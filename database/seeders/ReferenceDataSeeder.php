<?php

namespace Database\Seeders;

use App\Models\AssistanceType;
use App\Models\Beneficiary;
use App\Models\Branch;
use App\Models\Client;
use App\Models\FundSource;
use App\Models\Office;
use App\Models\Relationship;
use App\Models\ServiceProvider;
use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        Office::query()->firstOrCreate(['name' => 'Central Office'], ['region_id' => 20]);

        foreach (['Self', 'Spouse', 'Child', 'Parent', 'Sibling'] as $name) {
            Relationship::query()->firstOrCreate(['name' => $name]);
        }

        foreach (['Medical', 'Funeral', 'Transportation', 'Food'] as $name) {
            AssistanceType::query()->updateOrCreate(['name' => $name], ['is_active' => true]);
        }

        foreach ([
            ['name' => 'AICS', 'amount' => '5000000.00'],
            ['name' => 'AKAP', 'amount' => '3000000.00'],
        ] as $attributes) {
            FundSource::query()->updateOrCreate(
                ['name' => $attributes['name']],
                ['amount' => $attributes['amount'], 'is_active' => true],
            );
        }

        foreach ([
            ['name' => 'Philippine General Hospital', 'address' => 'Taft Avenue, Manila'],
            ['name' => 'East Avenue Medical Center', 'address' => 'Diliman, Quezon City'],
        ] as $attributes) {
            $provider = ServiceProvider::query()->updateOrCreate(
                ['name' => $attributes['name']],
                $attributes + [
                    'region_id' => 20,
                    'salutation' => 'Dear Sir/Madam',
                    'addressee' => 'The Medical Center Chief',
                    'designation' => 'Medical Center Chief',
                    'is_active' => true,
                ],
            );
            Branch::query()->updateOrCreate(
                ['service_provider_id' => $provider->id, 'name' => 'Main Branch'],
                ['address' => $attributes['address'], 'is_active' => true],
            );
        }

        foreach ([
            ['client' => 'Ana Reyes', 'beneficiary' => 'Pedro Reyes', 'sex' => 2],
            ['client' => 'Jose Garcia', 'beneficiary' => 'Maria Garcia', 'sex' => 1],
            ['client' => 'Liza Mendoza', 'beneficiary' => 'Carlo Mendoza', 'sex' => 2],
            ['client' => 'Ramon Flores', 'beneficiary' => 'Elena Flores', 'sex' => 1],
            ['client' => 'Grace Bautista', 'beneficiary' => 'Noel Bautista', 'sex' => 2],
            ['client' => 'Mario Aquino', 'beneficiary' => 'Rosa Aquino', 'sex' => 1],
            ['client' => 'Teresa Ramos', 'beneficiary' => 'Luis Ramos', 'sex' => 2],
            ['client' => 'Benito Cruz', 'beneficiary' => 'Celia Cruz', 'sex' => 1],
            ['client' => 'Nora Castillo', 'beneficiary' => 'Dante Castillo', 'sex' => 2],
            ['client' => 'Paolo Navarro', 'beneficiary' => 'Mila Navarro', 'sex' => 1],
        ] as $person) {
            Client::query()->updateOrCreate(
                ['full_name' => $person['client']],
                ['sex' => $person['sex'], 'address' => 'Metro Manila'],
            );
            Beneficiary::query()->firstOrCreate(['full_name' => $person['beneficiary']]);
        }
    }
}
