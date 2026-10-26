<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\GuaranteeLetter;
use App\Models\Status;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class VoucherCreationService
{
    /** @var list<string> */
    public const DETAIL_RELATIONSHIPS = [
        'voucher',
        'reviewer',
        'ors',
        'dv',
        'cheque',
        'latestResolvedCompliance.complianceType',
        'gls.client',
        'gls.beneficiary',
        'gls.branch.serviceProvider',
        'gls.fundSource',
        'gls.assistanceType',
        'gls.category',
        'gls.status',
    ];

    /** @var list<string> */
    public const LIST_RELATIONSHIPS = [
        'voucher',
        'reviewer',
        'gls.branch.serviceProvider',
        'gls.fundSource',
        'gls.status',
    ];

    /**
     * @param  array{gl_ids: list<int>, fund_source_id: int, reviewer_id: int, remarks?: string|null}  $data
     */
    public function create(array $data, User $actor): Batch
    {
        return Batch::query()->getConnection()->transaction(function () use ($data, $actor): Batch {
            // Serializing creations per user keeps the daily sequence stable even when
            // the same user submits two voucher creations concurrently.
            $creator = User::query()->whereKey($actor->getKey())->lockForUpdate()->firstOrFail();

            $letters = GuaranteeLetter::query()
                ->with(['status', 'branch', 'fundSource'])
                ->whereKey($data['gl_ids'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($letters->count() !== count($data['gl_ids'])) {
                throw ValidationException::withMessages([
                    'gl_ids' => 'One or more selected Guarantee Letters are unavailable.',
                ]);
            }

            $nextStatus = Status::query()
                ->where('name', 'For Review by RMCD')
                ->where('is_active', true)
                ->first();

            if ($nextStatus === null) {
                throw ValidationException::withMessages([
                    'gl_ids' => 'The For Review by RMCD status is unavailable. Contact your administrator.',
                ]);
            }

            $this->validateLetters($letters, $data['fund_source_id']);

            $voucher = Voucher::query()->create(['voucher_no' => $this->nextVoucherNumber($creator)]);
            $batch = Batch::query()->create([
                'voucher_id' => $voucher->id,
                'remarks' => $data['remarks'] ?? null,
                'created_by' => $actor->getKey(),
                'reviewer_id' => $data['reviewer_id'],
            ]);

            $batch->syncGls($data['gl_ids']);

            foreach ($letters as $letter) {
                $this->advanceLetter($letter, $nextStatus, $data['fund_source_id'], $batch, $voucher, $actor);
            }

            return $batch->fresh()->load(self::DETAIL_RELATIONSHIPS);
        });
    }

    private function nextVoucherNumber(User $actor): string
    {
        // Use the Philippine calendar day for the number and daily sequence.
        $createdOn = now('Asia/Manila');
        $prefix = sprintf('%s-%s-', $this->creatorInitials($actor), $createdOn->format('m-d'));
        $dayStart = $createdOn->copy()->startOfDay()->utc();
        $dayEnd = $createdOn->copy()->endOfDay()->utc();

        // Find this creator's highest valid sequence for today.
        $lastSequence = Batch::query()
            ->where('created_by', $actor->getKey())
            ->whereBetween('created_at', [$dayStart, $dayEnd])
            ->with('voucher:id,voucher_no')
            ->get()
            ->map(function (Batch $batch) use ($prefix): int {
                $voucherNo = $batch->voucher?->voucher_no;

                if (! is_string($voucherNo) || preg_match('/^'.preg_quote($prefix, '/').'(\d{4})$/', $voucherNo, $matches) !== 1) {
                    return 0;
                }

                return (int) $matches[1];
            })
            ->max() ?? 0;

        // Advance the sequence and keep its four-digit format.
        return sprintf('%s%04d', $prefix, $lastSequence + 1);
    }

    private function creatorInitials(User $actor): string
    {
        // Build initials from every name part (e.g. Frans Audrey S Sandicon → FASS).
        preg_match_all('/[\p{L}\p{N}]+/u', $actor->name, $matches);

        $initials = collect($matches[0])
            ->map(fn (string $namePart): string => mb_strtoupper(mb_substr($namePart, 0, 1)))
            ->join('');

        if ($initials === '') {
            throw ValidationException::withMessages([
                'voucher_no' => 'A voucher number cannot be generated because the creator has no name.',
            ]);
        }

        return $initials;
    }

    /**
     * @param  Collection<int, GuaranteeLetter>  $letters
     */
    private function validateLetters(Collection $letters, int $fundSourceId): void
    {
        $invalidStatus = $letters->first(fn (GuaranteeLetter $letter): bool => $letter->status?->name !== 'For Creation');
        if ($invalidStatus !== null) {
            throw ValidationException::withMessages([
                'gl_ids' => "Guarantee Letter {$invalidStatus->gl_no} is no longer ready for voucher creation.",
            ]);
        }

        $batchedLetter = $letters->first(fn (GuaranteeLetter $letter): bool => $letter->batches()->exists());
        if ($batchedLetter !== null) {
            throw ValidationException::withMessages([
                'gl_ids' => "Guarantee Letter {$batchedLetter->gl_no} is already included in a batch.",
            ]);
        }

        $openComplianceLetter = $letters->first(fn (GuaranteeLetter $letter): bool => $letter->hasOpenCompliance());
        if ($openComplianceLetter !== null) {
            throw ValidationException::withMessages([
                'gl_ids' => "Resolve the open compliance for {$openComplianceLetter->gl_no} before creating a voucher.",
            ]);
        }

        if ($letters->pluck('branch_id')->unique()->count() !== 1) {
            throw ValidationException::withMessages([
                'gl_ids' => 'Selected Guarantee Letters must belong to the same provider branch.',
            ]);
        }

        $incompatibleFundLetter = $letters->first(
            fn (GuaranteeLetter $letter): bool => $letter->fund_source_id !== null && $letter->fund_source_id !== $fundSourceId,
        );
        if ($incompatibleFundLetter !== null) {
            throw ValidationException::withMessages([
                'fund_source_id' => "The selected fund source does not match {$incompatibleFundLetter->gl_no}.",
            ]);
        }
    }

    private function advanceLetter(
        GuaranteeLetter $letter,
        Status $nextStatus,
        int $fundSourceId,
        Batch $batch,
        Voucher $voucher,
        User $actor,
    ): void {
        $previousStatus = $letter->status?->name;
        if ($letter->fund_source_id === null) {
            $letter->fund_source_id = $fundSourceId;
        }
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
            ->event('create_voucher')
            ->withProperties([
                'batch_id' => $batch->id,
                'voucher_id' => $voucher->id,
                'voucher_no' => $voucher->voucher_no,
                'from_status' => $previousStatus,
                'to_status' => $nextStatus->name,
            ])
            ->withChanges(['old' => $oldAttributes, 'attributes' => $attributes])
            ->log('Voucher created and Guarantee Letter moved to For Review by RMCD.');
    }
}
