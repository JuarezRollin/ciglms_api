<?php

namespace App\Services;

use App\Models\GlTransmittal;
use App\Models\GuaranteeLetter;
use App\Models\Status;
use App\Models\User;
use App\Support\NameInitials;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class GlTransmittalService
{
    /** @param array{category_id: int, gl_ids: list<int>} $data */
    public function create(array $data, User $actor): GlTransmittal
    {
        return GlTransmittal::query()->getConnection()->transaction(function () use ($data, $actor): GlTransmittal {
            $creator = User::query()->whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            $letters = $this->lockedLetters($data['gl_ids']);
            $this->validateNewLetters($letters);
            $endorsement = $this->status(Status::FOR_ENDORSEMENT_ID, 'For Endorsement', lockForUpdate: true);

            $transmittal = GlTransmittal::query()->create([
                'bundle_no' => $this->nextBundleNumber($creator),
                'category_id' => $data['category_id'],
                'created_by' => $creator->getKey(),
            ]);
            $transmittal->gls()->attach($this->positionedLetters($data['gl_ids']));
            foreach ($letters as $letter) {
                $letter->status()->associate($endorsement);
                $letter->save();
            }

            return $this->load($transmittal);
        });
    }

    /** @param array{category_id: int, gl_ids: list<int>} $data */
    public function update(GlTransmittal $transmittal, array $data): GlTransmittal
    {
        return $transmittal->getConnection()->transaction(function () use ($transmittal, $data): GlTransmittal {
            $transmittal = GlTransmittal::query()->whereKey($transmittal->getKey())->lockForUpdate()->firstOrFail();
            abort_if($transmittal->cancelled_at !== null, 409, 'A cancelled transmittal cannot be edited.');

            $currentIds = array_map('intval', $transmittal->gls()->pluck('guarantee_letters.id')->all());
            $selectedIds = array_map('intval', $data['gl_ids']);
            $letters = $this->lockedLetters(array_values(array_unique([...$currentIds, ...$selectedIds])));

            foreach ($letters as $letter) {
                if (in_array($letter->id, $currentIds, true)) {
                    abort_unless((int) $letter->status_id === Status::FOR_ENDORSEMENT_ID, 409, "{$letter->gl_no} has moved beyond For Endorsement.");
                    abort_if($letter->date_received_gl !== null, 409, "{$letter->gl_no} has already been received.");
                } else {
                    $this->validateNewLetters(collect([$letter]));
                }
            }

            $released = $this->status(Status::RELEASED_ID, 'Released');
            $endorsement = $this->status(Status::FOR_ENDORSEMENT_ID, 'For Endorsement');
            foreach ($letters as $letter) {
                if (in_array($letter->id, $currentIds, true) && ! in_array($letter->id, $selectedIds, true)) {
                    $letter->status()->associate($released);
                    $letter->save();
                } elseif (! in_array($letter->id, $currentIds, true)) {
                    $letter->status()->associate($endorsement);
                    $letter->save();
                }
            }

            $transmittal->update(['category_id' => $data['category_id']]);
            $transmittal->gls()->sync($this->positionedLetters($selectedIds));

            return $this->load($transmittal->refresh());
        });
    }

    public function cancel(GlTransmittal $transmittal, User $actor): GlTransmittal
    {
        return $transmittal->getConnection()->transaction(function () use ($transmittal, $actor): GlTransmittal {
            $transmittal = GlTransmittal::query()->whereKey($transmittal->getKey())->lockForUpdate()->firstOrFail();
            abort_if($transmittal->cancelled_at !== null, 409, 'This transmittal is already cancelled.');

            $letters = $this->lockedLetters($transmittal->gls()->pluck('guarantee_letters.id')->all());
            foreach ($letters as $letter) {
                abort_unless((int) $letter->status_id === Status::FOR_ENDORSEMENT_ID, 409, "{$letter->gl_no} has moved beyond For Endorsement.");
                abort_if($letter->date_received_gl !== null, 409, "{$letter->gl_no} has already been received.");
            }

            $released = $this->status(Status::RELEASED_ID, 'Released');
            foreach ($letters as $letter) {
                $letter->status()->associate($released);
                $letter->save();
            }

            $transmittal->update(['cancelled_at' => now('Asia/Manila'), 'cancelled_by' => $actor->getKey()]);

            return $this->load($transmittal->refresh());
        });
    }

    public function load(GlTransmittal $transmittal): GlTransmittal
    {
        return $transmittal->load(['category', 'creator', 'receiver', 'gls.client', 'gls.beneficiary', 'gls.serviceProvider', 'gls.signatory', 'gls.status']);
    }

    /** @param list<int>|null $glIds */
    public function receive(GlTransmittal $transmittal, User $actor, ?int $glId = null, ?array $glIds = null): GlTransmittal
    {
        return $transmittal->getConnection()->transaction(function () use ($transmittal, $actor, $glId, $glIds): GlTransmittal {
            $transmittal = GlTransmittal::query()->whereKey($transmittal->getKey())->lockForUpdate()->firstOrFail();
            abort_if($transmittal->cancelled_at !== null, 409, 'A cancelled transmittal cannot be received.');

            $ids = $transmittal->gls()->pluck('guarantee_letters.id')->all();
            $requestedIds = $glIds ?? ($glId === null ? $ids : [$glId]);
            abort_if(array_diff($requestedIds, $ids) !== [], 422, 'The selected GLs must belong to this transmittal.');

            $letters = $this->lockedLetters($requestedIds);
            $pending = $letters->filter(fn (GuaranteeLetter $letter): bool => $letter->date_received_gl === null);
            abort_if($pending->isEmpty(), 409, 'The selected GLs have already been received.');
            abort_if(($glId !== null || $glIds !== null) && $pending->count() !== count($requestedIds), 409, 'One or more selected GLs have already been received.');

            foreach ($pending as $letter) {
                abort_unless((int) $letter->status_id === Status::FOR_ENDORSEMENT_ID, 409, "{$letter->gl_no} is no longer For Endorsement.");
            }

            $awaitingSoa = $this->status(Status::AWAITING_SOA_ID, 'Awaiting SOA');
            $receivedAt = now('Asia/Manila');
            foreach ($pending as $letter) {
                $letter->date_received_gl = $receivedAt;
                $letter->received_by = $actor->getKey();
                $letter->status()->associate($awaitingSoa);
                $letter->save();
            }

            if (! $transmittal->gls()->whereNull('date_received_gl')->exists()) {
                $transmittal->update(['received_by' => $actor->getKey()]);
            }

            return $this->load($transmittal->refresh());
        });
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{position: int}>
     */
    private function positionedLetters(array $ids): array
    {
        $positions = [];
        foreach ($ids as $index => $id) {
            $positions[$id] = ['position' => $index + 1];
        }

        return $positions;
    }

    /** @param list<int> $ids
     * @return Collection<int, GuaranteeLetter>
     */
    private function lockedLetters(array $ids): Collection
    {
        $letters = GuaranteeLetter::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();
        if ($letters->count() !== count($ids)) {
            throw ValidationException::withMessages(['gl_ids' => 'One or more selected Guarantee Letters are unavailable.']);
        }

        return $letters;
    }

    /** @param Collection<int, GuaranteeLetter> $letters */
    private function validateNewLetters(Collection $letters): void
    {
        foreach ($letters as $letter) {
            if ((int) $letter->status_id !== Status::RELEASED_ID) {
                throw ValidationException::withMessages(['gl_ids' => "{$letter->gl_no} is no longer Released."]);
            }
            if ($letter->transmittals()->whereNull('gl_transmittals.cancelled_at')->exists()) {
                throw ValidationException::withMessages(['gl_ids' => "{$letter->gl_no} is already in an active transmittal."]);
            }
        }
    }

    private function status(int $id, string $name, bool $lockForUpdate = false): Status
    {
        $query = Status::query()->whereKey($id)->where('is_active', true);
        $status = ($lockForUpdate ? $query->lockForUpdate() : $query)->first();
        if ($status === null) {
            throw ValidationException::withMessages(['gl_ids' => "The {$name} status is unavailable."]);
        }

        return $status;
    }

    private function nextBundleNumber(User $creator): string
    {
        $initials = $creator->initials ?: NameInitials::fromName($creator->name);
        if ($initials === '') {
            throw ValidationException::withMessages(['bundle_no' => 'A bundle number cannot be generated because the creator has no name.']);
        }

        $today = now('Asia/Manila');
        $prefix = $initials.'-'.$today->format('m-d').'-';
        $lastSequence = GlTransmittal::query()
            ->whereBetween('created_at', [$today->copy()->startOfDay()->utc(), $today->copy()->endOfDay()->utc()])
            ->pluck('bundle_no')
            ->map(function (string $number) use ($prefix): int {
                return preg_match('/^'.preg_quote($prefix, '/').'(\d{4})$/', $number, $matches) === 1 ? (int) $matches[1] : 0;
            })->max() ?? 0;

        return sprintf('%s%04d', $prefix, $lastSequence + 1);
    }
}
