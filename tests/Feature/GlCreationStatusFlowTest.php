<?php

namespace Tests\Feature;

use App\Models\GuaranteeLetter;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlCreationStatusFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_review_approval_release_and_transmittal_follow_the_required_order(): void
    {
        $this->seed();
        $sample = GuaranteeLetter::query()->firstOrFail();
        $staff = User::factory()->create(['office_id' => $sample->office_id]);
        $staff->assignRole('admin');
        $this->actingAs($staff, 'api');
        $payload = $sample->only([
            'client_id', 'beneficiary_id', 'address', 'relationship_id',
            'service_provider_id', 'branch_id', 'assistance_type_id',
            'gl_amount', 'gl_validity', 'signatory_id', 'swo_id', 'category_id',
        ]);
        $payload['gl_date'] = '2026-10-17';

        $this->postJson('/api/v1/guarantee-letters', [...$payload, 'status_id' => Status::APPROVED_ID])
            ->assertUnprocessable()->assertJsonValidationErrors('status_id');
        $response = $this->postJson('/api/v1/guarantee-letters', $payload)
            ->assertCreated()
            ->assertJsonPath('data.status_id', Status::FOR_REVIEW_BY_CRD_ID)
            ->assertJsonPath('data.status.name', 'For Review by CRD')
            ->assertJsonPath('data.creation_action', 'approve_gl');
        $letter = GuaranteeLetter::query()->findOrFail($response->json('data.id'));

        $this->postJson("/api/v1/guarantee-letters/{$letter->id}/actions", ['action' => 'release_gl'])
            ->assertConflict();
        $this->putJson("/api/v1/guarantee-letters/{$letter->id}", [...$payload, 'status_id' => Status::RELEASED_ID])
            ->assertUnprocessable()->assertJsonValidationErrors('status_id');

        $this->postJson("/api/v1/guarantee-letters/{$letter->id}/actions", ['action' => 'approve_gl'])
            ->assertOk()->assertJsonPath('data.status.name', 'Approved')
            ->assertJsonPath('data.creation_action', 'release_gl');
        $letter->refresh();
        $this->assertSame($staff->id, $letter->reviewed_by_creation);
        $this->assertSame($staff->id, $letter->approved_by);
        $this->assertNotNull($letter->date_reviewed_by_creation);
        $this->assertNotNull($letter->date_approved);
        $this->postJson("/api/v1/guarantee-letters/{$letter->id}/actions", ['action' => 'approve_gl'])
            ->assertConflict();

        $this->getJson('/api/v1/gl-transmittals/eligible?search='.$letter->gl_no)
            ->assertOk()->assertJsonPath('meta.total', 0);
        $this->postJson("/api/v1/guarantee-letters/{$letter->id}/actions", ['action' => 'release_gl'])
            ->assertOk()->assertJsonPath('data.status.name', 'Released')
            ->assertJsonPath('data.creation_action', null);
        $letter->refresh();
        $this->assertSame($staff->id, $letter->released_by);
        $this->assertNotNull($letter->date_released);
        $this->getJson('/api/v1/gl-transmittals/eligible?search='.$letter->gl_no)
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $letter->id);

        $this->postJson('/api/v1/gl-transmittals', [
            'category_id' => $letter->category_id,
            'gl_ids' => [$letter->id],
        ])->assertCreated()->assertJsonPath('data.gls.0.status.name', 'For Endorsement');
        $this->assertSame(Status::FOR_ENDORSEMENT_ID, $letter->fresh()->status_id);
    }

    public function test_other_roles_cannot_approve_or_release_a_gl(): void
    {
        $this->seed();
        $letter = GuaranteeLetter::query()->where('status_id', Status::FOR_REVIEW_BY_CRD_ID)->firstOrFail();
        $user = User::factory()->create();
        $user->assignRole('voucher creation');
        $this->actingAs($user, 'api');

        $this->postJson("/api/v1/guarantee-letters/{$letter->id}/actions", ['action' => 'approve_gl'])
            ->assertForbidden();
        $this->assertSame(Status::FOR_REVIEW_BY_CRD_ID, $letter->fresh()->status_id);

        $encoder = User::factory()->create();
        $encoder->assignRole('encoder');
        $this->actingAs($encoder, 'api');
        $this->getJson("/api/v1/guarantee-letters/{$letter->id}")
            ->assertOk()->assertJsonPath('data.creation_action', null);
        $this->postJson("/api/v1/guarantee-letters/{$letter->id}/actions", ['action' => 'approve_gl'])
            ->assertForbidden();

        $approved = GuaranteeLetter::query()->where('status_id', Status::APPROVED_ID)->firstOrFail();
        $this->postJson("/api/v1/guarantee-letters/{$approved->id}/actions", ['action' => 'release_gl'])
            ->assertForbidden();
        $this->assertSame(Status::APPROVED_ID, $approved->fresh()->status_id);

        foreach (['glc admin', 'glc superadmin'] as $role) {
            $staff = User::factory()->create();
            $staff->assignRole($role);
            $this->actingAs($staff, 'api');

            $this->getJson("/api/v1/guarantee-letters/{$letter->id}")
                ->assertOk()->assertJsonPath('data.creation_action', null);
            $this->postJson("/api/v1/guarantee-letters/{$letter->id}/actions", ['action' => 'approve_gl'])
                ->assertForbidden();
            $this->getJson("/api/v1/guarantee-letters/{$approved->id}")
                ->assertOk()->assertJsonPath('data.creation_action', null);
            $this->postJson("/api/v1/guarantee-letters/{$approved->id}/actions", ['action' => 'release_gl'])
                ->assertForbidden();
        }
    }

    public function test_admin_can_approve_selected_review_letters_together(): void
    {
        $this->seed();
        $letters = GuaranteeLetter::query()->where('status_id', Status::FOR_REVIEW_BY_CRD_ID)->limit(2)->get();
        $staff = User::factory()->create();
        $staff->assignRole('admin');
        $this->actingAs($staff, 'api');

        $this->postJson('/api/v1/gl_creation/reviewed/approve-selected', ['gl_ids' => $letters->pluck('id')->all()])
            ->assertOk()->assertJsonPath('approved_count', 2);

        foreach ($letters as $letter) {
            $letter->refresh();
            $this->assertSame(Status::APPROVED_ID, $letter->status_id);
            $this->assertSame($staff->id, $letter->reviewed_by_creation);
            $this->assertSame($staff->id, $letter->approved_by);
            $this->assertNotNull($letter->date_reviewed_by_creation);
            $this->assertNotNull($letter->date_approved);
        }
    }

    public function test_bulk_approval_rejects_invalid_selections_and_rolls_back_the_whole_batch(): void
    {
        $this->seed();
        $letter = GuaranteeLetter::query()->where('status_id', Status::FOR_REVIEW_BY_CRD_ID)->firstOrFail();
        $alreadyApproved = GuaranteeLetter::query()->where('status_id', Status::APPROVED_ID)->firstOrFail();
        $staff = User::factory()->create();
        $staff->assignRole('admin');
        $this->actingAs($staff, 'api');
        $endpoint = '/api/v1/gl_creation/reviewed/approve-selected';

        $this->postJson($endpoint, ['gl_ids' => []])->assertUnprocessable()->assertJsonValidationErrors('gl_ids');
        $this->postJson($endpoint, ['gl_ids' => [$letter->id, $letter->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('gl_ids.0');
        $this->postJson($endpoint, ['gl_ids' => [$letter->id, $alreadyApproved->id]])->assertConflict();
        $this->assertSame(Status::FOR_REVIEW_BY_CRD_ID, $letter->fresh()->status_id);
        $this->assertNull($letter->approved_by);

        $encoder = User::factory()->create();
        $encoder->assignRole('encoder');
        $this->actingAs($encoder, 'api');
        $this->postJson($endpoint, ['gl_ids' => [$letter->id]])->assertForbidden();
        $this->assertSame(Status::FOR_REVIEW_BY_CRD_ID, $letter->fresh()->status_id);
    }

    public function test_admin_can_release_selected_approved_letters_together(): void
    {
        $this->seed();
        $letters = GuaranteeLetter::query()->where('status_id', Status::APPROVED_ID)->limit(2)->get();
        $staff = User::factory()->create();
        $staff->assignRole('admin');
        $this->actingAs($staff, 'api');

        $this->postJson('/api/v1/gl_creation/approval/release-selected', ['gl_ids' => $letters->pluck('id')->all()])
            ->assertOk()->assertJsonPath('released_count', 2);

        foreach ($letters as $letter) {
            $letter->refresh();
            $this->assertSame(Status::RELEASED_ID, $letter->status_id);
            $this->assertSame($staff->id, $letter->released_by);
            $this->assertNotNull($letter->date_released);
        }
    }

    public function test_bulk_release_rejects_invalid_selections_and_rolls_back_the_whole_batch(): void
    {
        $this->seed();
        $letter = GuaranteeLetter::query()->where('status_id', Status::APPROVED_ID)->firstOrFail();
        $alreadyReleased = GuaranteeLetter::query()->where('status_id', Status::RELEASED_ID)->firstOrFail();
        $staff = User::factory()->create();
        $staff->assignRole('admin');
        $this->actingAs($staff, 'api');
        $endpoint = '/api/v1/gl_creation/approval/release-selected';

        $this->postJson($endpoint, ['gl_ids' => []])->assertUnprocessable()->assertJsonValidationErrors('gl_ids');
        $this->postJson($endpoint, ['gl_ids' => [$letter->id, $letter->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('gl_ids.0');
        $this->postJson($endpoint, ['gl_ids' => [$letter->id, $alreadyReleased->id]])->assertConflict();
        $this->assertSame(Status::APPROVED_ID, $letter->fresh()->status_id);
        $this->assertNull($letter->released_by);

        $encoder = User::factory()->create();
        $encoder->assignRole('encoder');
        $this->actingAs($encoder, 'api');
        $this->postJson($endpoint, ['gl_ids' => [$letter->id]])->assertForbidden();
        $this->assertSame(Status::APPROVED_ID, $letter->fresh()->status_id);
    }
}
