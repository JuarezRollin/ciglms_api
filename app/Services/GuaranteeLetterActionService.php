<?php

namespace App\Services;

use App\Models\Compliance;
use App\Models\ComplianceType;
use App\Models\GuaranteeLetter;
use App\Models\Status;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Throwable;

class GuaranteeLetterActionService
{
    public function __construct(private GuaranteeLetterDocumentService $documents) {}

    /**
     * @param  array{action: string, date_received_soa?: string, obligation_amount?: numeric-string|float|int|null, date_pulled_out?: string, forwarded_date?: string, remarks?: string|null, supporting_documents?: list<UploadedFile>}  $data
     */
    public function processAction(GuaranteeLetter $guaranteeLetter, array $data, User $actor): GuaranteeLetter
    {
        if ($data['action'] === 'update_obligation_amount') {
            abort_unless($actor->hasAnyRole(['admin', 'encoder', 'reviewer']), 403);
        } else {
            $isCreationAction = in_array($data['action'], ['approve_gl', 'release_gl'], true);
            abort_unless(
                $isCreationAction
                    ? $actor->hasRole('admin')
                    : $actor->hasAnyRole(['admin', 'encoder'])
                        || ($data['action'] === 'return_for_compliance' && $actor->hasRole('voucher creation')),
                403,
            );
        }

        $storedFiles = [];
        try {
            return $guaranteeLetter->getConnection()->transaction(function () use ($guaranteeLetter, $data, $actor, &$storedFiles): GuaranteeLetter {
                $letter = $guaranteeLetter->newQuery()->whereKey($guaranteeLetter->getKey())->lockForUpdate()->firstOrFail();

                if ($data['action'] !== 'update_obligation_amount') {
                    abort_if($letter->batches()->exists(), 409, 'This guarantee letter is already included in a batch.');
                    abort_if($letter->hasOpenCompliance(), 409, 'Resolve the open compliance before recording this action.');
                }

                $previousStatus = $letter->status?->name;
                $description = match ($data['action']) {
                    'approve_gl' => $this->approveGl($letter, $actor),
                    'release_gl' => $this->releaseGl($letter, $actor),
                    'record_soa_received' => $this->recordSoaReceived($letter, $data, $actor),
                    'record_pullout' => $this->recordPullout($letter, $data, $actor),
                    'return_for_compliance' => $this->returnForCompliance($letter, $data, $actor),
                    'update_obligation_amount' => $this->updateObligationAmount($letter, $data),
                    default => throw ValidationException::withMessages(['action' => 'This action is not supported.']),
                };

                $documentIds = $data['action'] === 'record_soa_received'
                    ? $this->documents->store($letter, $data['supporting_documents'] ?? [], $actor, $storedFiles)
                    : [];

                $attributes = $letter->getDirty();
                $oldAttributes = array_intersect_key($letter->getRawOriginal(), $attributes);

                // Record one action entry instead of an additional automatic model-update entry.
                $letter->disableLogging();
                try {
                    $letter->save();
                } finally {
                    $letter->enableLogging();
                }

                activity()
                    ->performedOn($letter)
                    ->causedBy($actor)
                    ->event($data['action'])
                    ->withProperties([
                        'from_status' => $previousStatus,
                        'to_status' => $letter->status->name,
                        'forwarded_date' => $data['forwarded_date'] ?? null,
                        'remarks' => $data['remarks'] ?? null,
                        ...($documentIds !== [] ? ['supporting_document_ids' => $documentIds, 'supporting_documents_count' => count($documentIds)] : []),
                        ...($data['action'] === 'update_obligation_amount' ? [
                            'old_attributes' => $oldAttributes,
                            'new_attributes' => $attributes,
                        ] : []),
                    ])
                    ->withChanges(['old' => $oldAttributes, 'attributes' => $attributes])
                    ->log($description);

                return $letter;
            });
        } catch (Throwable $exception) {
            $this->documents->cleanup($storedFiles);
            throw $exception;
        }
    }

    private function approveGl(GuaranteeLetter $letter, User $actor): string
    {
        abort_unless((int) $letter->status_id === Status::FOR_REVIEW_BY_CRD_ID, 409, 'Only GLs for review by CRD can be approved.');

        $reviewedAt = now('Asia/Manila');
        $letter->reviewed_by_creation = $actor->getKey();
        $letter->date_reviewed_by_creation = $reviewedAt;
        $letter->approved_by = $actor->getKey();
        $letter->date_approved = $reviewedAt;
        $this->setStatus($letter, 'Approved');

        return 'GL approved by GLC staff.';
    }

