<?php

namespace Tests\Feature;

use App\Http\Requests\ServiceProviderRequest;
use App\Http\Resources\ServiceProviderResource;
use App\Models\ServiceProvider;
use Database\Seeders\ServiceProviderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Tests\TestCase;

class ServiceProviderSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_requires_central_office_region(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Import PSGC data before seeding Central Office service providers.');

        $this->seed(ServiceProviderSeeder::class);
    }

    public function test_workbook_seeds_every_row_and_preserves_duplicate_names(): void
    {
        $this->artisan('psgc:import-4q25', ['--chunk' => 500])->assertSuccessful();
        $this->seed(ServiceProviderSeeder::class);

        $this->assertDatabaseCount('service_providers', 544);
        $this->assertSame(2, ServiceProvider::query()->where('name', 'SOUTHHILL MEDICAL SPECIALISTS INC.')->count());
        $this->assertSame(512, ServiceProvider::query()->where('is_active', true)->count());
        $this->assertSame(55, ServiceProvider::query()->where('moa', true)->count());
        $this->assertSame(544, ServiceProvider::query()->where('region_id', 20)->count());

        $provider = ServiceProvider::query()->where('source_row', 2)->firstOrFail();
        $this->assertSame('ACE MEDICAL CENTER SARIAYA INC.', $provider->name);
        $this->assertSame('HOSPITAL', $provider->category);
        $this->assertSame('IV-CALABAZON', $provider->jurisdiction);
        $this->assertSame(20, $provider->region_id);
        $this->assertFalse($provider->moa);
        $this->assertTrue($provider->is_active);
        $this->assertNull(ServiceProvider::query()->where('source_row', 2)->value('designation'));
        $this->assertTrue(ServiceProvider::query()->whereNotNull('email')->get()->contains(
            fn (ServiceProvider $record): bool => mb_strlen($record->email) > 255,
        ));

        $provider->update(['address' => 'Changed address']);
        $this->seed(ServiceProviderSeeder::class);

        $this->assertDatabaseCount('service_providers', 544);
        $this->assertSame('National Hi-Way, Brgy. Gibanga, Sariaya Quezon', $provider->fresh()->address);
    }

    public function test_service_provider_request_and_resource_support_workbook_fields(): void
    {
        $request = new ServiceProviderRequest;
        $this->assertTrue(Validator::make([
            'name' => 'Repeated provider name',
            'address' => 'Sample address',
            'category' => 'HOSPITAL',
            'email' => "first@example.com\nsecond@example.com",
            'moa' => true,
        ], $request->rules())->passes());

        $resource = new ServiceProviderResource(new ServiceProvider([
            'name' => 'Sample provider',
            'address' => 'Sample address',
            'category' => 'HOSPITAL',
            'business_name' => 'Sample business',
            'moa' => true,
        ]));
        $data = $resource->toArray(new Request);

        $this->assertSame('HOSPITAL', $data['category']);
        $this->assertSame('Sample business', $data['business_name']);
        $this->assertTrue($data['moa']);
    }
}
