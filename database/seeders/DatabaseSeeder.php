<?php

namespace Database\Seeders;

use App\Models\Office;
use App\Models\PsgcRegion;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! PsgcRegion::query()->whereKey(20)->exists()
            && Artisan::call('psgc:import-4q25', ['--chunk' => 500]) !== 0) {
            throw new RuntimeException('PSGC data could not be imported before seeding.');
        }

        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            OtherUserSeeder::class,
            StatusSeeder::class,
            CategorySeeder::class,
            ComplianceTypeSeeder::class,
            ReferenceDataSeeder::class,
            SignatorySeeder::class,
        ]);

        User::query()->whereNull('office_id')->update([
            'office_id' => Office::query()->where('name', 'Central Office')->value('id'),
        ]);

        $this->call([
            ServiceProviderSeeder::class,
            SwoSeeder::class,
            TransactionSeeder::class,
            SignatoryRouteSeeder::class,
        ]);
    }
}
