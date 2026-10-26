<?php

namespace Database\Factories;

use App\Models\Ors;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Ors> */
class OrsFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['ors_no' => fake()->bothify('ORS-########')];
    }
}
