<?php

namespace Database\Factories;

use App\Models\Dv;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Dv> */
class DvFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['dv_no' => fake()->bothify('DV-########')];
    }
}
