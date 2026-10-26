<?php

namespace Database\Seeders;

use App\Models\GuaranteeLetter;
use App\Services\SignatoryRoutePlanner;
use Illuminate\Database\Seeder;

class SignatoryRouteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(SignatoryRoutePlanner $routePlanner): void
    {
        GuaranteeLetter::query()
            ->whereDoesntHave('signatoryRoutes')
            ->with('signatory')
            ->lazyById()
            ->each(fn (GuaranteeLetter $letter) => $routePlanner->createFor($letter));
    }
}
