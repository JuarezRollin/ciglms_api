<?php

namespace Tests\Feature;

use App\Http\Requests\GuaranteeLetterRequest;
use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.defaults.guard' => 'web']);
    }

    public function test_categories_are_seeded_without_duplicates(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(CategorySeeder::class);

        $this->assertSame([
            'Hospital Bill', 'Pharmaceutical', 'Medical and Assistive Devices',
            'Treatment and Therapy', 'Diagnostics and Procedures', 'Funeral',
        ], Category::orderBy('id')->pluck('name')->all());
    }

    public function test_categories_can_be_listed_created_and_updated(): void
    {
        $this->withoutMiddleware(Authenticate::class);
        $this->seed(CategorySeeder::class);

        $this->getJson('/api/v1/categories')->assertOk()->assertJsonCount(6, 'data');
        $id = $this->postJson('/api/v1/categories', ['name' => 'Other'])
            ->assertCreated()->json('data.id');
        $this->getJson('/api/v1/categories/'.$id)->assertOk()->assertJsonPath('data.name', 'Other');
        $this->putJson('/api/v1/categories/'.$id, ['name' => 'Updated'])
            ->assertOk()->assertJsonPath('data.name', 'Updated');
        $this->postJson('/api/v1/categories', ['name' => 'Hospital Bill'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_guarantee_letter_category_is_required_and_must_exist(): void
    {
        $this->seed(CategorySeeder::class);
        $rules = ['category_id' => (new GuaranteeLetterRequest)->rules()['category_id']];

        $this->assertTrue(Validator::make(['category_id' => Category::first()->id], $rules)->passes());
        $this->assertTrue(Validator::make(['category_id' => null], $rules)->fails());
        $this->assertTrue(Validator::make([], $rules)->fails());
        $this->assertTrue(Validator::make(['category_id' => 999999], $rules)->fails());
    }
}
