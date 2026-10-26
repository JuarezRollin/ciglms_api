<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Hospital Bill',
            'Pharmaceutical',
            'Medical and Assistive Devices',
            'Treatment and Therapy',
            'Diagnostics and Procedures',
            'Funeral',
        ];

        foreach ($categories as $name) {
            Category::firstOrCreate(['name' => $name]);
        }
    }
}
