<?php

namespace Tests\Feature;

use App\Http\Requests\GuaranteeLetterRequest;
use App\Models\Branch;
use App\Models\GuaranteeLetter;
use App\Models\Office;
use App\Models\ServiceProvider;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class GuaranteeLetterReferenceTablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_gl_creation_reference_tables_and_foreign_keys_exist(): void
    {
        $this->assertTrue(Schema::hasTable('offices'));
        $this->assertTrue(Schema::hasColumn('offices', 'region_id'));
        $this->assertTrue(Schema::hasColumn('offices', 'name'));
        $this->assertTrue(Schema::hasTable('swos'));
        $this->assertTrue(Schema::hasColumn('swos', 'name'));
        $this->assertTrue(Schema::hasTable('referring_parties'));
        $this->assertTrue(Schema::hasColumn('referring_parties', 'name'));

        $this->assertTrue(Schema::hasForeignKey('guarantee_letters', ['office_id']));
        $this->assertTrue(Schema::hasForeignKey('guarantee_letters', ['swo_id']));
        $this->assertTrue(Schema::hasForeignKey('guarantee_letters', ['referring_party_id']));
        $this->assertTrue(Schema::hasForeignKey('guarantee_letters', ['received_by']));
        foreach (['received_by', 'date_reviewed_by_creation', 'date_endorsed', 'creation_remarks', 'processing_remarks', 'reviewed_by_rmcd', 'date_cancelled_by_creation', 'date_cancelled_by_rmcd'] as $column) {
            $this->assertTrue(Schema::hasColumn('guarantee_letters', $column), $column);
        }
        foreach (['encoded_by', 'date_created', 'date_created_reviewed', 'date_recieving_endorsement', 'gl_creation_remarks', 'remarks', 'reviewed_by', 'date_cancelled', 'date_cancelled_by_processing'] as $column) {
            $this->assertFalse(Schema::hasColumn('guarantee_letters', $column), $column);
        }
    }

    public function test_office_can_have_no_region_and_has_guarantee_letter_relationships(): void
    {
        $office = Office::create(['name' => 'Test Office']);

        $this->assertNull($office->region_id);
        $this->assertSame($office->id, $office->fresh()->id);
        $this->assertInstanceOf(Office::class, (new GuaranteeLetter)->office()->getRelated());
        $this->assertInstanceOf(GuaranteeLetter::class, $office->guaranteeLetters()->getRelated());
    }

    public function test_guarantee_letters_keep_workflow_dates_without_legacy_compliance_columns(): void
    {
        $this->assertTrue(Schema::hasTable('compliances'));
        $this->assertTrue(Schema::hasColumn('guarantee_letters', 'date_fwd_to_reviewer'));
        $this->assertFalse(Schema::hasColumn('guarantee_letters', 'date_fwd_to_signatory'));
        $this->assertFalse(Schema::hasColumn('guarantee_letters', 'date_returned_by_signatory'));

        foreach ([
            'compliance_remarks_from_voucher',
            'date_fwd_compliance_to_encoder',
            'date_rcvd_compliance_from_encoder',
            'compliance_received_date',
            'compliance_forwarded_date',
            'compliance_remarks',
            'compliance_reviewer',
            'date_compliance_voucher',
            'compliance_box_a',
            'date_compliance_box_a',
            'compliance_box_b',
            'date_compliance_box_b',
            'compliance_box_c',
            'date_compliance_box_c',
            'compliance_box_d',
            'date_compliance_box_d',
        ] as $column) {
            $this->assertFalse(Schema::hasColumn('guarantee_letters', $column), $column);
        }
    }

    public function test_guarantee_letter_request_rejects_legacy_fields(): void
    {
        $legacyFields = [
            'compliance_received_date', 'compliance_forwarded_date', 'compliance_remarks',
            'date_created', 'date_created_reviewed', 'date_recieving_endorsement',
            'encoded_by', 'reviewed_by', 'date_cancelled', 'date_cancelled_by_processing',
            'gl_creation_remarks', 'remarks', 'date_fwd_to_signatory', 'date_returned_by_signatory',
        ];
        $rules = array_intersect_key((new GuaranteeLetterRequest)->rules(), array_flip($legacyFields));

        foreach ($legacyFields as $field) {
            $this->assertTrue(Validator::make([$field => 'legacy value'], $rules)->errors()->has($field));
        }
    }

    public function test_guarantee_letter_api_saves_and_returns_current_creation_fields(): void
    {
        $this->seed();
        $sample = GuaranteeLetter::query()->where('gl_no', 'GL-2026-0001')->firstOrFail();
        $this->actingAs($sample->creator, 'api');
        $payload = $sample->only([
            'office_id', 'client_id', 'beneficiary_id', 'address', 'relationship_id',
            'service_provider_id', 'branch_id', 'assistance_type_id',
            'gl_amount', 'gl_validity', 'signatory_id', 'swo_id', 'created_by',
        ]);
        $payload['gl_no'] = 'GL-2026-API-0001';
        $payload['gl_date'] = '2026-09-20';
        $payload['category_id'] = $sample->category_id;
        $payload['creation_remarks'] = 'Created through API';
        $payload['processing_remarks'] = 'Processing note';
        $payload['received_by'] = $sample->created_by;

        $response = $this->postJson('/api/v1/guarantee-letters', $payload)
            ->assertCreated()
            ->assertJsonPath('data.office_id', $sample->office_id)
            ->assertJsonPath('data.status_id', Status::FOR_REVIEW_BY_CRD_ID)
            ->assertJsonPath('data.service_provider_id', $sample->service_provider_id)
            ->assertJsonPath('data.swo_id', $sample->swo_id)
            ->assertJsonPath('data.received_by', $sample->created_by)
            ->assertJsonPath('data.creation_remarks', 'Created through API')
            ->assertJsonPath('data.processing_remarks', 'Processing note')
            ->assertJsonMissingPath('data.date_fwd_to_signatory')
            ->assertJsonMissingPath('data.date_returned_by_signatory');

        $createdNumber = $response->json('data.gl_no');
        $this->assertMatchesRegularExpression('/^GL-\d{4}-\d{5}$/', $createdNumber);

        $this->assertDatabaseHas('guarantee_letters', [
            'gl_no' => $createdNumber,
            'office_id' => $sample->office_id,
            'received_by' => $sample->created_by,
            'creation_remarks' => 'Created through API',
            'processing_remarks' => 'Processing note',
        ]);

        $workflowPayload = array_merge($payload, [
            'status_id' => Status::FOR_REVIEW_BY_CRD_ID,
            'reviewed_by_creation' => $sample->created_by,
            'date_reviewed_by_creation' => '2026-09-21 09:00:00',
            'approved_by' => $sample->created_by,
            'date_approved' => '2026-09-21 10:00:00',
            'released_by' => $sample->created_by,
            'date_released' => '2026-09-21 11:00:00',
            'endorsed_by' => $sample->created_by,
            'date_endorsed' => '2026-09-21 12:00:00',
            'onhold_by' => $sample->created_by,
            'date_onhold' => '2026-09-21 13:00:00',
            'cancelled_by_creation' => $sample->created_by,
            'date_cancelled_by_creation' => '2026-09-21 14:00:00',
            'reviewed_by_rmcd' => $sample->created_by,
            'cancelled_by_rmcd' => $sample->created_by,
            'date_cancelled_by_rmcd' => '2026-09-21 15:00:00',
        ]);
        $updated = $this->putJson('/api/v1/guarantee-letters/'.$response->json('data.id'), $workflowPayload)->assertOk();
        foreach (['reviewed_by_creation', 'approved_by', 'released_by', 'endorsed_by', 'onhold_by', 'cancelled_by_creation', 'reviewed_by_rmcd', 'cancelled_by_rmcd'] as $column) {
            $updated->assertJsonPath('data.'.$column, $sample->created_by);
        }
        foreach (['date_reviewed_by_creation', 'date_approved', 'date_released', 'date_endorsed', 'date_onhold', 'date_cancelled_by_creation', 'date_cancelled_by_rmcd'] as $column) {
            $this->assertSame('2026-09-21', substr($updated->json('data.'.$column), 0, 10));
        }
        $updated->assertJsonMissingPath('data.date_cancelled_by_processing');

        $invalidWorkflowPayload = $workflowPayload;
        $invalidWorkflowPayload['date_cancelled_by_rmcd'] = 'invalid date';
        $this->putJson('/api/v1/guarantee-letters/'.$response->json('data.id'), $invalidWorkflowPayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_cancelled_by_rmcd');

        $missingCategory = $payload;
        unset($missingCategory['category_id']);
        $this->postJson('/api/v1/guarantee-letters', $missingCategory)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_id');

        $sample->creator->update(['office_id' => null]);
        $this->postJson('/api/v1/guarantee-letters', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('office_id');
    }

    public function test_creation_respects_provider_status_optional_branch_and_receipt_timing(): void
    {
        $this->seed();
        $sample = GuaranteeLetter::query()->where('gl_no', 'GL-2026-0001')->firstOrFail();
        $this->actingAs($sample->creator, 'api');
        $payload = $sample->only([
            'client_id', 'beneficiary_id', 'address', 'relationship_id',
            'service_provider_id', 'assistance_type_id',
            'gl_amount', 'gl_validity', 'signatory_id', 'swo_id', 'category_id',
        ]);
        $payload['gl_date'] = '2026-09-20';

        $provider = ServiceProvider::query()->findOrFail($sample->service_provider_id);
        $provider->update([
            'addressee' => 'Dr. Maria Santos',
            'designation' => 'Medical Director',
            'status' => 'Suspended',
            'sp_remarks' => 'Pending review',
        ]);

        $this->getJson('/api/v1/service-providers/dropdown')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $provider->id,
                'name' => $provider->name,
                'addressee' => 'Dr. Maria Santos',
                'designation' => 'Medical Director',
                'status' => 'Suspended',
                'sp_remarks' => 'Pending review',
            ]);

        $this->postJson('/api/v1/guarantee-letters', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('service_provider_id');

        $provider->update(['status' => 'Active']);
        $this->postJson('/api/v1/guarantee-letters', $payload)
            ->assertCreated()
            ->assertJsonPath('data.branch_id', null)
            ->assertJsonPath('data.date_received_gl', null);

        $this->postJson('/api/v1/guarantee-letters', $payload + ['date_received_gl' => '2026-09-20'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_received_gl');

        $otherBranch = Branch::query()->where('service_provider_id', '!=', $provider->id)->firstOrFail();
        $this->postJson('/api/v1/guarantee-letters', $payload + ['branch_id' => $otherBranch->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('branch_id');

        $this->getJson('/api/v1/branches/dropdown')
            ->assertOk()
            ->assertJsonFragment(['id' => $otherBranch->id, 'service_provider_id' => $otherBranch->service_provider_id]);
    }
}
