<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Cheque;
use App\Models\Compliance;
use App\Models\ComplianceType;
use App\Models\Dv;
use App\Models\GuaranteeLetter;
use App\Models\Ors;
use App\Models\Status;
use App\Models\User;
use App\Support\ComplianceRoute;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VoucherActionService
{
    /** @param array{ors_no?: string|null, dv_no?: string|null, cheque_no?: string|null, cheque_date?: string|null} $data */
    public function recordFinancialDetails(Batch $batch, array $data, User $actor): Batch
    {
        return $batch->getConnection()->transaction(function () use ($batch, $data, $actor): Batch {
            $locked = Batch::query()->whereKey($batch->getKey())->lockForUpdate()->firstOrFail();
            $status = $locked->gls()->with('status')->lockForUpdate()->get()->first()?->status?->name;
            abort_unless(in_array($status, ['For Pre-Audit', 'For Crediting', 'Paid'], true), 409, 'Financial details cannot be recorded at this workflow stage.');
            abort_if(! filled($data['ors_no'] ?? null) && ! filled($data['dv_no'] ?? null) && ! filled($data['cheque_no'] ?? null), 422, 'Enter at least one financial detail.');
            if ($status === 'Paid' && (! filled($data['cheque_no'] ?? null) || ! filled($data['cheque_date'] ?? null))) {
                throw ValidationException::withMessages([
                    'cheque_no' => 'Cheque number is required for a paid voucher.',
                    'cheque_date' => 'Cheque date is required for a paid voucher.',
                ]);
            }
            if ($status === 'For Pre-Audit' && filled($data['dv_no'] ?? null)) {
                throw ValidationException::withMessages(['dv_no' => 'DV details can be recorded once the voucher reaches For Crediting.']);
            }
            if ($status !== 'Paid' && (filled($data['cheque_no'] ?? null) || filled($data['cheque_date'] ?? null))) {
                throw ValidationException::withMessages(['cheque_no' => 'Cheque details can be recorded once the voucher is Paid.']);
            }
            $previous = ['ors_no' => $locked->ors?->ors_no, 'dv_no' => $locked->dv?->dv_no, 'cheque_no' => $locked->cheque?->cheque_no, 'cheque_date' => $locked->cheque?->cheque_date?->toDateString()];
            $next = [
                'ors_no' => filled($data['ors_no'] ?? null) ? trim($data['ors_no']) : $previous['ors_no'],
                'dv_no' => filled($data['dv_no'] ?? null) ? trim($data['dv_no']) : $previous['dv_no'],
                'cheque_no' => filled($data['cheque_no'] ?? null) ? trim($data['cheque_no']) : $previous['cheque_no'],
                'cheque_date' => filled($data['cheque_no'] ?? null) ? $data['cheque_date'] : $previous['cheque_date'],
            ];
            if ($previous === $next) {
                throw ValidationException::withMessages([
                    'financial_details' => 'Change at least one financial detail before saving.',
                ]);
            }
            if (filled($data['ors_no'] ?? null)) {
                $locked->ors()->associate(Ors::query()->firstOrCreate(['ors_no' => trim($data['ors_no'])]));
            }
            if (filled($data['dv_no'] ?? null)) {
                $locked->dv()->associate(Dv::query()->firstOrCreate(['dv_no' => trim($data['dv_no'])]));
            }
            if (filled($data['cheque_no'] ?? null)) {
                $cheque = Cheque::query()->where('cheque_no', trim($data['cheque_no']))->lockForUpdate()->first();
                abort_if($cheque !== null && $cheque->batches()->whereKeyNot($locked->getKey())->exists(), 409, 'This cheque number is already assigned to another voucher.');
                if ($cheque !== null && $cheque->cheque_date?->toDateString() !== $data['cheque_date']) {
                    $cheque->cheque_date = $data['cheque_date'];
                    $cheque->save();
                }
                $locked->cheque()->associate($cheque ?? Cheque::query()->create(['cheque_no' => trim($data['cheque_no']), 'cheque_date' => $data['cheque_date']]));
            }
            $locked->save();
            activity()->performedOn($locked)->causedBy($actor)->event('record_financial_details')->withProperties(['old' => $previous, 'attributes' => ['ors_no' => $locked->ors?->ors_no, 'dv_no' => $locked->dv?->dv_no, 'cheque_no' => $locked->cheque?->cheque_no, 'cheque_date' => $locked->cheque?->cheque_date?->toDateString()]])->log('Reviewer updated voucher financial details.');

            return $locked->fresh()->load(VoucherCreationService::DETAIL_RELATIONSHIPS);
        });
    }

    public function approveBoxAReview(Batch $batch, User $actor): Batch
    {
        return $batch->getConnection()->transaction(function () use ($batch, $actor): Batch {
            $locked = Batch::query()->whereKey($batch->getKey())->lockForUpdate()->firstOrFail();
            $letters = $locked->gls()->with('status')->lockForUpdate()->get();
            abort_if($letters->isEmpty() || $letters->contains(fn (GuaranteeLetter $letter) => $letter->status?->name !== 'On Process'), 409, 'This voucher is no longer waiting for Box A review.');
            abort_if($locked->hasOpenCompliance() || $letters->contains(fn (GuaranteeLetter $letter): bool => $letter->box_a_reviewed_by !== null), 409, 'This voucher cannot be reviewed for Outgoing.');
            foreach ($letters as $letter) {
                $letter->box_a_reviewed_by = $actor->getKey();
                $letter->saveQuietly();
            }
            activity()->performedOn($locked)->causedBy($actor)->event('box_a_reviewed')->log('Box A Reviewer reviewed voucher for Outgoing.');

            return $locked->fresh()->load(VoucherCreationService::DETAIL_RELATIONSHIPS);
        });
    }

    /** @param array{ors_no?: string|null, date_fwd_to_actg: string, remarks?: string|null} $data */
    public function forwardToBoxC(Batch $batch, array $data, User $actor): Batch
    {
        return $batch->getConnection()->transaction(function () use ($batch, $data, $actor): Batch {
            $lockedBatch = Batch::query()->whereKey($batch->getKey())->lockForUpdate()->firstOrFail();
            abort_if($lockedBatch->hasOpenCompliance(), 409, 'Resolve the open compliance before forwarding this voucher.');

            $letters = $lockedBatch->gls()
                ->with('status')
                ->orderBy('guarantee_letters.id')
                ->lockForUpdate()
                ->get();

            abort_if(
                $letters->isEmpty() || $letters->contains(fn (GuaranteeLetter $letter): bool => $letter->status?->name !== 'For Obligation'),
                409,
                'This voucher is no longer waiting for Box C forwarding.',
            );
            abort_if(filled($data['ors_no'] ?? null) && $lockedBatch->ors_id !== null, 409, 'An ORS number has already been recorded for this voucher.');
            abort_if(
                $letters->contains(fn (GuaranteeLetter $letter): bool => $letter->date_fwd_to_actg !== null),
                409,
                'The Box C forwarding date has already been recorded.',
            );
            if ($letters->contains(fn (GuaranteeLetter $letter): bool => $letter->date_fwd_to_budget === null || $data['date_fwd_to_actg'] < $letter->date_fwd_to_budget->toDateString())) {
                throw ValidationException::withMessages([
                    'date_fwd_to_actg' => 'The Box C forwarding date cannot be before the Box B forwarding date.',
                ]);
            }

            $nextStatus = Status::query()->where('name', 'For Pre-Audit')->where('is_active', true)->first();
            if ($nextStatus === null) {
                throw ValidationException::withMessages([
                    'date_fwd_to_actg' => 'The For Pre-Audit status is unavailable. Contact your administrator.',
                ]);
            }

            $ors = null;
            if (filled($data['ors_no'] ?? null)) {
                $ors = Ors::query()->firstOrCreate(['ors_no' => trim($data['ors_no'])]);
                $lockedBatch->ors()->associate($ors);
                $lockedBatch->save();
            }

            foreach ($letters as $letter) {
                $previousStatus = $letter->status->name;
                $letter->date_fwd_to_actg = $data['date_fwd_to_actg'];
                $letter->status()->associate($nextStatus);

                $attributes = $letter->getDirty();
                $oldAttributes = array_intersect_key($letter->getRawOriginal(), $attributes);

                $letter->disableLogging();
                try {
                    $letter->save();
                } finally {
                    $letter->enableLogging();
                }

                activity()
                    ->performedOn($letter)
                    ->causedBy($actor)
                    ->event('forward_to_box_c')
                    ->withProperties([
                        'batch_id' => $lockedBatch->id,
                        'ors_id' => $ors?->id,
                        'ors_no' => $ors?->ors_no,
                        'from_status' => $previousStatus,
                        'to_status' => $nextStatus->name,
                        'date_fwd_to_actg' => $data['date_fwd_to_actg'],
                        'remarks' => $data['remarks'] ?? null,
                    ])
                    ->withChanges(['old' => $oldAttributes, 'attributes' => $attributes])
                    ->log('Voucher forwarded to Box C.');
            }

            activity()
                ->performedOn($lockedBatch)
                ->causedBy($actor)
                ->event('forward_to_box_c')
                ->withProperties([
                    'voucher_id' => $lockedBatch->voucher_id,
                    'ors_id' => $ors?->id,
                    'ors_no' => $ors?->ors_no,
                    'from_status' => 'For Obligation',
                    'to_status' => $nextStatus->name,
                    'date_fwd_to_actg' => $data['date_fwd_to_actg'],
                    'remarks' => $data['remarks'] ?? null,
                ])
                ->log('Voucher forwarded to Box C.');

            return $lockedBatch->fresh()->load(VoucherCreationService::DETAIL_RELATIONSHIPS);
        });
    }

    /** @param array{date_fwd_to_budget: string, remarks?: string|null} $data */
    public function forwardToBoxB(Batch $batch, array $data, User $actor): Batch
    {
        return $batch->getConnection()->transaction(function () use ($batch, $data, $actor): Batch {
            $lockedBatch = Batch::query()->whereKey($batch->getKey())->lockForUpdate()->firstOrFail();
            abort_if($lockedBatch->hasOpenCompliance(), 409, 'Resolve the open compliance before forwarding this voucher.');
            $letters = $lockedBatch->gls()
                ->with('status')
                ->orderBy('guarantee_letters.id')
                ->lockForUpdate()
                ->get();

            abort_if(
                $letters->isEmpty() || $letters->contains(fn (GuaranteeLetter $letter): bool => $letter->status?->name !== 'On Process'),
                409,
                'This voucher is no longer waiting for Box B forwarding.',
            );
            abort_if($letters->contains(fn (GuaranteeLetter $letter): bool => $letter->box_a_reviewed_by === null), 409, 'Box A Reviewer review is required before forwarding to Box B.');
            abort_if(
                $letters->contains(fn (GuaranteeLetter $letter): bool => $letter->date_fwd_to_budget !== null),
                409,
                'The Box B forwarding date has already been recorded.',
            );

            $nextStatus = Status::query()->where('name', 'For Obligation')->where('is_active', true)->first();
            if ($nextStatus === null) {
                throw ValidationException::withMessages([
                    'date_fwd_to_budget' => 'The For Obligation status is unavailable. Contact your administrator.',
                ]);
            }

            foreach ($letters as $letter) {
                $previousStatus = $letter->status->name;
                $letter->date_fwd_to_budget = $data['date_fwd_to_budget'];
                $letter->status()->associate($nextStatus);

                $attributes = $letter->getDirty();
                $oldAttributes = array_intersect_key($letter->getRawOriginal(), $attributes);

                $letter->disableLogging();
                try {
                    $letter->save();
                } finally {
                    $letter->enableLogging();
                }

                activity()
                    ->performedOn($letter)
                    ->causedBy($actor)
                    ->event('forward_to_box_b')
                    ->withProperties([
                        'batch_id' => $lockedBatch->id,
                        'from_status' => $previousStatus,
                        'to_status' => $nextStatus->name,
                        'date_fwd_to_budget' => $data['date_fwd_to_budget'],
                        'remarks' => $data['remarks'] ?? null,
                    ])
                    ->withChanges(['old' => $oldAttributes, 'attributes' => $attributes])
                    ->log('Voucher forwarded to Box B.');
            }

            activity()
                ->performedOn($lockedBatch)
                ->causedBy($actor)
                ->event('forward_to_box_b')
                ->withProperties([
                    'voucher_id' => $lockedBatch->voucher_id,
                    'date_fwd_to_budget' => $data['date_fwd_to_budget'],
                    'remarks' => $data['remarks'] ?? null,
                    'from_status' => 'On Process',
                    'to_status' => $nextStatus->name,
                ])
                ->log('Voucher forwarded to Box B.');

            return $lockedBatch->fresh()->load(VoucherCreationService::DETAIL_RELATIONSHIPS);
        });
    }

    /** @param array{forwarded_date: string, remarks: string} $data */
    public function returnForCompliance(Batch $batch, array $data, User $actor): Batch
    {
        return $batch->getConnection()->transaction(function () use ($batch, $data, $actor): Batch {
            $lockedBatch = Batch::query()->whereKey($batch->getKey())->lockForUpdate()->firstOrFail();
            abort_if($lockedBatch->voucher_id === null, 409, 'This batch has no voucher to return.');
            abort_if($lockedBatch->hasOpenCompliance(), 409, 'This voucher already has open compliance.');

            $letters = $lockedBatch->gls()
                ->with('status')
                ->orderBy('guarantee_letters.id')
                ->lockForUpdate()
                ->get();

            abort_if(
                $letters->isEmpty() || $letters->contains(fn (GuaranteeLetter $letter): bool => $letter->status?->name !== 'For Review by RMCD'),
                409,
                'This voucher is no longer waiting for reviewer action.',
            );
            $selectedGlCompliances = $this->selectedGlCompliancesForLetters($letters, $data);

            $complianceType = ComplianceType::query()
                ->where('name', 'Reviewer to Voucher Creation')
                ->where('is_active', true)
                ->first();
            $nextStatus = Status::query()->where('name', 'For Compliance')->where('is_active', true)->first();

            if ($complianceType === null || $nextStatus === null) {
                throw ValidationException::withMessages([
                    'compliance' => 'The reviewer compliance route is unavailable. Contact your administrator.',
                ]);
            }

            $compliances = $this->createVoucherCompliances($lockedBatch, $complianceType, $data, $selectedGlCompliances);
            $compliance = $compliances->first();

            foreach ($letters as $letter) {
                $letter->status()->associate($nextStatus);
                $letter->disableLogging();
                try {
                    $letter->save();
                } finally {
                    $letter->enableLogging();
                }

                activity()
                    ->performedOn($letter)
                    ->causedBy($actor)
                    ->event('return_for_compliance')
                    ->withProperties([
                        'batch_id' => $lockedBatch->id,
                        'compliance_id' => $compliance->id,
                        'selected_gl_compliances' => $this->selectedGlComplianceAuditProperties($selectedGlCompliances),
                        'from_status' => 'For Review by RMCD',
                        'to_status' => $nextStatus->name,
                        'forwarded_date' => $data['forwarded_date'],
                        'remarks' => $compliance->remarks,
                    ])
                    ->log('Voucher returned for compliance.');
            }

            activity()
                ->performedOn($lockedBatch)
                ->causedBy($actor)
                ->event('return_for_compliance')
                ->withProperties([
                    'compliance_id' => $compliance->id,
                    'compliance_type' => $complianceType->name,
                    'selected_gl_compliances' => $this->selectedGlComplianceAuditProperties($selectedGlCompliances),
                    'forwarded_date' => $data['forwarded_date'],
                    'remarks' => $compliance->remarks,
                    'from_status' => 'For Review by RMCD',
                    'to_status' => $nextStatus->name,
                ])
                ->log('Voucher returned for compliance.');

            return $lockedBatch->fresh()->load(VoucherCreationService::DETAIL_RELATIONSHIPS);
        });
    }

    /** @param array{forwarded_date: string, remarks: string} $data */
    public function returnFromBoxAForCompliance(Batch $batch, array $data, User $actor): Batch
    {
        return $batch->getConnection()->transaction(function () use ($batch, $data, $actor): Batch {
            $lockedBatch = Batch::query()->whereKey($batch->getKey())->lockForUpdate()->firstOrFail();
            abort_if($lockedBatch->hasOpenCompliance(), 409, 'This voucher already has open compliance.');
            $letters = $lockedBatch->gls()->with('status')->orderBy('guarantee_letters.id')->lockForUpdate()->get();
            abort_if($letters->isEmpty() || $letters->contains(fn (GuaranteeLetter $letter): bool => $letter->status?->name !== 'On Process'), 409, 'This voucher is no longer waiting for Box A review.');
            abort_if($letters->contains(fn (GuaranteeLetter $letter): bool => $letter->box_a_reviewed_by !== null), 409, 'This voucher has already been reviewed for Outgoing.');
            $selectedGlCompliances = $this->selectedGlCompliancesForLetters($letters, $data);

            $type = ComplianceType::query()->where('name', 'Box A Reviewer to Reviewer')->where('is_active', true)->first();
            $status = Status::query()->where('name', 'For Compliance')->where('is_active', true)->first();
            if ($type === null || $status === null) {
                throw ValidationException::withMessages(['compliance' => 'The Box A Reviewer compliance route is unavailable. Contact your administrator.']);
            }
            $compliances = $this->createVoucherCompliances($lockedBatch, $type, $data, $selectedGlCompliances);
            $compliance = $compliances->first();
            foreach ($letters as $letter) {
                $letter->status()->associate($status);
                $letter->disableLogging();
                try {
                    $letter->save();
                } finally {
                    $letter->enableLogging();
                }
            }
            activity()->performedOn($lockedBatch)->causedBy($actor)->event('return_for_compliance')->withProperties([
                'compliance_id' => $compliance->id, 'compliance_type' => $type->name,
                'selected_gl_compliances' => $this->selectedGlComplianceAuditProperties($selectedGlCompliances),
                'from_status' => 'On Process', 'to_status' => $status->name,
                'forwarded_date' => $data['forwarded_date'], 'remarks' => $compliance->remarks,
            ])->log('Box A Reviewer returned voucher for compliance.');

            return $lockedBatch->fresh()->load(VoucherCreationService::DETAIL_RELATIONSHIPS);
        });
    }

    /** @param array{forwarded_date: string, compliance_classification: 'internal'|'external', remarks: string} $data */
    public function returnFromBudgetForCompliance(Batch $batch, array $data, User $actor): Batch
    {
        return $batch->getConnection()->transaction(function () use ($batch, $data, $actor): Batch {
            $lockedBatch = Batch::query()->whereKey($batch->getKey())->lockForUpdate()->firstOrFail();
            abort_if($lockedBatch->hasOpenCompliance(), 409, 'This voucher already has open compliance.');

            $letters = $lockedBatch->gls()->with('status')->orderBy('guarantee_letters.id')->lockForUpdate()->get();
            abort_if(
                $letters->isEmpty() || $letters->contains(fn (GuaranteeLetter $letter): bool => $letter->status?->name !== 'For Obligation'),
                409,
                'This voucher is no longer waiting for Budget review.',
            );
            $selectedGlCompliances = $this->selectedGlCompliancesForLetters($letters, $data);

            $type = ComplianceType::query()->where('name', 'Budget to Compliance Officer')->where('is_active', true)->first();
            $status = Status::query()->where('name', 'For Compliance')->where('is_active', true)->first();
            if ($type === null || $status === null) {
                throw ValidationException::withMessages([
                    'compliance' => 'The Budget compliance route is unavailable. Contact your administrator.',
                ]);
            }

            $compliances = $this->createVoucherCompliances($lockedBatch, $type, $data, $selectedGlCompliances);
            $compliance = $compliances->first();

            foreach ($letters as $letter) {
                $letter->status()->associate($status);
                $letter->disableLogging();
                try {
                    $letter->save();
                } finally {
                    $letter->enableLogging();
                }

                activity()->performedOn($letter)->causedBy($actor)->event('return_for_compliance')->withProperties([
                    'batch_id' => $lockedBatch->id,
                    'compliance_id' => $compliance->id,
                    'selected_gl_compliances' => $this->selectedGlComplianceAuditProperties($selectedGlCompliances),
                    'compliance_type' => $type->name,
                    'from_status' => 'For Obligation',
                    'to_status' => $status->name,
                    'forwarded_date' => $data['forwarded_date'],
                    'remarks' => $compliance->remarks,
                ])->log('Voucher returned to FS Compliance from Budget.');
            }

            activity()->performedOn($lockedBatch)->causedBy($actor)->event('return_for_compliance')->withProperties([
                'compliance_id' => $compliance->id,
                'compliance_type' => $type->name,
                'selected_gl_compliances' => $this->selectedGlComplianceAuditProperties($selectedGlCompliances),
                'from_status' => 'For Obligation',
                'to_status' => $status->name,
                'forwarded_date' => $data['forwarded_date'],
                'remarks' => $compliance->remarks,
            ])->log('Voucher returned to FS Compliance from Budget.');

            return $lockedBatch->fresh()->load(VoucherCreationService::DETAIL_RELATIONSHIPS);
        });
    }

    /** @param array{dv_no?: string|null, date_fwd_to_dir_dv: string, remarks?: string|null} $data */
    public function forwardToBoxD(Batch $batch, array $data, User $actor): Batch
    {
        return $batch->getConnection()->transaction(function () use ($batch, $data, $actor): Batch {
            $lockedBatch = Batch::query()->whereKey($batch->getKey())->lockForUpdate()->firstOrFail();
            abort_if($lockedBatch->hasOpenCompliance(), 409, 'Resolve the open compliance before forwarding this voucher.');
            abort_if(filled($data['dv_no'] ?? null) && $lockedBatch->dv_id !== null, 409, 'A DV number has already been recorded for this voucher.');

            $letters = $lockedBatch->gls()->with('status')->orderBy('guarantee_letters.id')->lockForUpdate()->get();
            abort_if(
                $letters->isEmpty() || $letters->contains(fn (GuaranteeLetter $letter): bool => $letter->status?->name !== 'For Pre-Audit'),
                409,
                'This voucher is no longer waiting for Box D forwarding.',
            );
            abort_if(
                $letters->contains(fn (GuaranteeLetter $letter): bool => $letter->date_fwd_to_actg === null),
                409,
                'The Box C forwarding date must be recorded before forwarding to Box D.',
            );
            if ($letters->contains(fn (GuaranteeLetter $letter): bool => $data['date_fwd_to_dir_dv'] < $letter->date_fwd_to_actg->toDateString())) {
                throw ValidationException::withMessages([
                    'date_fwd_to_dir_dv' => 'The Box D forwarding date cannot be before the Box C forwarding date.',
                ]);
            }

            $nextStatus = Status::query()->where('name', 'For Crediting')->where('is_active', true)->first();
            if ($nextStatus === null) {
                throw ValidationException::withMessages([
                    'date_fwd_to_dir_dv' => 'The For Crediting status is unavailable. Contact your administrator.',
                ]);
            }

            $dv = null;
            if (filled($data['dv_no'] ?? null)) {
                $dvNumber = trim($data['dv_no']);
                $dv = Dv::query()->where('dv_no', $dvNumber)->lockForUpdate()->first();
                abort_if($dv !== null && $dv->batches()->exists(), 409, 'This DV number is already assigned to another voucher.');
                $dv ??= Dv::query()->create(['dv_no' => $dvNumber]);
                $lockedBatch->dv()->associate($dv);
                $lockedBatch->save();
            }

            foreach ($letters as $letter) {
                $previousStatus = $letter->status->name;
                $letter->date_fwd_to_dir_dv = $data['date_fwd_to_dir_dv'];
                $letter->status()->associate($nextStatus);

                $attributes = $letter->getDirty();
                $oldAttributes = array_intersect_key($letter->getRawOriginal(), $attributes);
                $letter->disableLogging();
                try {
                    $letter->save();
                } finally {
                    $letter->enableLogging();
                }

                activity()->performedOn($letter)->causedBy($actor)->event('forward_to_box_d')->withProperties([
                    'batch_id' => $lockedBatch->id,
                    'dv_id' => $dv?->id,
                    'dv_no' => $dv?->dv_no,
                    'from_status' => $previousStatus,
                    'to_status' => $nextStatus->name,
                    'date_fwd_to_dir_dv' => $data['date_fwd_to_dir_dv'],
                    'remarks' => $data['remarks'] ?? null,
                ])->withChanges(['old' => $oldAttributes, 'attributes' => $attributes])
                    ->log('Voucher forwarded to Director Box D.');
            }

            activity()->performedOn($lockedBatch)->causedBy($actor)->event('forward_to_box_d')->withProperties([
                'voucher_id' => $lockedBatch->voucher_id,
                'dv_id' => $dv?->id,
                'dv_no' => $dv?->dv_no,
                'from_status' => 'For Pre-Audit',
                'to_status' => $nextStatus->name,
                'date_fwd_to_dir_dv' => $data['date_fwd_to_dir_dv'],
                'remarks' => $data['remarks'] ?? null,
            ])->log('Voucher forwarded to Director Box D.');

            return $lockedBatch->fresh()->load(VoucherCreationService::DETAIL_RELATIONSHIPS);
        });
    }

    /** @param array{forwarded_date: string, compliance_classification: 'internal'|'external', remarks: string} $data */
    public function returnFromAccountingForCompliance(Batch $batch, array $data, User $actor): Batch
    {
        return $batch->getConnection()->transaction(function () use ($batch, $data, $actor): Batch {
            $lockedBatch = Batch::query()->whereKey($batch->getKey())->lockForUpdate()->firstOrFail();
            abort_if($lockedBatch->hasOpenCompliance(), 409, 'This voucher already has open compliance.');
            $letters = $lockedBatch->gls()->with('status')->orderBy('guarantee_letters.id')->lockForUpdate()->get();
            abort_if(
                $letters->isEmpty() || $letters->contains(fn (GuaranteeLetter $letter): bool => $letter->status?->name !== 'For Pre-Audit'),
                409,
                'This voucher is no longer waiting for Accounting review.',
            );
            $selectedGlCompliances = $this->selectedGlCompliancesForLetters($letters, $data);

            $type = ComplianceType::query()->where('name', 'Accounting to Compliance Officer')->where('is_active', true)->first();
            $status = Status::query()->where('name', 'For Compliance')->where('is_active', true)->first();
            if ($type === null || $status === null) {
                throw ValidationException::withMessages([
                    'compliance' => 'The Accounting compliance route is unavailable. Contact your administrator.',
                ]);
            }

            $compliances = $this->createVoucherCompliances($lockedBatch, $type, $data, $selectedGlCompliances);
            $compliance = $compliances->first();

            foreach ($letters as $letter) {
                $letter->status()->associate($status);
                $letter->disableLogging();
                try {
                    $letter->save();
                } finally {
                    $letter->enableLogging();
                }
                activity()->performedOn($letter)->causedBy($actor)->event('return_for_compliance')->withProperties([
                    'batch_id' => $lockedBatch->id,
                    'compliance_id' => $compliance->id,
                    'selected_gl_compliances' => $this->selectedGlComplianceAuditProperties($selectedGlCompliances),
                    'compliance_type' => $type->name,
                    'from_status' => 'For Pre-Audit',
                    'to_status' => $status->name,
                    'forwarded_date' => $data['forwarded_date'],
                    'remarks' => $compliance->remarks,
                ])->log('Voucher returned to FS Compliance from Accounting.');
            }

            activity()->performedOn($lockedBatch)->causedBy($actor)->event('return_for_compliance')->withProperties([
                'compliance_id' => $compliance->id,
                'compliance_type' => $type->name,
                'selected_gl_compliances' => $this->selectedGlComplianceAuditProperties($selectedGlCompliances),
                'from_status' => 'For Pre-Audit',
                'to_status' => $status->name,
                'forwarded_date' => $data['forwarded_date'],
                'remarks' => $compliance->remarks,
            ])->log('Voucher returned to FS Compliance from Accounting.');

            return $lockedBatch->fresh()->load(VoucherCreationService::DETAIL_RELATIONSHIPS);
        });
    }

    /** @param array{date_fwd_to_dir_fs: string, remarks?: string|null} $data */
    public function tagSignedByCipDirector(Batch $batch, array $data, User $actor): Batch
    {
        return $batch->getConnection()->transaction(function () use ($batch, $data, $actor): Batch {
            $lockedBatch = Batch::query()->whereKey($batch->getKey())->lockForUpdate()->firstOrFail();
            $letters = $lockedBatch->gls()->with('status')->lockForUpdate()->get();
            abort_if($letters->isEmpty() || $letters->contains(fn (GuaranteeLetter $letter): bool => $letter->status?->name !== 'For Crediting'), 409, 'This voucher is no longer waiting for CIP Director signing.');
            abort_if($lockedBatch->hasOpenCompliance() || $letters->contains(fn (GuaranteeLetter $letter): bool => $letter->date_fwd_to_dir_fs !== null), 409, 'This voucher cannot be tagged as signed.');
            abort_if($letters->contains(fn (GuaranteeLetter $letter): bool => $letter->date_fwd_to_dir_dv === null || $data['date_fwd_to_dir_fs'] < $letter->date_fwd_to_dir_dv->toDateString()), 422, 'The Financial Service forwarding date cannot be before the Box D forwarding date.');
            foreach ($letters as $letter) {
                $letter->date_fwd_to_dir_fs = $data['date_fwd_to_dir_fs'];
                $letter->saveQuietly();
            }
            activity()->performedOn($lockedBatch)->causedBy($actor)->event('cip_dir_signed')->withProperties([
                'date_fwd_to_dir_fs' => $data['date_fwd_to_dir_fs'], 'remarks' => $data['remarks'] ?? null,
            ])->log('Voucher tagged as signed by CIP Director and forwarded to Financial Service.');

            return $lockedBatch->fresh()->load(VoucherCreationService::DETAIL_RELATIONSHIPS);
        });
    }

    /** @param array{date_fwd_to_cash: string, remarks?: string|null} $data */
    public function forwardToCash(Batch $batch, array $data, User $actor): Batch
    {
        return $batch->getConnection()->transaction(function () use ($batch, $data, $actor): Batch {
            $lockedBatch = Batch::query()->whereKey($batch->getKey())->lockForUpdate()->firstOrFail();
            $letters = $lockedBatch->gls()->with('status')->lockForUpdate()->get();
            abort_if($letters->isEmpty() || $letters->contains(fn (GuaranteeLetter $letter): bool => $letter->status?->name !== 'For Crediting'), 409, 'This voucher is no longer waiting for Cash forwarding.');
            abort_if($lockedBatch->hasOpenCompliance() || $letters->contains(fn (GuaranteeLetter $letter): bool => $letter->date_fwd_to_dir_fs === null || $letter->date_fwd_to_cash !== null), 409, 'This voucher cannot be forwarded to Cash.');
            abort_if($letters->contains(fn (GuaranteeLetter $letter): bool => $data['date_fwd_to_cash'] < $letter->date_fwd_to_dir_fs->toDateString()), 422, 'The Cash forwarding date cannot be before the Financial Service forwarding date.');
            foreach ($letters as $letter) {
                $letter->date_fwd_to_cash = $data['date_fwd_to_cash'];
                $letter->saveQuietly();
            }
            activity()->performedOn($lockedBatch)->causedBy($actor)->event('forward_to_cash')->withProperties([
                'date_fwd_to_cash' => $data['date_fwd_to_cash'], 'remarks' => $data['remarks'] ?? null,
            ])->log('Voucher forwarded to Cash.');

            return $lockedBatch->fresh()->load(VoucherCreationService::DETAIL_RELATIONSHIPS);
        });
    }

    /** @param array{cheque_no: string, cheque_date: string, remarks?: string|null} $data */
    public function markAsPaid(Batch $batch, array $data, User $actor): Batch
    {
        return $batch->getConnection()->transaction(function () use ($batch, $data, $actor): Batch {
            $lockedBatch = Batch::query()->whereKey($batch->getKey())->lockForUpdate()->firstOrFail();
            abort_if($lockedBatch->hasOpenCompliance(), 409, 'Resolve the open compliance before marking this voucher as paid.');
            $letters = $lockedBatch->gls()->with('status')->orderBy('guarantee_letters.id')->lockForUpdate()->get();
            abort_if($letters->isEmpty() || $letters->contains(fn (GuaranteeLetter $letter): bool => $letter->status?->name !== 'For Crediting'), 409, 'This voucher is no longer waiting to be marked as paid.');
            abort_if($letters->contains(fn (GuaranteeLetter $letter): bool => $letter->date_fwd_to_cash === null), 409, 'Forward this voucher to Cash before marking it as paid.');
            $paidStatus = Status::query()->where('name', 'Paid')->where('is_active', true)->first();
            if ($paidStatus === null) {
                throw ValidationException::withMessages(['status' => 'The Paid status is unavailable. Contact your administrator.']);
            }

            $cheque = Cheque::query()->where('cheque_no', trim($data['cheque_no']))->lockForUpdate()->first();
            abort_if($cheque !== null && $cheque->batches()->whereKeyNot($lockedBatch->getKey())->exists(), 409, 'This cheque number is already assigned to another voucher.');
            if ($cheque !== null && $cheque->cheque_date?->toDateString() !== $data['cheque_date']) {
                $cheque->cheque_date = $data['cheque_date'];
                $cheque->save();
            }
            $lockedBatch->cheque()->associate($cheque ?? Cheque::query()->create([
                'cheque_no' => trim($data['cheque_no']),
                'cheque_date' => $data['cheque_date'],
            ]));
            $lockedBatch->save();

            foreach ($letters as $letter) {
                $previousStatus = $letter->status->name;
                $letter->status()->associate($paidStatus);
                $attributes = $letter->getDirty();
                $oldAttributes = array_intersect_key($letter->getRawOriginal(), $attributes);
                $letter->disableLogging();
                try {
                    $letter->save();
                } finally {
                    $letter->enableLogging();
                }
                activity()->performedOn($letter)->causedBy($actor)->event('mark_as_paid')->withProperties([
                    'batch_id' => $lockedBatch->id, 'from_status' => $previousStatus, 'to_status' => $paidStatus->name,
                    'remarks' => $data['remarks'] ?? null,
                    'cheque_no' => $lockedBatch->cheque->cheque_no,
                    'cheque_date' => $lockedBatch->cheque->cheque_date?->toDateString(),
                ])->withChanges(['old' => $oldAttributes, 'attributes' => $attributes])->log('Voucher marked as paid.');
            }
            activity()->performedOn($lockedBatch)->causedBy($actor)->event('mark_as_paid')->withProperties([
                'from_status' => 'For Crediting', 'to_status' => $paidStatus->name,
                'remarks' => $data['remarks'] ?? null,
                'cheque_no' => $lockedBatch->cheque->cheque_no,
                'cheque_date' => $lockedBatch->cheque->cheque_date?->toDateString(),
            ])->log('Voucher marked as paid.');

            return $lockedBatch->fresh()->load(VoucherCreationService::DETAIL_RELATIONSHIPS);
        });
    }

    /** @param array{forwarded_date: string, remarks: string} $data */
    public function returnFromCashForCompliance(Batch $batch, array $data, User $actor): Batch
    {
        return $batch->getConnection()->transaction(function () use ($batch, $data, $actor): Batch {
            $lockedBatch = Batch::query()->whereKey($batch->getKey())->lockForUpdate()->firstOrFail();
            $letters = $lockedBatch->gls()->with('status')->orderBy('guarantee_letters.id')->lockForUpdate()->get();
            abort_if($letters->isEmpty() || $letters->contains(fn (GuaranteeLetter $letter): bool => $letter->status?->name !== 'For Crediting'), 409, 'This voucher is no longer waiting for Cash review.');
            abort_if($lockedBatch->hasOpenCompliance(), 409, 'This voucher cannot be returned for Cash compliance.');
            abort_if($letters->contains(fn (GuaranteeLetter $letter): bool => $letter->date_fwd_to_cash === null), 409, 'Forward this voucher to Cash before returning it for compliance.');
            $selectedGlCompliances = $this->selectedGlCompliancesForLetters($letters, $data);

            $type = ComplianceType::query()->where('name', 'Cash to Reviewer')->where('is_active', true)->first();
            $status = Status::query()->where('name', 'For Compliance')->where('is_active', true)->first();
            if ($type === null || $status === null) {
                throw ValidationException::withMessages(['compliance' => 'The Cash compliance route is unavailable. Contact your administrator.']);
            }

            $compliances = $this->createVoucherCompliances($lockedBatch, $type, $data, $selectedGlCompliances);
            $compliance = $compliances->first();
            foreach ($letters as $letter) {
                $letter->status()->associate($status);
                $letter->disableLogging();
                try {
                    $letter->save();
                } finally {
                    $letter->enableLogging();
                }
                activity()->performedOn($letter)->causedBy($actor)->event('return_for_compliance')->withProperties([
                    'batch_id' => $lockedBatch->id, 'compliance_id' => $compliance->id, 'compliance_type' => $type->name,
                    'selected_gl_compliances' => $this->selectedGlComplianceAuditProperties($selectedGlCompliances),
                    'from_status' => 'For Crediting', 'to_status' => $status->name, 'forwarded_date' => $data['forwarded_date'], 'remarks' => $compliance->remarks,
                ])->log('Voucher returned for compliance from Cash.');
            }
            activity()->performedOn($lockedBatch)->causedBy($actor)->event('return_for_compliance')->withProperties([
                'compliance_id' => $compliance->id, 'compliance_type' => $type->name,
                'selected_gl_compliances' => $this->selectedGlComplianceAuditProperties($selectedGlCompliances),
                'from_status' => 'For Crediting', 'to_status' => $status->name, 'forwarded_date' => $data['forwarded_date'], 'remarks' => $compliance->remarks,
            ])->log('Voucher returned for compliance from Cash.');

            return $lockedBatch->fresh()->load(VoucherCreationService::DETAIL_RELATIONSHIPS);
        });
    }

    /** @param array{resolved_date: string, remarks?: string|null} $data */
    public function resolveForCompliance(Compliance $compliance, array $data, User $actor): void
    {
        $compliance->getConnection()->transaction(function () use ($compliance, $data, $actor): void {
            $lockedCompliance = Compliance::query()
                ->with('complianceType')
                ->whereKey($compliance->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            abort_if($lockedCompliance->resolved_at !== null, 409, 'This compliance record has already been resolved.');
            $route = ComplianceRoute::resubmissionRoute($lockedCompliance->complianceType->name);
            abort_unless(
                $route !== null && $actor->hasAnyRole(['admin', $route['role']]),
                409,
                'This compliance route cannot be resubmitted yet.',
            );

            $resolvedAt = now('Asia/Manila');
            $resolvedDate = Carbon::parse($data['resolved_date'])->startOfDay();

            if ($lockedCompliance->batch_id === null) {
                $letter = GuaranteeLetter::query()->with('status')->whereKey($lockedCompliance->guarantee_letter_id)->lockForUpdate()->firstOrFail();
                abort_if($resolvedDate->lessThan($lockedCompliance->returned_at->copy()->startOfDay()), 422, 'The compliance date cannot be before the returned date.');
                abort_if($letter->status?->name !== 'For Compliance', 409, 'This GL is no longer waiting for compliance resubmission.');
                $status = Status::query()->where('name', $route['status'])->where('is_active', true)->firstOrFail();
                $letter->status()->associate($status);
                $letter->disableLogging();
                try {
                    $letter->save();
                } finally {
                    $letter->enableLogging();
                }
                $lockedCompliance->forceFill(['resolved_at' => $resolvedAt, 'duration' => $lockedCompliance->returned_at->diffInDays($resolvedAt, true), 'resolution_remarks' => filled($data['remarks'] ?? null) ? trim($data['remarks']) : null])->save();
                activity()
                    ->performedOn($lockedCompliance)
                    ->causedBy($actor)
                    ->event('resolve_compliance')
                    ->withProperties([
                        'compliance_id' => $lockedCompliance->id,
                        'resolved_date' => $data['resolved_date'],
                        'duration' => $lockedCompliance->duration,
                        'remarks' => $data['remarks'] ?? null,
                        'from_status' => 'For Compliance',
                        'to_status' => $status->name,
                    ])
                    ->log("GL compliance resolved and returned to {$status->name}.");

                return;
            }
            $voucherCompliances = Compliance::query()
                ->open()
                ->where('batch_id', $lockedCompliance->batch_id)
                ->when($lockedCompliance->compliance_group_id, fn ($query) => $query->where('compliance_group_id', $lockedCompliance->compliance_group_id), fn ($query) => $query->where('compliance_type_id', $lockedCompliance->compliance_type_id))
                ->lockForUpdate()
                ->get();

            abort_if($voucherCompliances->isEmpty(), 409, 'This voucher has no open compliance to resolve.');

            if ($voucherCompliances->contains(fn (Compliance $record): bool => $resolvedDate->lessThan($record->returned_at->copy()->startOfDay()))) {
                throw ValidationException::withMessages([
                    'resolved_date' => 'The compliance date cannot be before the returned date.',
                ]);
            }

            $status = Status::query()->where('name', $route['status'])->where('is_active', true)->first();
            if ($status === null) {
                throw ValidationException::withMessages([
                    'resolved_date' => "The {$route['status']} status is unavailable. Contact your administrator.",
                ]);
            }

            foreach ($voucherCompliances as $voucherCompliance) {
                $voucherCompliance->forceFill([
                    'resolved_at' => $resolvedAt,
                    'duration' => $voucherCompliance->returned_at->diffInDays($resolvedAt, true),
                    'resolution_remarks' => filled($data['remarks'] ?? null) ? trim($data['remarks']) : null,
                ])->save();
            }

            $letters = $lockedCompliance->batch->gls()
                ->with('status')
                ->orderBy('guarantee_letters.id')
                ->lockForUpdate()
                ->get();

            abort_if(
                $letters->isEmpty() || $letters->contains(fn (GuaranteeLetter $letter): bool => $letter->status?->name !== 'For Compliance'),
                409,
                'This voucher is no longer waiting for compliance resubmission.',
            );

            foreach ($letters as $letter) {
                $letter->status()->associate($status);
                $letter->disableLogging();
                try {
                    $letter->save();
                } finally {
                    $letter->enableLogging();
                }
            }

            activity()
                ->performedOn($lockedCompliance)
                ->causedBy($actor)
                ->event('resolve_compliance')
                ->withProperties([
                    'batch_id' => $lockedCompliance->batch_id,
                    'compliance_ids' => $voucherCompliances->pluck('id')->all(),
                    'resolved_date' => $data['resolved_date'],
                    'duration' => $lockedCompliance->duration,
                    'remarks' => $data['remarks'] ?? null,
                    'from_status' => 'For Compliance',
                    'to_status' => $status->name,
                ])
                ->log("Voucher compliance resolved and returned to {$status->name}.");
        });
    }

    /** @param array{date_fwd_to_dc: string, remarks?: string|null} $data */
    public function forwardToBoxA(Batch $batch, array $data, User $actor): Batch
    {
        return $batch->getConnection()->transaction(function () use ($batch, $data, $actor): Batch {
            $lockedBatch = Batch::query()->whereKey($batch->getKey())->lockForUpdate()->firstOrFail();
            abort_if($lockedBatch->hasOpenCompliance(), 409, 'Resolve the open compliance before forwarding this voucher.');

            $letters = $lockedBatch->gls()
                ->with('status')
                ->orderBy('guarantee_letters.id')
                ->lockForUpdate()
                ->get();

            abort_if(
                $letters->isEmpty() || $letters->contains(fn (GuaranteeLetter $letter): bool => $letter->status?->name !== 'For Review by RMCD'),
                409,
                'This voucher is no longer waiting for reviewer action.',
            );
            abort_if(
                $letters->contains(fn (GuaranteeLetter $letter): bool => $letter->date_fwd_to_dc !== null),
                409,
                'The Box A forwarding date has already been recorded.',
            );

            $nextStatus = Status::query()->where('name', 'On Process')->where('is_active', true)->first();
            if ($nextStatus === null) {
                throw ValidationException::withMessages([
                    'date_fwd_to_dc' => 'The On Process status is unavailable. Contact your administrator.',
                ]);
            }

            foreach ($letters as $letter) {
                $previousStatus = $letter->status->name;
                $letter->date_fwd_to_dc = $data['date_fwd_to_dc'];
                $letter->reviewed_by_rmcd = $actor->getKey();
                $letter->status()->associate($nextStatus);

                $attributes = $letter->getDirty();
                $oldAttributes = array_intersect_key($letter->getRawOriginal(), $attributes);

                $letter->disableLogging();
                try {
                    $letter->save();
                } finally {
                    $letter->enableLogging();
                }

                activity()
                    ->performedOn($letter)
                    ->causedBy($actor)
                    ->event('forward_to_box_a')
                    ->withProperties([
                        'batch_id' => $lockedBatch->id,
                        'from_status' => $previousStatus,
                        'to_status' => $nextStatus->name,
                        'date_fwd_to_dc' => $data['date_fwd_to_dc'],
                        'remarks' => $data['remarks'] ?? null,
                    ])
                    ->withChanges(['old' => $oldAttributes, 'attributes' => $attributes])
                    ->log('Voucher forwarded to Box A.');
            }

            activity()
                ->performedOn($lockedBatch)
                ->causedBy($actor)
                ->event('forward_to_box_a')
                ->withProperties([
                    'voucher_id' => $lockedBatch->voucher_id,
                    'date_fwd_to_dc' => $data['date_fwd_to_dc'],
                    'remarks' => $data['remarks'] ?? null,
                    'from_status' => 'For Review by RMCD',
                    'to_status' => $nextStatus->name,
                ])
                ->log('Voucher forwarded to Box A.');

            return $lockedBatch->fresh()->load(VoucherCreationService::DETAIL_RELATIONSHIPS);
        });
    }

    /**
     * @param  Collection<int, GuaranteeLetter>  $letters
     * @param  array{items: list<array{guarantee_letter_id: int|string, remarks: string}>}  $data
     * @return Collection<int, array{guarantee_letter_id: int, gl_no: string, remarks: string}>
     */
    private function selectedGlCompliancesForLetters(Collection $letters, array $data): Collection
    {
        $lettersById = $letters->keyBy('id');
        $invalidIndexes = collect($data['items'])
            ->filter(fn (array $item): bool => ! $lettersById->has((int) $item['guarantee_letter_id']))
            ->keys()
            ->map(fn (int $index): string => "items.{$index}.guarantee_letter_id")
            ->all();

        if ($invalidIndexes !== []) {
            throw ValidationException::withMessages(
                collect($invalidIndexes)->mapWithKeys(fn (string $key): array => [
                    $key => 'The selected GL does not belong to this voucher.',
                ])->all(),
            );
        }

        return collect($data['items'])->map(function (array $item) use ($lettersById): array {
            $letter = $lettersById->get((int) $item['guarantee_letter_id']);

            return [
                'guarantee_letter_id' => $letter->id,
                'gl_no' => $letter->gl_no,
                'remarks' => trim($item['remarks']),
            ];
        })->values();
    }

    /**
     * @param  array{forwarded_date: string}  $data
     * @param  Collection<int, array{guarantee_letter_id: int, gl_no: string, remarks: string}>  $items
     * @return Collection<int, Compliance>
     */
    private function createVoucherCompliances(Batch $batch, ComplianceType $type, array $data, Collection $items): Collection
    {
        $returnedAt = now('Asia/Manila');
        $groupId = (string) Str::uuid();

        return $items->map(fn (array $item): Compliance => Compliance::query()->create([
            'compliance_group_id' => $groupId,
            'batch_id' => $batch->id,
            'guarantee_letter_id' => $item['guarantee_letter_id'],
            'compliance_type_id' => $type->id,
            'compliance_classification' => $data['compliance_classification'] ?? null,
            'returned_at' => $returnedAt,
            'remarks' => $item['remarks'],
        ]));
    }

    /**
     * @param  Collection<int, array{guarantee_letter_id: int, gl_no: string, remarks: string}>  $items
     * @return list<array{guarantee_letter_id: int, gl_no: string, remarks: string}>
     */
    private function selectedGlComplianceAuditProperties(Collection $items): array
    {
        return $items->values()->all();
    }
}
