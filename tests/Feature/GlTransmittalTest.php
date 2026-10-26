<?php

namespace Tests\Feature;

use App\Models\AssistanceType;
use App\Models\Beneficiary;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Client;
use App\Models\GlTransmittal;
use App\Models\GuaranteeLetter;
use App\Models\Office;
use App\Models\PsgcRegion;
use App\Models\Relationship;
use App\Models\ServiceProvider;
use App\Models\Signatory;
use App\Models\Status;
use App\Models\Swo;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlTransmittalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => 'gl-transmittal-tests-only-secret-0123456789']);
        $this->seed(StatusSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->category = Category::query()->create(['name' => 'Hospital Bill']);
        $this->user = User::factory()->create(['name' => 'Frans Audrey S Sandicon']);
        $this->user->assignRole('glc admin');
        $this->actingAs($this->user, 'api');
    }

    public function test_creation_generates_bundle_number_and_advances_released_letters_even_when_categories_differ(): void
    {
        $otherCategory = Category::query()->create(['name' => 'Medicine']);
        $first = $this->letter(['category_id' => $otherCategory->id]);
        $first->forceFill(['date_released' => '2026-10-01'])->save();
        $second = $this->letter();

        $this->postJson('/api/v1/gl-transmittals', $this->payload([$first->id, $second->id]))
            ->assertCreated()
            ->assertJsonPath('data.bundle_no', 'FASS-'.now('Asia/Manila')->format('m-d').'-0001')
            ->assertJsonPath('data.category_id', $this->category->id)
            ->assertJsonPath('data.gls.0.date_released', $first->date_released->toJSON())
            ->assertJsonPath('data.gls.0.service_provider.name', 'Test Hospital')
            ->assertJsonPath('data.gls.0.signatory.name', 'Test Signatory')
            ->assertJsonCount(2, 'data.gls');

        $this->assertSame(1, GlTransmittal::query()->count());
        $this->assertSame('For Endorsement', $first->fresh()->status->name);
        $this->assertSame('For Endorsement', $second->fresh()->status->name);
        $this->assertNull(GlTransmittal::query()->sole()->received_by);
        $this->assertDatabaseCount('gl_transmittal', 2);
        $this->getJson('/api/v1/gl-transmittals/'.GlTransmittal::query()->sole()->id)
            ->assertOk()
            ->assertJsonPath('data.gls.0.signatory.name', 'Test Signatory');

        $third = $this->letter();
        $this->postJson('/api/v1/gl-transmittals', $this->payload([$third->id]))
            ->assertCreated()
            ->assertJsonPath('data.bundle_no', 'FASS-'.now('Asia/Manila')->format('m-d').'-0002');
    }

    public function test_selected_letter_order_is_preserved_for_the_pdf_and_when_editing(): void
    {
        $first = $this->letter();
        $second = $this->letter();
        $third = $this->letter();

        $this->postJson('/api/v1/gl-transmittals', $this->payload([$third->id, $first->id, $second->id]))
            ->assertCreated()
            ->assertJsonPath('data.gls.0.id', $third->id)
            ->assertJsonPath('data.gls.1.id', $first->id)
            ->assertJsonPath('data.gls.2.id', $second->id);

        $transmittal = GlTransmittal::query()->sole();
        $this->assertSame([$third->id, $first->id, $second->id], $transmittal->gls()->pluck('guarantee_letters.id')->all());
        $this->assertDatabaseHas('gl_transmittal', ['gl_transmittal_id' => $transmittal->id, 'gl_id' => $third->id, 'position' => 1]);

        $this->putJson("/api/v1/gl-transmittals/{$transmittal->id}", $this->payload([$second->id, $third->id, $first->id]))
            ->assertOk()
            ->assertJsonPath('data.gls.0.id', $second->id)
            ->assertJsonPath('data.gls.1.id', $third->id)
            ->assertJsonPath('data.gls.2.id', $first->id);

        $this->assertSame([$second->id, $third->id, $first->id], $transmittal->gls()->pluck('guarantee_letters.id')->all());
        $this->assertDatabaseHas('gl_transmittal', ['gl_transmittal_id' => $transmittal->id, 'gl_id' => $second->id, 'position' => 1]);
    }

    public function test_receiving_list_excludes_cancelled_transmittals(): void
    {
        $first = $this->letter();
        $second = $this->letter();
        $this->postJson('/api/v1/gl-transmittals', $this->payload([$first->id]))->assertCreated();
        $cancelled = GlTransmittal::query()->sole();
        $this->postJson("/api/v1/gl-transmittals/{$cancelled->id}/cancel")->assertOk();
        $this->postJson('/api/v1/gl-transmittals', $this->payload([$second->id]))->assertCreated();

        $this->getJson('/api/v1/gl-transmittals?scope=receiving')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.cancelled_at', null);
        $this->getJson('/api/v1/gl-transmittals')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_transmittal_table_filters_match_receipt_state_and_related_columns(): void
    {
        $otherCategory = Category::query()->create(['name' => 'Medicine']);
        $otherCreator = User::factory()->create(['name' => 'Other Preparer']);
        $otherCreator->assignRole('glc admin');
        $encoder = User::factory()->create(['name' => 'Receiving Encoder']);
        $encoder->assignRole('encoder');

        $pendingLetter = $this->letter();
        $this->postJson('/api/v1/gl-transmittals', $this->payload([$pendingLetter->id]))->assertCreated();
        $pending = GlTransmittal::query()->latest('id')->firstOrFail();
        $pending->forceFill(['created_at' => '2026-10-17 16:30:00'])->save();

        $partialLetters = [$this->letter(), $this->letter()];
        $this->postJson('/api/v1/gl-transmittals', $this->payload(array_map(fn (GuaranteeLetter $letter): int => $letter->id, $partialLetters)))->assertCreated();
        $partial = GlTransmittal::query()->latest('id')->firstOrFail();

        $receivedLetter = $this->letter();
        $this->actingAs($otherCreator, 'api');
        $this->postJson('/api/v1/gl-transmittals', ['category_id' => $otherCategory->id, 'gl_ids' => [$receivedLetter->id]])->assertCreated();
        $received = GlTransmittal::query()->latest('id')->firstOrFail();
        $this->actingAs($this->user, 'api');

        $cancelledLetter = $this->letter();
        $this->postJson('/api/v1/gl-transmittals', $this->payload([$cancelledLetter->id]))->assertCreated();
        $cancelled = GlTransmittal::query()->latest('id')->firstOrFail();
        $this->postJson("/api/v1/gl-transmittals/{$cancelled->id}/cancel")->assertOk();

        $this->actingAs($encoder, 'api');
        $this->postJson("/api/v1/gl-transmittals/{$partial->id}/receive", ['gl_id' => $partialLetters[0]->id])->assertOk();
        $this->postJson("/api/v1/gl-transmittals/{$received->id}/receive")->assertOk();

        foreach (['pending' => $pending, 'partial' => $partial, 'received' => $received, 'cancelled' => $cancelled] as $status => $transmittal) {
            $this->getJson("/api/v1/gl-transmittals?receipt_status={$status}")
                ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $transmittal->id);
        }

        $this->getJson('/api/v1/gl-transmittals?scope=receiving&receipt_status=cancelled')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/gl-transmittals?category_id={$otherCategory->id}&created_by={$otherCreator->id}&received_by={$encoder->id}")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $received->id);
        $this->getJson('/api/v1/gl-transmittals?search='.urlencode($received->bundle_no))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $received->id);
        $this->getJson('/api/v1/gl-transmittals?search='.urlencode($pending->bundle_no).'&date_from=2026-10-18&date_to=2026-10-18')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $pending->id);
        $this->getJson('/api/v1/gl-transmittals?search='.urlencode($pending->bundle_no).'&date_to=2026-10-17')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/gl-transmittals/filter-options?scope=receiving')
            ->assertOk()->assertJsonFragment(['id' => $encoder->id, 'name' => $encoder->name]);
        $this->getJson('/api/v1/gl-transmittals?date_from=2026-10-19&date_to=2026-10-18')->assertUnprocessable();
    }

    public function test_encoder_can_receive_one_gl_then_the_remaining_transmittal(): void
    {
        $first = $this->letter();
        $second = $this->letter();
        $this->postJson('/api/v1/gl-transmittals', $this->payload([$first->id, $second->id]))->assertCreated();
        $transmittal = GlTransmittal::query()->sole();
        $encoder = User::factory()->create();
        $encoder->assignRole('encoder');
        $this->actingAs($encoder, 'api');

        $this->getJson('/api/v1/gl-transmittals')
            ->assertOk()
            ->assertJsonPath('permissions.can_manage', false)
            ->assertJsonPath('permissions.can_receive', true)
            ->assertJsonPath('data.0.received_gl_count', 0);

        $this->postJson("/api/v1/gl-transmittals/{$transmittal->id}/receive", ['gl_id' => $first->id])
            ->assertOk()
            ->assertJsonPath('data.received_gl_count', 1)
            ->assertJsonPath('data.received_by', null)
            ->assertJsonPath('data.gls.0.status.name', 'Awaiting SOA');
        $this->assertSame(Status::AWAITING_SOA_ID, $first->fresh()->status_id);
        $this->assertSame($encoder->id, $first->fresh()->received_by);
        $this->assertNotNull($first->fresh()->date_received_gl);
        $this->assertSame(Status::FOR_ENDORSEMENT_ID, $second->fresh()->status_id);

        $this->postJson("/api/v1/gl-transmittals/{$transmittal->id}/receive")
            ->assertOk()
            ->assertJsonPath('data.received_gl_count', 2)
            ->assertJsonPath('data.received_by', $encoder->id)
            ->assertJsonPath('data.receiver.name', $encoder->name);
        $this->assertSame($encoder->id, $transmittal->fresh()->received_by);
        $this->getJson('/api/v1/gl-transmittals?scope=receiving')
            ->assertOk()
            ->assertJsonPath('data.0.received_by', $encoder->id)
            ->assertJsonPath('data.0.receiver.name', $encoder->name);
        $this->assertSame(Status::AWAITING_SOA_ID, $second->fresh()->status_id);
        $this->assertSame($encoder->id, $second->fresh()->received_by);
        $this->postJson("/api/v1/gl-transmittals/{$transmittal->id}/receive")->assertConflict();
        $this->postJson("/api/v1/gl-transmittals/{$transmittal->id}/receive", ['gl_id' => $first->id])->assertConflict();
    }

    public function test_encoder_can_receive_selected_gls_without_receiving_the_rest(): void
    {
        $first = $this->letter();
        $second = $this->letter();
        $third = $this->letter();
        $outside = $this->letter();
        $this->postJson('/api/v1/gl-transmittals', $this->payload([$first->id, $second->id, $third->id]))->assertCreated();
        $transmittal = GlTransmittal::query()->sole();
        $encoder = User::factory()->create();
        $encoder->assignRole('encoder');
        $this->actingAs($encoder, 'api');

        $endpoint = "/api/v1/gl-transmittals/{$transmittal->id}/receive";
        $this->postJson($endpoint, ['gl_ids' => [$first->id, $second->id]])
            ->assertOk()
            ->assertJsonPath('data.received_gl_count', 2)
            ->assertJsonPath('data.received_by', null);
        $this->assertSame(Status::AWAITING_SOA_ID, $first->fresh()->status_id);
        $this->assertSame(Status::AWAITING_SOA_ID, $second->fresh()->status_id);
        $this->assertSame(Status::FOR_ENDORSEMENT_ID, $third->fresh()->status_id);

        $this->postJson($endpoint, ['gl_ids' => [$first->id, $third->id]])->assertConflict();
        $this->assertNull($third->fresh()->date_received_gl);
        $this->postJson($endpoint, ['gl_ids' => [$third->id, $outside->id]])->assertUnprocessable();
        $this->postJson($endpoint, ['gl_ids' => [$third->id, $third->id]])->assertUnprocessable();
        $this->postJson($endpoint, ['gl_id' => $third->id, 'gl_ids' => [$third->id]])->assertUnprocessable();

        $this->postJson($endpoint, ['gl_ids' => [$third->id]])
            ->assertOk()
            ->assertJsonPath('data.received_gl_count', 3)
            ->assertJsonPath('data.received_by', $encoder->id);
    }

    public function test_roles_and_received_letters_protect_transmittal_changes(): void
    {
        $first = $this->letter();
        $second = $this->letter();
        $this->postJson('/api/v1/gl-transmittals', $this->payload([$first->id, $second->id]))->assertCreated();
        $transmittal = GlTransmittal::query()->sole();
        $encoder = User::factory()->create();
        $encoder->assignRole('encoder');
        $this->actingAs($encoder, 'api');

        $this->postJson('/api/v1/gl-transmittals', $this->payload([$first->id]))->assertForbidden();
        $this->putJson("/api/v1/gl-transmittals/{$transmittal->id}", $this->payload([$second->id]))->assertForbidden();
        $this->postJson("/api/v1/gl-transmittals/{$transmittal->id}/cancel")->assertForbidden();
        $this->postJson("/api/v1/gl-transmittals/{$transmittal->id}/receive", ['gl_id' => 999999])->assertUnprocessable();
        $this->postJson("/api/v1/gl-transmittals/{$transmittal->id}/receive", ['gl_id' => $first->id])->assertOk();

        $this->actingAs($this->user, 'api');
        $this->postJson("/api/v1/gl-transmittals/{$transmittal->id}/receive")->assertForbidden();
        $this->putJson("/api/v1/gl-transmittals/{$transmittal->id}", $this->payload([$second->id]))->assertConflict();
        $this->postJson("/api/v1/gl-transmittals/{$transmittal->id}/cancel")->assertConflict();
        $this->assertNull($transmittal->fresh()->cancelled_at);
        $this->assertSame(Status::FOR_ENDORSEMENT_ID, $second->fresh()->status_id);
    }

    public function test_bulk_receipt_is_atomic_when_a_letter_is_no_longer_eligible(): void
    {
        $first = $this->letter();
        $second = $this->letter();
        $this->postJson('/api/v1/gl-transmittals', $this->payload([$first->id, $second->id]))->assertCreated();
        $transmittal = GlTransmittal::query()->sole();
        $second->refresh()->update(['status_id' => Status::RELEASED_ID]);
        $this->assertSame(Status::RELEASED_ID, $second->fresh()->status_id);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'api');

        $this->postJson("/api/v1/gl-transmittals/{$transmittal->id}/receive")->assertConflict();
        $this->assertSame(Status::FOR_ENDORSEMENT_ID, $first->fresh()->status_id);
        $this->assertNull($first->fresh()->date_received_gl);

        $second->update(['status_id' => Status::FOR_ENDORSEMENT_ID]);
        $this->postJson("/api/v1/gl-transmittals/{$transmittal->id}/receive")
            ->assertOk()
            ->assertJsonPath('data.received_gl_count', 2);
    }

    public function test_active_transmittal_receipt_cannot_be_recorded_through_general_gl_update(): void
    {
        $letter = $this->letter();
        $this->postJson('/api/v1/gl-transmittals', $this->payload([$letter->id]))->assertCreated();
        $payload = $letter->refresh()->only([
            'gl_date', 'client_id', 'beneficiary_id', 'address', 'relationship_id',
            'service_provider_id', 'assistance_type_id', 'status_id', 'gl_amount',
            'signatory_id', 'gl_validity', 'swo_id', 'category_id',
        ]);

        $this->putJson("/api/v1/guarantee-letters/{$letter->id}", [
            ...$payload,
            'status_id' => Status::AWAITING_SOA_ID,
        ])->assertUnprocessable()->assertJsonValidationErrors('status_id');

        $this->putJson("/api/v1/guarantee-letters/{$letter->id}", [
            ...$payload,
            'received_by' => $this->user->id,
            'date_received_gl' => now('Asia/Manila')->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['received_by', 'date_received_gl']);
        $this->assertNull($letter->fresh()->date_received_gl);
    }

    public function test_edit_replaces_members_and_cancel_restores_released_status_without_losing_history(): void
    {
        $first = $this->letter();
        $second = $this->letter();
        $this->postJson('/api/v1/gl-transmittals', $this->payload([$first->id]))->assertCreated();
        $transmittal = GlTransmittal::query()->sole();

        $this->putJson("/api/v1/gl-transmittals/{$transmittal->id}", $this->payload([$second->id]))
            ->assertOk()->assertJsonCount(1, 'data.gls');
        $this->assertSame('Released', $first->fresh()->status->name);
        $this->assertSame('For Endorsement', $second->fresh()->status->name);

        $this->postJson("/api/v1/gl-transmittals/{$transmittal->id}/cancel")
            ->assertOk()->assertJsonPath('data.gls.0.id', $second->id);
        $this->assertSame('Released', $second->fresh()->status->name);
        $this->assertNotNull($transmittal->fresh()->cancelled_at);
        $this->assertDatabaseHas('gl_transmittal', ['gl_transmittal_id' => $transmittal->id, 'gl_id' => $second->id]);
        $this->putJson("/api/v1/gl-transmittals/{$transmittal->id}", $this->payload([$first->id]))->assertConflict();
        $this->postJson("/api/v1/gl-transmittals/{$transmittal->id}/cancel")->assertConflict();
        $this->postJson('/api/v1/gl-transmittals', $this->payload([$second->id]))->assertCreated();
    }

    public function test_bundle_numbers_stay_unique_for_creators_with_the_same_initials(): void
    {
        $first = $this->letter();
        $second = $this->letter();
        $this->postJson('/api/v1/gl-transmittals', $this->payload([$first->id]))->assertCreated();

        $otherCreator = User::factory()->create(['name' => 'Fiona Anne S Santos']);
        $otherCreator->assignRole('glc superadmin');
        $this->actingAs($otherCreator, 'api');
        $this->postJson('/api/v1/gl-transmittals', $this->payload([$second->id]))
            ->assertCreated()
            ->assertJsonPath('data.bundle_no', 'FASS-'.now('Asia/Manila')->format('m-d').'-0002');
    }

    public function test_custom_creator_initials_are_used_for_new_bundle_numbers(): void
    {
        $letter = $this->letter();
        $this->user->update(['initials' => 'FAS']);

        $this->postJson('/api/v1/gl-transmittals', $this->payload([$letter->id]))
            ->assertCreated()
            ->assertJsonPath('data.bundle_no', 'FAS-'.now('Asia/Manila')->format('m-d').'-0001')
            ->assertJsonPath('data.creator.initials', 'FAS');
    }

    public function test_status_label_changes_do_not_block_transmittal_creation_editing_or_cancellation(): void
    {
        $first = $this->letter();
        $second = $this->letter();
        Status::query()->findOrFail(Status::RELEASED_ID)->update(['name' => 'Released to Client']);
        Status::query()->findOrFail(Status::FOR_ENDORSEMENT_ID)->update(['name' => 'Ready for Endorsement']);

        $this->getJson('/api/v1/gl-transmittals/eligible')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
        $this->postJson('/api/v1/gl-transmittals', $this->payload([$first->id]))->assertCreated();
        $transmittal = GlTransmittal::query()->sole();
        $this->assertSame(Status::FOR_ENDORSEMENT_ID, $first->fresh()->status_id);

        $this->putJson("/api/v1/gl-transmittals/{$transmittal->id}", $this->payload([$first->id, $second->id]))->assertOk();
        $this->postJson("/api/v1/gl-transmittals/{$transmittal->id}/cancel")->assertOk();
        $this->assertSame(Status::RELEASED_ID, $first->fresh()->status_id);
        $this->assertSame(Status::RELEASED_ID, $second->fresh()->status_id);
    }

    public function test_eligible_letters_can_be_filtered_by_provider_gl_creator_and_signatory(): void
    {
        $default = $this->letter();
        $otherProvider = ServiceProvider::query()->create([
            'name' => 'Another Hospital', 'address' => 'Quezon City',
            'salutation' => 'Dear', 'addressee' => 'Director', 'designation' => 'Director',
            'region_id' => $default->serviceProvider->region_id,
        ]);
        $otherSignatory = Signatory::query()->create([
            'name' => 'Another Signatory', 'designation' => 'Director',
            'minimum_amount' => '0.00', 'maximum_amount' => '99999999.99',
        ]);
        $otherCreator = User::factory()->create(['name' => 'Another GL Creator']);
        $branch = Branch::query()->create([
            'service_provider_id' => $otherProvider->id,
            'name' => 'North Branch',
            'address' => 'Quezon City',
        ]);
        $matching = $this->letter([
            'service_provider_id' => $otherProvider->id,
            'branch_id' => $branch->id,
            'created_by' => $otherCreator->id,
            'signatory_id' => $otherSignatory->id,
        ]);
        $this->letter(['service_provider_id' => $otherProvider->id]);
        $this->letter(['status_id' => Status::FOR_REVIEW_BY_CRD_ID]);

        $endpoint = '/api/v1/gl-transmittals/eligible';
        foreach ([
            'created_by' => $otherCreator->id,
            'signatory_id' => $otherSignatory->id,
        ] as $field => $id) {
            $this->getJson($endpoint.'?'.http_build_query(['filter' => [$field => $id]]))
                ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $matching->id);
        }

        $this->getJson($endpoint.'?'.http_build_query([
            'filter' => [
                'service_provider_id' => $otherProvider->id,
                'created_by' => $otherCreator->id,
                'signatory_id' => $otherSignatory->id,
            ],
            'search' => $matching->gl_no,
            'per_page' => 1,
        ]))->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $matching->id)
            ->assertJsonPath('data.0.creator.name', $otherCreator->name)
            ->assertJsonPath('data.0.creator.initials', $otherCreator->initials)
            ->assertJsonPath('data.0.beneficiary.full_name', 'Test Beneficiary')
            ->assertJsonPath('data.0.branch.name', 'North Branch')
            ->assertJsonPath('data.0.signatory.initials', $otherSignatory->initials)
            ->assertJsonPath('data.0.signatory.name', $otherSignatory->name);

        $this->getJson($endpoint.'?'.http_build_query(['filter' => ['service_provider_id' => $otherProvider->id]]))
            ->assertOk()->assertJsonCount(2, 'data');
        $this->getJson($endpoint.'?'.http_build_query(['filter' => ['unsupported' => '1']]))
            ->assertUnprocessable()->assertJsonValidationErrors('filter');

        $this->getJson($endpoint.'/filter-options')->assertOk()
            ->assertJsonCount(2, 'service_providers')
            ->assertJsonCount(2, 'gl_creators')
            ->assertJsonCount(2, 'signatories');

        $this->postJson('/api/v1/gl-transmittals', $this->payload([$matching->id]))->assertCreated();
        $this->getJson($endpoint.'?'.http_build_query(['filter' => ['created_by' => $otherCreator->id]]))
            ->assertOk()->assertJsonCount(0, 'data');
        $this->getJson($endpoint.'/filter-options')->assertOk()->assertJsonCount(1, 'gl_creators');
        $this->getJson($endpoint)->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.1.id', $default->id)->assertJsonPath('data.1.branch', null);
    }

    public function test_cancel_does_not_overwrite_a_letter_that_has_advanced_past_endorsement(): void
    {
        $letter = $this->letter();
        $this->postJson('/api/v1/gl-transmittals', $this->payload([$letter->id]))->assertCreated();
        $transmittal = GlTransmittal::query()->sole();
        $letter->update(['status_id' => Status::FOR_REVIEW_BY_CRD_ID]);

        $this->postJson("/api/v1/gl-transmittals/{$transmittal->id}/cancel")->assertConflict();
        $this->assertNull($transmittal->fresh()->cancelled_at);
        $this->assertSame('For Review by CRD', $letter->fresh()->status->name);
    }

    public function test_selection_rejects_non_released_duplicates_and_more_than_thirty(): void
    {
        $released = $this->letter();
        $review = $this->letter(['status_id' => Status::FOR_REVIEW_BY_CRD_ID]);
        $this->postJson('/api/v1/gl-transmittals', $this->payload([$review->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('gl_ids');
        $this->postJson('/api/v1/gl-transmittals', $this->payload([$released->id, $released->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('gl_ids.1');
        $this->postJson('/api/v1/gl-transmittals', $this->payload(range(1, 31)))
            ->assertUnprocessable()->assertJsonValidationErrors('gl_ids');
        $this->assertDatabaseCount('gl_transmittals', 0);

        $this->postJson('/api/v1/gl-transmittals', $this->payload([$released->id]))->assertCreated();
        $this->getJson('/api/v1/gl-transmittals/eligible')->assertOk()->assertJsonMissing(['id' => $released->id]);
        $this->postJson('/api/v1/gl-transmittals', $this->payload([$released->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('gl_ids');
    }

    public function test_pdf_uses_standard_landscape_masterlist_and_cancelled_pdf_is_blocked(): void
    {
        $letters = [];
        for ($index = 0; $index < 30; $index++) {
            $letters[] = $this->letter()->id;
        }
        Client::query()->where('full_name', 'Test Client')->update(['full_name' => 'Maria Christina Dela Cruz de los Santos']);
        Beneficiary::query()->where('full_name', 'Test Beneficiary')->update(['full_name' => 'Juan Antonio Dela Cruz de los Santos']);
        ServiceProvider::query()->where('name', 'Test Hospital')->update(['name' => 'Philippine General Hospital Medical Center']);
        $this->postJson('/api/v1/gl-transmittals', $this->payload($letters))->assertCreated();
        $transmittal = GlTransmittal::query()->sole();

        $pdf = $this->get("/api/v1/gl-transmittals/{$transmittal->id}/print")
            ->assertOk()->assertHeader('Content-Type', 'application/pdf')->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page\b/', $pdf));

        $this->postJson("/api/v1/gl-transmittals/{$transmittal->id}/cancel")->assertOk();
        $this->get("/api/v1/gl-transmittals/{$transmittal->id}/print")->assertConflict();
    }

    public function test_pdf_keeps_thirty_long_name_rows_on_one_page(): void
    {
        $letters = [];
        for ($index = 0; $index < 30; $index++) {
            $letters[] = $this->letter()->id;
        }
        Client::query()->where('full_name', 'Test Client')->update(['full_name' => str_repeat('Maria Christina Dela Cruz ', 4)]);
        Beneficiary::query()->where('full_name', 'Test Beneficiary')->update(['full_name' => str_repeat('Juan Antonio Dela Cruz ', 4)]);
        ServiceProvider::query()->where('name', 'Test Hospital')->update(['name' => str_repeat('Philippine General Hospital ', 4)]);

        $this->postJson('/api/v1/gl-transmittals', $this->payload($letters))->assertCreated();
        $transmittal = GlTransmittal::query()->sole();

        $pdf = $this->get("/api/v1/gl-transmittals/{$transmittal->id}/print")
            ->assertOk()->getContent();
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page\b/', $pdf));
    }

    /** @param array<string, mixed> $attributes */
    private function letter(array $attributes = []): GuaranteeLetter
    {
        $region = PsgcRegion::query()->firstOrCreate(['code' => '0000000020'], ['code_correspondence' => '000000020', 'name' => 'Central Office']);
        $office = Office::query()->firstOrCreate(['name' => 'Test Office'], ['region_id' => $region->reg_id]);
        $client = Client::query()->firstOrCreate(['full_name' => 'Test Client'], ['sex' => 1, 'address' => 'Manila']);
        $beneficiary = Beneficiary::query()->firstOrCreate(['full_name' => 'Test Beneficiary']);
        $provider = ServiceProvider::query()->firstOrCreate(['name' => 'Test Hospital'], ['region_id' => $region->reg_id, 'address' => 'Manila', 'salutation' => 'Dear', 'addressee' => 'Director', 'designation' => 'Director']);
        $signatory = Signatory::query()->firstOrCreate(['name' => 'Test Signatory'], ['designation' => 'Director', 'minimum_amount' => '0.00', 'maximum_amount' => '99999999.99']);

        return GuaranteeLetter::query()->create(array_replace([
            'gl_no' => 'GL-TEST-'.(GuaranteeLetter::query()->count() + 1),
            'gl_date' => '2026-10-01',
            'gl_amount' => '5000.00',
            'gl_validity' => '2026-11-01',
            'office_id' => $office->id,
            'client_id' => $client->id,
            'beneficiary_id' => $beneficiary->id,
            'address' => 'Manila',
            'relationship_id' => Relationship::query()->firstOrCreate(['name' => 'Self'])->id,
            'service_provider_id' => $provider->id,
            'category_id' => $this->category->id,
            'assistance_type_id' => AssistanceType::query()->firstOrCreate(['name' => 'Hospital'])->id,
            'status_id' => Status::query()->where('name', 'Released')->sole()->id,
            'signatory_id' => $signatory->id,
            'swo_id' => Swo::query()->firstOrCreate(['name' => 'Test SWO'])->id,
            'created_by' => $this->user->id,
        ], $attributes));
    }

    /** @param list<int> $ids
     * @return array{category_id: int, gl_ids: list<int>}
     */
    private function payload(array $ids): array
    {
        return ['category_id' => $this->category->id, 'gl_ids' => $ids];
    }
}
