<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Batch> */
class BatchFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'voucher_id' => Voucher::factory(),
            'ors_id' => null,
            'dv_id' => null,
            'cheque_id' => null,
            'remarks' => null,
            'created_by' => User::factory(),
        ];
    }
}
