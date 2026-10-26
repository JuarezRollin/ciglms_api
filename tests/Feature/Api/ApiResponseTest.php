<?php

namespace Tests\Feature\Api;

use App\Models\PsgcRegion;
use App\Models\ServiceProvider;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(), 'api');
    }

    public function test_paginated_resources_have_stable_envelope_and_query_controls(): void
    {
        $region = PsgcRegion::query()->create([
            'code_correspondence' => '000000020', 'name' => 'Central Office', 'code' => '0000000020',
        ]);
        foreach ([
            ['name' => 'Zulu Medical Center', 'is_active' => true],
            ['name' => 'Alpha Hospital', 'is_active' => true],
            ['name' => 'Inactive Clinic', 'is_active' => false],
        ] as $attributes) {
            ServiceProvider::query()->create($attributes + [
                'region_id' => $region->reg_id,
                'address' => 'Sample Address',
                'salutation' => 'Dear Sir/Madam',
                'addressee' => 'Administrator',
                'designation' => 'Director',
            ]);
        }

        $response = $this->getJson('/api/v1/service-providers?search=a&filter[is_active]=true&sort=name&direction=asc&per_page=1');

        $response
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Alpha Hospital')
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_validation_errors_use_the_standard_error_envelope(): void
    {
        $this->postJson('/api/v1/relationships', [])
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Validation failed.',
                'errors' => ['name' => ['The name field is required.']],
            ]);
    }

    public function test_invalid_query_field_returns_a_validation_error(): void
    {
        $this->getJson('/api/v1/relationships?sort=unknown')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure(['errors' => ['sort']]);
    }

    public function test_not_found_errors_use_the_standard_error_envelope(): void
    {
        $this->getJson('/api/v1/relationships/999999')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Resource not found.']);
    }

    public function test_authorization_errors_use_the_standard_error_envelope(): void
    {
        Route::get('/api/v1/test-forbidden', fn () => throw new AuthorizationException);

        $this->getJson('/api/v1/test-forbidden')
            ->assertForbidden()
            ->assertExactJson(['message' => 'Forbidden.']);
    }

    public function test_authentication_errors_use_the_standard_error_envelope(): void
    {
        Route::get('/api/v1/test-authenticated', fn () => response()->json([]))->middleware('auth');
        auth()->forgetGuards();

        $this->getJson('/api/v1/test-authenticated')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_server_errors_do_not_expose_internal_details(): void
    {
        Route::get('/api/v1/test-server-error', fn () => throw new RuntimeException('Sensitive detail'));

        $this->getJson('/api/v1/test-server-error')
            ->assertInternalServerError()
            ->assertExactJson(['message' => 'Server error.']);
    }
}
