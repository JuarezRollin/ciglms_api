<?php

namespace App\Services;

use App\Models\GuaranteeLetter;
use App\Models\Signatory;
use Illuminate\Support\Collection;

class SignatoryRoutePlanner
{
    private const MINIMUM_ROUTED_AMOUNT = 150001;

    private const INTERMEDIATE_SIGNATORIES = [
        'Edwin S. Morata',
        'Ada A. Colico',
        'Monina Josefina H. Romualdez',
    ];

    /** @return Collection<int, Signatory> */
    public function signatoriesFor(GuaranteeLetter $letter): Collection
    {
        return $this->signatoriesForSelection($letter->gl_amount, $letter->signatory);
    }

    /** @return Collection<int, Signatory> */
    public function signatoriesForSelection(string|int|float $amount, Signatory $finalSignatory): Collection
    {
        if ((float) $amount < self::MINIMUM_ROUTED_AMOUNT) {
            return collect();
        }

        $intermediateSignatories = Signatory::query()
            ->whereIn('name', self::INTERMEDIATE_SIGNATORIES)
            ->where('minimum_amount', '<', $finalSignatory->minimum_amount)
            ->get()
            ->keyBy('name');

        return collect(self::INTERMEDIATE_SIGNATORIES)
            ->map(fn (string $name): ?Signatory => $intermediateSignatories->get($name))
            ->filter()
            ->push($finalSignatory)
            ->values();
    }

    public function createFor(GuaranteeLetter $letter): void
    {
        foreach ($this->signatoriesFor($letter) as $index => $signatory) {
            $letter->signatoryRoutes()->create([
                'signatory_id' => $signatory->id,
                'sequence' => $index + 1,
            ]);
        }
    }
}
