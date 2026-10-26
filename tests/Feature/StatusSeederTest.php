<?php

namespace Tests\Feature;

use App\Http\Requests\StatusRequest;
use App\Http\Resources\StatusResource;
use App\Models\Status;
use Database\Seeders\StatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StatusSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_statuses_is_repeatable_and_preserves_inactive_statuses(): void
    {
        $this->seed(StatusSeeder::class);

        $this->assertDatabaseCount('statuses', 17);
        $this->assertSame([
            'For Review by CRD',
            'Approved',
            'Released',
            'On Hold',
            'Cancelled by CRD',
            'For Endorsement',
            'Awaiting SOA',
            'For Pullout',
            'For Creation',
            'For Compliance',
            'For Review by RMCD',
            'On Process',
            'For Obligation',
            'For Pre-Audit',
            'For Crediting',
            'Paid',
            'Cancelled by RMCD',
        ], Status::query()->orderBy('id')->pluck('name')->all());
        $this->assertSame(range(1, 17), [
            Status::FOR_REVIEW_BY_CRD_ID,
            Status::APPROVED_ID,
            Status::RELEASED_ID,
            Status::ON_HOLD_ID,
            Status::CANCELLED_BY_CRD_ID,
            Status::FOR_ENDORSEMENT_ID,
            Status::AWAITING_SOA_ID,
            Status::FOR_PULLOUT_ID,
            Status::FOR_CREATION_ID,
            Status::FOR_COMPLIANCE_ID,
            Status::FOR_REVIEW_BY_RMCD_ID,
            Status::ON_PROCESS_ID,
            Status::FOR_OBLIGATION_ID,
            Status::FOR_PRE_AUDIT_ID,
            Status::FOR_CREDITING_ID,
            Status::PAID_ID,
            Status::CANCELLED_BY_RMCD_ID,
        ]);
        $this->assertSame('Released', Status::query()->findOrFail(Status::RELEASED_ID)->name);
        $this->assertSame('For Endorsement', Status::query()->findOrFail(Status::FOR_ENDORSEMENT_ID)->name);
        $this->assertFalse(Status::query()->where('name', 'Created')->exists());
        $status = Status::query()->where('name', 'Awaiting SOA')->firstOrFail();
        $status->update(['is_active' => false]);
        $released = Status::query()->findOrFail(Status::RELEASED_ID);
        $released->update(['name' => 'Released to Client']);

        $this->seed(StatusSeeder::class);

        $this->assertDatabaseCount('statuses', 17);
        $this->assertFalse($status->fresh()->is_active);
        $this->assertSame('Released to Client', $released->fresh()->name);
    }

    public function test_status_input_and_output_match_the_statuses_table(): void
    {
        $request = new StatusRequest;
        $this->assertTrue(Validator::make([
            'name' => 'Awaiting SOA',
            'is_active' => false,
        ], $request->rules())->passes());
        $this->assertFalse(Validator::make([
            'name' => 'Awaiting SOA',
            'is_active' => 'invalid',
        ], $request->rules())->passes());

        $status = Status::query()->create(['name' => 'Awaiting SOA', 'is_active' => false]);
        $resource = (new StatusResource($status))->toArray(new Request);

        $this->assertFalse($resource['is_active']);
        $this->assertArrayNotHasKey('description', $resource);
    }
}
