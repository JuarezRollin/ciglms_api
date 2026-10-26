<?php

namespace Tests\Feature;

use App\Models\GuaranteeLetter;
use App\Models\Signatory;
use App\Models\SignatoryRoute;
use Database\Seeders\SignatoryRouteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SignatoryRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_routes_belong_to_a_letter_and_signatory_and_allow_pending_dates(): void
    {
        $this->seed();
        $letter = GuaranteeLetter::query()->firstOrFail();
        $signatory = Signatory::query()->where('name', 'Edwin S. Morata')->firstOrFail();

        foreach (['guarantee_letter_id', 'signatory_id', 'sequence', 'date_sent', 'date_returned', 'remarks'] as $column) {
            $this->assertTrue(Schema::hasColumn('signatory_routes', $column));
        }

        $route = $letter->signatoryRoutes()->create([
            'signatory_id' => $signatory->id,
            'sequence' => 1,
            'remarks' => 'Pending dispatch',
        ]);

        $this->assertSame($letter->id, $route->guaranteeLetter->id);
        $this->assertSame($signatory->id, $route->signatory->id);
        $this->assertNull($route->date_sent);
        $this->assertNull($route->date_returned);
        $this->assertSame('Pending dispatch', $route->remarks);
        $this->assertDatabaseHas('signatory_routes', [
            'id' => $route->id,
            'guarantee_letter_id' => $letter->id,
            'signatory_id' => $signatory->id,
            'remarks' => 'Pending dispatch',
        ]);
    }

    public function test_creating_a_gl_for_three_hundred_thousand_one_adds_each_signatory_in_route_order(): void
    {
        $this->seed();
        $sample = GuaranteeLetter::query()->firstOrFail();
        $this->actingAs($sample->creator, 'api');
        $finalSignatory = Signatory::query()->where('name', 'Monina Josefina H. Romualdez')->firstOrFail();
        $payload = $sample->only([
            'client_id', 'beneficiary_id', 'address', 'relationship_id',
            'service_provider_id', 'assistance_type_id',
            'gl_validity', 'swo_id', 'category_id',
        ]);
        $payload['gl_date'] = '2026-09-20';
        $payload['gl_amount'] = 300001;
        $payload['signatory_id'] = $finalSignatory->id;

        $response = $this->postJson('/api/v1/guarantee-letters', $payload)->assertCreated();
        $letter = GuaranteeLetter::query()->findOrFail($response->json('data.id'));

        $this->assertSame($finalSignatory->id, $letter->signatory_id);
        $this->assertSame(
            ['Edwin S. Morata', 'Ada A. Colico', 'Monina Josefina H. Romualdez'],
            $letter->signatoryRoutes()->with('signatory')->get()->pluck('signatory.name')->all(),
        );
        $this->assertSame([1, 2, 3], $letter->signatoryRoutes()->pluck('sequence')->all());
        $response->assertJsonCount(3, 'data.signatory_routes');
        $this->assertNull($letter->signatoryRoutes()->firstOrFail()->date_sent);
        $this->assertNull($letter->signatoryRoutes()->firstOrFail()->date_returned);

        $lowerSignatory = Signatory::query()->where('name', 'Sharon A. Pattugalan')->firstOrFail();
        $lowerAmountResponse = $this->postJson('/api/v1/guarantee-letters', [
            ...$payload,
            'gl_amount' => 10000,
            'signatory_id' => $lowerSignatory->id,
        ])->assertCreated();
        $lowerAmountResponse->assertJsonCount(0, 'data.signatory_routes');

        $director = Signatory::query()->where('name', 'Edwin S. Morata')->firstOrFail();
        $this->postJson('/api/v1/guarantee-letters', [
            ...$payload,
            'gl_amount' => 150000,
            'signatory_id' => $director->id,
        ])->assertCreated()->assertJsonCount(0, 'data.signatory_routes');

        $assistantSecretary = Signatory::query()->where('name', 'Ada A. Colico')->firstOrFail();
        $this->postJson('/api/v1/guarantee-letters', [
            ...$payload,
            'gl_amount' => 150001,
            'signatory_id' => $assistantSecretary->id,
        ])->assertCreated()
            ->assertJsonCount(2, 'data.signatory_routes')
            ->assertJsonPath('data.signatory_routes.0.signatory_id', $director->id)
            ->assertJsonPath('data.signatory_routes.1.signatory_id', $assistantSecretary->id);

        $secretary = Signatory::query()->where('name', 'Rex Gatchalian')->firstOrFail();
        $this->postJson('/api/v1/guarantee-letters', [
            ...$payload,
            'gl_amount' => 500001,
            'signatory_id' => $secretary->id,
        ])->assertCreated()
            ->assertJsonCount(4, 'data.signatory_routes')
            ->assertJsonPath('data.signatory_routes.0.signatory_id', $director->id)
            ->assertJsonPath('data.signatory_routes.1.signatory_id', $assistantSecretary->id)
            ->assertJsonPath('data.signatory_routes.2.signatory_id', $finalSignatory->id)
            ->assertJsonPath('data.signatory_routes.3.signatory_id', $secretary->id);
    }

    public function test_route_seeder_is_idempotent_for_seeded_letters(): void
    {
        $this->seed();
        $routeCount = SignatoryRoute::query()->count();
        $this->assertGreaterThan(0, $routeCount);

        $this->seed(SignatoryRouteSeeder::class);
        $this->assertSame($routeCount, SignatoryRoute::query()->count());
    }

    public function test_routing_preview_matches_the_creation_plan_and_rejects_invalid_selection(): void
    {
        $this->seed();
        $this->actingAs(GuaranteeLetter::query()->firstOrFail()->creator, 'api');
        $usec = Signatory::query()->where('name', 'Monina Josefina H. Romualdez')->firstOrFail();

        $this->getJson('/api/v1/signatories/routing-preview?'.http_build_query([
            'gl_amount' => 300001,
            'signatory_id' => $usec->id,
        ]))->assertOk()
            ->assertJsonCount(3)
            ->assertJsonPath('0.name', 'Edwin S. Morata')
            ->assertJsonPath('1.name', 'Ada A. Colico')
            ->assertJsonPath('2.id', $usec->id);

        $this->getJson('/api/v1/signatories/routing-preview?'.http_build_query([
            'gl_amount' => 150000,
            'signatory_id' => Signatory::query()->where('name', 'Edwin S. Morata')->value('id'),
        ]))->assertOk()->assertExactJson([]);

        $this->getJson('/api/v1/signatories/routing-preview?'.http_build_query([
            'gl_amount' => 300001,
            'signatory_id' => Signatory::query()->where('name', 'Edwin S. Morata')->value('id'),
        ]))->assertUnprocessable()->assertJsonValidationErrors('signatory_id');
    }
}
