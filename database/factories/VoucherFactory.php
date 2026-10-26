<?php

namespace Database\Factories;

use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Voucher> */
class VoucherFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['voucher_no' => fake()->bothify('VOUCHER-########')];
    }
}
