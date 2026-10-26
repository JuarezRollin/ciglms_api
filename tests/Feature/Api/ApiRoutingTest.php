<?php

namespace Tests\Feature\Api;

use App\Models\PsgcRegion;
use App\Models\ServiceProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ApiRoutingTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(), 'api');
    }

    public function test_api_resource_endpoint_creates_and_returns_json(): void
    {
        $response = $this->postJson('/api/v1/relationships', [
            'name' => 'Parent',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Parent');
        $this->assertDatabaseHas('relationships', ['name' => 'Parent']);
    }

    public function test_status_endpoint_updates_is_active_without_deleting_the_model(): void
    {
        $region = PsgcRegion::query()->create([
            'code_correspondence' => '000000020', 'name' => 'Central Office', 'code' => '0000000020',
        ]);
        $serviceProvider = ServiceProvider::query()->create([
            'region_id' => $region->reg_id,
            'name' => 'Sample Provider',
            'address' => 'Sample Address',
            'salutation' => 'Dear Sir/Madam',
            'addressee' => 'Sample Addressee',
            'designation' => 'Administrator',
        ]);

        $response = $this->patchJson("/api/v1/service-providers/{$serviceProvider->id}/status", [
            'is_active' => false,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
        $this->assertDatabaseHas('service_providers', [
            'id' => $serviceProvider->id,
            'is_active' => false,
        ]);
    }

    public function test_delete_endpoint_is_not_registered(): void
    {
        $response = $this->deleteJson('/api/v1/relationships/1');

        $response->assertMethodNotAllowed();
    }
}
