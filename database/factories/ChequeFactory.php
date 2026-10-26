<?php

namespace Database\Factories;

use App\Models\Cheque;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Cheque> */
class ChequeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'cheque_no' => fake()->bothify('CHEQUE-########'),
            'cheque_date' => fake()->date(),
        ];
    }
}