    private function releaseGl(GuaranteeLetter $letter, User $actor): string
    {
        abort_unless((int) $letter->status_id === Status::APPROVED_ID, 409, 'Only approved GLs can be released.');

        $letter->released_by = $actor->getKey();
        $letter->date_released = now('Asia/Manila');
        $this->setStatus($letter, 'Released');

        return 'GL released by GLC staff.';
    }

    private function returnForCompliance(GuaranteeLetter $letter, array $data, User $actor): string
    {
        abort_unless($actor->hasAnyRole(['admin', 'voucher creation']), 403);
        $this->requireStatus($letter, 'For Creation');
        $type = ComplianceType::query()->where('name', 'Voucher Creation to Encoder')->where('is_active', true)->firstOrFail();
        $status = Status::query()->where('name', 'For Compliance')->where('is_active', true)->firstOrFail();

        Compliance::query()->create([
            'guarantee_letter_id' => $letter->id,
            'compliance_type_id' => $type->id,
            'returned_at' => now('Asia/Manila'),
            'remarks' => trim($data['remarks']),
        ]);
        $letter->status()->associate($status);

        return 'GL returned to Encoder for compliance.';
    }

    /** @param array{date_received_soa: string, obligation_amount?: numeric-string|float|int|null} $data */
    private function recordSoaReceived(GuaranteeLetter $letter, array $data, User $actor): string
    {
        $this->requireStatus($letter, 'Awaiting SOA');
        abort_if($letter->date_received_soa !== null, 409, 'SOA receipt has already been recorded.');

        if (($data['obligation_amount'] ?? null) !== null) {
            abort_unless($actor->hasAnyRole(['admin', 'encoder', 'reviewer']), 403);
        }

        if ($letter->date_received_gl !== null && $data['date_received_soa'] < $letter->date_received_gl->toDateString()) {
            throw ValidationException::withMessages(['date_received_soa' => 'The SOA receipt date cannot be before the GL receipt date.']);
        }

        $letter->date_received_soa = $data['date_received_soa'];
        $letter->obligation_amount = $data['obligation_amount'] ?? null;
        $letter->soa_received_by = $actor->getKey();
        $this->setStatus($letter, 'For Pullout');

        return 'SOA receipt recorded';
    }

    /** @param array{obligation_amount: numeric-string|float|int} $data */
    private function updateObligationAmount(GuaranteeLetter $letter, array $data): string
    {
        $currentAmount = $letter->obligation_amount === null ? null : number_format((float) $letter->obligation_amount, 2, '.', '');
        $newAmount = number_format((float) $data['obligation_amount'], 2, '.', '');

        if ($currentAmount === $newAmount) {
            throw ValidationException::withMessages([
                'obligation_amount' => 'Enter an amount different from the current obligation amount.',
            ]);
        }

        $letter->obligation_amount = $data['obligation_amount'];

        return 'Obligation amount updated';
    }

    /** @param array{date_pulled_out: string} $data */
    private function recordPullout(GuaranteeLetter $letter, array $data, User $actor): string
    {
        $this->requireStatus($letter, 'For Pullout');
        abort_if($letter->date_pulled_out !== null, 409, 'GL pullout has already been recorded.');
        abort_if($letter->date_received_soa === null, 409, 'Record SOA receipt before recording GL pullout.');

        if ($data['date_pulled_out'] < $letter->date_received_soa->toDateString()) {
            throw ValidationException::withMessages(['date_pulled_out' => 'The pullout date cannot be before the SOA receipt date.']);
        }

        $letter->date_pulled_out = $data['date_pulled_out'];
        $letter->pulled_out_by = $actor->getKey();
        $this->setStatus($letter, 'For Creation');

        return 'GL pullout recorded';
    }

    private function requireStatus(GuaranteeLetter $letter, string $status): void
    {
        abort_unless($letter->status?->name === $status, 409, 'The guarantee letter status has changed or does not allow this action. Refresh the record and try again.');
    }

    private function setStatus(GuaranteeLetter $letter, string $name): void
    {
        $status = Status::query()->where('name', $name)->where('is_active', true)->first();

        if ($status === null) {
            throw ValidationException::withMessages(['action' => "The {$name} status is unavailable. Contact your administrator."]);
        }

        $letter->status()->associate($status);
    }
}
