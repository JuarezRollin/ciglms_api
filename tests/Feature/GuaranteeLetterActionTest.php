<?php

namespace Tests\Feature;

use App\Models\GuaranteeLetter;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class GuaranteeLetterActionTest extends TestCase
{
    use RefreshDatabase;

    private GuaranteeLetter $letter;

    private User $encoder;

    public function createApplication(): Application
    {
        $app = parent::createApplication();

        // Stop before RefreshDatabase if cached configuration points at a real database.
        if ($app['config']->get('database.default') !== 'sqlite'
            || $app['config']->get('database.connections.sqlite.database') !== ':memory:'
            || $app['config']->get('database.connections.sqlite.url')) {
            throw new RuntimeException('These tests require SQLite :memory: without a database URL. No database refresh was attempted.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => 'guarantee-letter-action-tests-only-secret-0123456789']);
        $this->seed();
        $this->letter = GuaranteeLetter::query()
            ->whereHas('status', fn ($query) => $query->where('name', 'Awaiting SOA'))
            ->firstOrFail();
        $this->encoder = User::factory()->create();
        $this->encoder->assignRole('encoder');
        $this->actingAs($this->encoder, 'api');
    }

    public function test_soa_receipt_records_actor_and_advances_status_without_overwriting_processing_remarks(): void
    {
        $remarks = $this->letter->processing_remarks;
        $activitiesBefore = Activity::query()->count();

        $this->recordSoa()->assertOk()->assertJsonPath('data.id', $this->letter->id);

        $this->letter->refresh();
        $this->assertSame('For Pullout', $this->letter->status->name);
        $this->assertSame($this->soaDate(), $this->letter->date_received_soa->toDateString());
        $this->assertSame('1250.50', $this->letter->obligation_amount);
        $this->assertEquals($this->encoder->id, $this->letter->soa_received_by);
        $this->assertSame($remarks, $this->letter->processing_remarks);
        $this->assertDatabaseCount('activity_log', $activitiesBefore + 1);

        $activity = Activity::query()->where('event', 'record_soa_received')->sole();
        $this->assertTrue($activity->subject->is($this->letter));
        $this->assertTrue($activity->causer->is($this->encoder));
        $this->assertSame('Documents received.', $activity->properties['remarks']);
        $this->assertSame('Awaiting SOA', $activity->properties['from_status']);
        $this->assertSame('For Pullout', $activity->properties['to_status']);
    }

    public function test_audit_trail_returns_normalized_guarantee_letter_activities(): void
    {
        $this->recordSoa()->assertOk();

        $this->getJson('/api/v1/guarantee-letters/'.$this->letter->id.'/audit-trail')
            ->assertOk()
            ->assertJsonPath('data.0.event', 'record_soa_received')
            ->assertJsonPath('data.0.title', 'SOA receipt recorded')
            ->assertJsonPath('data.0.from_status', 'Awaiting SOA')
            ->assertJsonPath('data.0.to_status', 'For Pullout')
            ->assertJsonPath('data.0.actor', $this->encoder->name)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_pullout_records_actor_and_advances_to_creation(): void
    {
        $this->recordSoa()->assertOk();
        $this->postJson($this->endpoint(), [
            'action' => 'record_pullout',
            'date_pulled_out' => $this->pulloutDate(),
        ])->assertOk();

        $this->letter->refresh();
        $this->assertSame('For Creation', $this->letter->status->name);
        $this->assertSame($this->pulloutDate(), $this->letter->date_pulled_out->toDateString());
        $this->assertEquals($this->encoder->id, $this->letter->pulled_out_by);
    }

    public function test_duplicate_soa_receipt_does_not_add_another_action(): void
    {
        $this->recordSoa()->assertOk();
        $this->recordSoa()->assertConflict();

        $this->assertSame(1, Activity::query()->where('event', 'record_soa_received')->count());
    }

    public function test_pullout_is_rejected_before_soa_receipt(): void
    {
        $this->postJson($this->endpoint(), [
            'action' => 'record_pullout',
            'date_pulled_out' => $this->pulloutDate(),
        ])->assertConflict();
        $this->assertNull($this->letter->fresh()->date_pulled_out);
    }

    public function test_soa_date_cannot_precede_gl_receipt(): void
    {
        $this->recordSoa(['date_received_soa' => $this->letter->date_received_gl->copy()->subDay()->toDateString()])
            ->assertUnprocessable()->assertJsonValidationErrors('date_received_soa');
        $this->assertNull($this->letter->fresh()->date_received_soa);
    }

    public function test_pullout_date_cannot_precede_soa_receipt(): void
    {
        $this->recordSoa()->assertOk();
        $this->postJson($this->endpoint(), [
            'action' => 'record_pullout',
            'date_pulled_out' => $this->letter->date_received_gl->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('date_pulled_out');
    }

    public function test_action_fields_are_validated_and_actor_and_status_cannot_be_supplied(): void
    {
        $this->recordSoa([
            'obligation_amount' => '-1.123',
            'date_received_soa' => 'not-a-date',
            'soa_received_by' => $this->encoder->id,
            'status_id' => $this->letter->status_id,
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'obligation_amount', 'date_received_soa', 'soa_received_by', 'status_id',
        ]);

        $this->postJson($this->endpoint(), ['action' => 'unknown'])
            ->assertUnprocessable()->assertJsonValidationErrors('action');
    }

    public function test_unavailable_next_status_does_not_partially_save_receipt(): void
    {
        Status::query()->where('name', 'For Pullout')->update(['is_active' => false]);
        $this->recordSoa()->assertUnprocessable()->assertJsonValidationErrors('action');

        $this->letter->refresh();
        $this->assertSame('Awaiting SOA', $this->letter->status->name);
        $this->assertNull($this->letter->date_received_soa);
        $this->assertNull($this->letter->soa_received_by);
        $this->assertSame(0, Activity::query()->where('event', 'record_soa_received')->count());
    }

    public function test_user_without_action_role_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create(), 'api');
        $this->recordSoa()->assertForbidden();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->app['auth']->forgetGuards();
        $this->recordSoa()->assertUnauthorized();
    }

    public function test_soa_receipt_allows_an_empty_obligation_amount(): void
    {
        $this->postJson($this->endpoint(), [
            'action' => 'record_soa_received',
            'date_received_soa' => $this->soaDate(),
        ])->assertOk();

        $this->assertNull($this->letter->fresh()->obligation_amount);
    }

    public function test_required_action_fields_cannot_be_omitted(): void
    {
        $this->postJson($this->endpoint(), ['action' => 'record_soa_received'])
            ->assertUnprocessable()->assertJsonValidationErrors('date_received_soa');
        $this->postJson($this->endpoint(), ['action' => 'record_pullout'])
            ->assertUnprocessable()->assertJsonValidationErrors('date_pulled_out');
    }

    public function test_batched_gl_cannot_use_individual_receipt_actions(): void
    {
        $this->letter = GuaranteeLetter::query()->whereHas('batches')->firstOrFail();
        $this->recordSoa()->assertConflict();
    }

    public function test_admin_can_record_soa_receipt(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'api');
        $this->recordSoa(['obligation_amount' => null])->assertOk();
        $this->assertEquals($admin->id, $this->letter->fresh()->soa_received_by);
    }

    public function test_admin_can_encode_an_obligation_amount_during_soa_receipt(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'api');

        $this->recordSoa()->assertOk();
    }

    public function test_encoder_can_update_obligation_amount_without_changing_the_gl_status(): void
    {
        $this->recordSoa(['obligation_amount' => null])->assertOk();
        $this->letter->refresh();
        $status = $this->letter->status->name;

        $this->postJson($this->endpoint(), [
            'action' => 'update_obligation_amount',
            'obligation_amount' => '2450.75',
        ])->assertOk()
            ->assertJsonPath('data.obligation_amount', '2450.75');

        $this->letter->refresh();
        $this->assertSame('2450.75', $this->letter->obligation_amount);
        $this->assertSame($status, $this->letter->status->name);
        $this->assertSame(1, Activity::query()->where('event', 'update_obligation_amount')->count());
    }

    public function test_unchanged_obligation_amount_is_rejected_without_an_audit_entry(): void
    {
        $this->recordSoa(['obligation_amount' => '1250.50'])->assertOk();
        $activitiesBefore = Activity::query()->where('event', 'update_obligation_amount')->count();

        $this->postJson($this->endpoint(), [
            'action' => 'update_obligation_amount',
            'obligation_amount' => '1250.50',
        ])->assertUnprocessable()->assertJsonValidationErrors('obligation_amount');

        $this->assertSame($activitiesBefore, Activity::query()->where('event', 'update_obligation_amount')->count());
    }

    public function test_admin_can_update_obligation_amount(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'api');

        $this->postJson($this->endpoint(), [
            'action' => 'update_obligation_amount',
            'obligation_amount' => '2450.75',
        ])->assertOk();
    }

    public function test_reviewer_can_update_obligation_amount(): void
    {
        $reviewer = User::factory()->create();
        $reviewer->assignRole('reviewer');
        $this->actingAs($reviewer, 'api');

        $this->postJson($this->endpoint(), [
            'action' => 'update_obligation_amount',
            'obligation_amount' => '2450.75',
        ])->assertOk();
    }

    public function test_detail_response_exposes_obligation_amount_editing_to_allowed_roles_only(): void
    {
        $this->getJson('/api/v1/guarantee-letters/'.$this->letter->id)
            ->assertOk()
            ->assertJsonPath('data.can_update_obligation_amount', true);

        $reviewer = User::factory()->create();
        $reviewer->assignRole('reviewer');
        $this->actingAs($reviewer, 'api');

        $this->getJson('/api/v1/guarantee-letters/'.$this->letter->id)
            ->assertOk()
            ->assertJsonPath('data.can_update_obligation_amount', true);

        $this->actingAs(User::factory()->create(), 'api');

        $this->getJson('/api/v1/guarantee-letters/'.$this->letter->id)
            ->assertOk()
            ->assertJsonPath('data.can_update_obligation_amount', false);
    }

    public function test_normal_update_keeps_existing_validation_instead_of_requiring_admin(): void
    {
        $this->patchJson('/api/v1/guarantee-letters/'.$this->letter->id, ['processing_remarks' => 'Normal edit'])
            ->assertUnprocessable()->assertJsonValidationErrors('status_id');
    }

    public function test_action_endpoint_rejects_an_empty_action(): void
    {
        $this->recordSoa(['action' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('action');
    }

    public function test_encoder_can_still_submit_a_complete_normal_edit(): void
    {
        $payload = $this->letter->attributesToArray();
        unset($payload['received_by'], $payload['date_received_gl']);
        $payload['processing_remarks'] = 'Updated GL remarks';

        $this->patchJson('/api/v1/guarantee-letters/'.$this->letter->id, $payload)->assertOk();

        $this->letter->refresh();
        $this->assertSame('Updated GL remarks', $this->letter->processing_remarks);
        $this->assertSame('Awaiting SOA', $this->letter->status->name);
        $this->assertNull($this->letter->date_received_soa);
    }

    public function test_action_does_not_apply_unrelated_gl_fields(): void
    {
        $originalNumber = $this->letter->gl_no;
        $this->recordSoa(['gl_no' => 'UNEXPECTED-CHANGE'])->assertOk();

        $this->assertSame($originalNumber, $this->letter->fresh()->gl_no);
    }

    /** @param array<string, mixed> $overrides */
    private function recordSoa(array $overrides = []): TestResponse
    {
        return $this->postJson($this->endpoint(), array_replace([
            'action' => 'record_soa_received',
            'date_received_soa' => $this->soaDate(),
            'obligation_amount' => '1250.50',
            'remarks' => 'Documents received.',
        ], $overrides));
    }

    private function endpoint(): string
    {
        return '/api/v1/guarantee-letters/'.$this->letter->id.'/actions';
    }

    private function soaDate(): string
    {
        return $this->letter->date_received_gl->copy()->addDay()->toDateString();
    }

    private function pulloutDate(): string
    {
        return $this->letter->date_received_gl->copy()->addDays(2)->toDateString();
    }
}
