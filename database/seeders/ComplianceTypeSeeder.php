<?php

namespace Database\Seeders;

use App\Models\ComplianceType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ComplianceTypeSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            'GL Processing to GL Creation',
            'Voucher Creation to Encoder',
            'Reviewer to Voucher Creation',
            'Box A Reviewer to Reviewer',
            'Budget to Compliance Officer',
            'Accounting to Compliance Officer',
            'Cash to Reviewer',
        ] as $name) {
            ComplianceType::query()->firstOrCreate(['name' => $name]);
        }
    }
}
