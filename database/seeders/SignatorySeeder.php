<?php

namespace Database\Seeders;

use App\Models\Signatory;
use Illuminate\Database\Seeder;

class SignatorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            ['Sharon A. Pattugalan', 'Social Welfare Officer II', '0.00', '10000.00'],
            ['Maria Gracia O. Tomniak', 'Social Welfare Officer II', '0.00', '10000.00'],
            ['Ma. Lilian A Malig-On', 'Social Welfare Officer III', '0.00', '10000.00'],
            ['Lea B. Bangalan', 'Director III', '10001.00', '150000.00'],
            ['Edwin S. Morata', 'Director IV', '10001.00', '150000.00'],
            ['Ada A. Colico', 'Assistant Secretary for Statutory Programs', '150001.00', '300000.00'],
            ['Monina Josefina H. Romualdez', 'Undersecretary for Operations', '300001.00', '500000.00'],
            ['Rex Gatchalian', 'Secretary', '500001.00', '9999999999999.99'],
        ] as [$name, $designation, $minimumAmount, $maximumAmount]) {
            Signatory::query()->updateOrCreate(
                ['name' => $name],
                [
                    'designation' => $designation,
                    'minimum_amount' => $minimumAmount,
                    'maximum_amount' => $maximumAmount,
                    'is_active' => true,
                ],
            );
        }
    }
}
