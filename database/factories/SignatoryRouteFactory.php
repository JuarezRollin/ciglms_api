<?php

namespace Database\Factories;

use App\Models\GuaranteeLetter;
use App\Models\Signatory;
use App\Models\SignatoryRoute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SignatoryRoute>
 */
class SignatoryRouteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'guarantee_letter_id' => GuaranteeLetter::query()->inRandomOrder()->value('id'),
            'signatory_id' => Signatory::query()->inRandomOrder()->value('id'),
            'sequence' => 1,
            'date_sent' => null,
            'date_returned' => null,
            'remarks' => null,
        ];
    }
}
