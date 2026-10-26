<?php

namespace Tests\Feature;

use App\Http\Resources\BatchResource;
use App\Models\AssistanceType;
use App\Models\Batch;
use App\Models\Beneficiary;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Client;
use App\Models\Compliance;
use App\Models\ComplianceType;
use App\Models\FundSource;
use App\Models\GuaranteeLetter;
use App\Models\Office;
use App\Models\PsgcRegion;
use App\Models\Relationship;
use App\Models\ServiceProvider;
use App\Models\Signatory;
use App\Models\Status;
use App\Models\Swo;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VoucherCreationTest extends TestCase
{
    use RefreshDatabase;

    private User $voucherCreator;

    private User $assignedReviewer;

    private FundSource $fundSource;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => 'voucher-creation-tests-only-secret-0123456789']);

        Role::query()->firstOrCreate(['name' => 'voucher creation']);
        Role::query()->firstOrCreate(['name' => 'reviewer']);
        Role::query()->firstOrCreate(['name' => 'box a reviewer']);
        Role::query()->firstOrCreate(['name' => 'compliance']);
        Status::query()->firstOrCreate(['name' => 'For Creation']);
        Status::query()->firstOrCreate(['name' => 'For Review by RMCD']);
        Status::query()->firstOrCreate(['name' => 'On Process']);
        Status::query()->firstOrCreate(['name' => 'For Obligation']);
        Status::query()->firstOrCreate(['name' => 'For Pre-Audit']);
        Status::query()->firstOrCreate(['name' => 'For Crediting']);
        Status::query()->firstOrCreate(['name' => 'Paid']);
        Status::query()->firstOrCreate(['name' => 'For Compliance']);
        ComplianceType::query()->firstOrCreate(['name' => 'Reviewer to Voucher Creation']);
        ComplianceType::query()->firstOrCreate(['name' => 'Box A Reviewer to Reviewer']);
        ComplianceType::query()->firstOrCreate(['name' => 'Budget to Compliance Officer']);
        ComplianceType::query()->firstOrCreate(['name' => 'Accounting to Compliance Officer']);
        ComplianceType::query()->firstOrCreate(['name' => 'Cash to Reviewer']);

        $this->voucherCreator = User::factory()->create(['name' => 'Frans Audrey S Sandicon', 'position' => 'Administrative Assistant II']);
        $this->voucherCreator->assignRole('voucher creation');
        $this->assignedReviewer = User::factory()->create(['name' => 'Assigned Reviewer', 'position' => 'Administrative Officer IV', 'is_active' => true]);
        $this->assignedReviewer->assignRole('reviewer');
        $this->actingAs($this->voucherCreator, 'api');

        $this->branch = $this->createBranch();
        $this->fundSource = FundSource::query()->create(['name' => 'Voucher Test Fund', 'amount' => '1000000.00']);
    }

    public function test_it_creates_a_voucher_batch_for_one_guarantee_letter(): void
    {
        $letter = $this->letter('5000.25');

        $response = $this->createVoucher([$letter->id]);

        $response->assertCreated()
            ->assertJsonPath('data.voucher.voucher_no', 'FASS-'.now('Asia/Manila')->format('m-d').'-0001')
            ->assertJsonPath('data.total_amount', '5000.25')
            ->assertJsonPath('data.reviewer.id', $this->assignedReviewer->id)
            ->assertJsonCount(1, 'data.gls')
            ->assertJsonPath('data.gls.0.id', $letter->id);

        $batch = Batch::query()->sole();
        $this->assertSame($this->voucherCreator->id, $batch->created_by);
        $this->assertSame($this->assignedReviewer->id, $batch->reviewer_id);
        $this->assertSame('5000.25', $batch->total_amount);
        $this->assertTrue($batch->gls->sole()->is($letter));
        $this->assertSame('For Review by RMCD', $letter->fresh()->status->name);
        $this->assertArrayNotHasKey('status', (new BatchResource($batch->load('gls.status')))->resolve());
        $this->assertSame($this->fundSource->id, $letter->fresh()->fund_source_id);
        $this->assertSame(1, Batch::query()->atStatus('For Review by RMCD')->count());
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => GuaranteeLetter::class,
            'subject_id' => $letter->id,
            'causer_id' => $this->voucherCreator->id,
            'event' => 'create_voucher',
        ]);
        $this->assertSame(1, Activity::query()->where('event', 'create_voucher')->count());
    }

    public function test_reviewer_options_only_include_reviewers(): void
    {
        $inactiveReviewer = User::factory()->create(['is_active' => false]);
        $inactiveReviewer->assignRole('reviewer');
        $otherUser = User::factory()->create(['is_active' => true]);
        $otherUser->assignRole('voucher creation');

        $this->getJson('/api/v1/voucher-creation/reviewer-options')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $this->assignedReviewer->id, 'name' => 'Assigned Reviewer', 'position' => 'Administrative Officer IV'])
            ->assertJsonFragment(['id' => $inactiveReviewer->id]);

        $this->actingAs($otherUser, 'api');
        $this->getJson('/api/v1/voucher-creation/reviewer-options')->assertOk();
    }

    public function test_voucher_creation_requires_a_reviewer_user(): void
    {
        $letter = $this->letter('5000.25');
        $endpoint = '/api/v1/voucher-creation/batches';
        $payload = ['gl_ids' => [$letter->id], 'fund_source_id' => $this->fundSource->id];

        $this->postJson($endpoint, $payload)->assertUnprocessable()->assertJsonValidationErrors('reviewer_id');
        $this->postJson($endpoint, [...$payload, 'reviewer_id' => $this->voucherCreator->id])
            ->assertUnprocessable()->assertJsonValidationErrors('reviewer_id');

        $this->assertDatabaseCount('batches', 0);
    }

    public function test_it_generates_voucher_numbers_per_creator_and_day(): void
    {
        $date = now('Asia/Manila')->format('m-d');

        $first = $this->letter('5000.25');
        $this->createVoucher([$first->id])
            ->assertCreated()
            ->assertJsonPath('data.voucher.voucher_no', "FASS-{$date}-0001");

        $second = $this->letter('6000.25');
        $this->createVoucher([$second->id])
            ->assertCreated()
            ->assertJsonPath('data.voucher.voucher_no', "FASS-{$date}-0002");

        $otherCreator = User::factory()->create(['name' => 'Lian P Dela M']);
        $otherCreator->assignRole('voucher creation');
        $this->actingAs($otherCreator, 'api');

        $third = $this->letter('7000.25');
        $this->createVoucher([$third->id])
            ->assertCreated()
            ->assertJsonPath('data.voucher.voucher_no', "LPDM-{$date}-0001");
    }

    public function test_it_lists_created_voucher_batches(): void
    {
        $letter = $this->letter('5000.25');
        $this->createVoucher([$letter->id])->assertCreated();

        $this->getJson('/api/v1/voucher-creation/batches?per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.gls.0.id', $letter->id)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_voucher_creator_only_sees_their_own_batches(): void
    {
        $letter = $this->letter('5000.25');
        $this->createVoucher([$letter->id])->assertCreated();
        Batch::factory()->create();

        $this->getJson('/api/v1/voucher-creation/batches')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_all_records_shows_creators_their_own_vouchers_and_reviewers_every_voucher(): void
    {
        Role::query()->firstOrCreate(['name' => 'reviewer']);
        $ownBatch = Batch::factory()->create(['created_by' => $this->voucherCreator->id]);
        Batch::factory()->create();

        $this->getJson('/api/v1/voucher-creation/batches/all')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $ownBatch->id);

        $reviewer = User::factory()->create();
        $reviewer->assignRole('reviewer');
        $this->actingAs($reviewer, 'api')
            ->getJson('/api/v1/voucher-creation/batches/all')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->actingAs(User::factory()->create(), 'api')
            ->getJson('/api/v1/voucher-creation/batches/all')
            ->assertForbidden();
    }

    public function test_voucher_creator_cannot_open_another_users_batch(): void
    {
        $otherBatch = Batch::factory()->create();

        $this->getJson('/api/v1/voucher-creation/batches/'.$otherBatch->id)
            ->assertForbidden();
    }

    public function test_admin_created_voucher_view_is_still_limited_to_the_admin_users_batches(): void
    {
        Role::query()->firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Batch::factory()->create(['created_by' => $admin->id]);
        Batch::factory()->create(['created_by' => $this->voucherCreator->id]);

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/voucher-creation/batches')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_user_without_voucher_creation_access_cannot_list_batches(): void
    {
        $this->actingAs(User::factory()->create(), 'api')
            ->getJson('/api/v1/voucher-creation/batches')
            ->assertForbidden();
    }

    public function test_it_creates_one_batch_for_multiple_compatible_guarantee_letters(): void
    {
        $first = $this->letter('5000.25');
        $second = $this->letter('2500.75');

        $this->createVoucher([$first->id, $second->id])->assertCreated();

        $batch = Batch::query()->sole();
        $this->assertSame('7501.00', $batch->total_amount);
        $this->assertSame([$first->id, $second->id], $batch->gls()->orderBy('guarantee_letters.id')->pluck('guarantee_letters.id')->all());
        $this->assertSame('For Review by RMCD', $first->fresh()->status->name);
        $this->assertSame('For Review by RMCD', $second->fresh()->status->name);
    }

    public function test_tenth_voucher_candidate_is_on_the_first_page(): void
    {
        $letterIds = collect(range(1, 11))
            ->map(fn (): int => $this->letter('100.00')->id)
            ->all();

        $statusId = Status::query()->where('name', 'For Creation')->sole()->id;
        $firstPage = $this->getJson("/api/v1/guarantee-letters?filter[status_id]={$statusId}&per_page=10&page=1")
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.from', 1)
            ->assertJsonPath('meta.to', 10);
        $secondPage = $this->getJson("/api/v1/guarantee-letters?filter[status_id]={$statusId}&per_page=10&page=2")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertSame(array_slice(array_reverse($letterIds), 0, 10), collect($firstPage->json('data'))->pluck('id')->all());
        $this->assertSame($letterIds[0], $secondPage->json('data.0.id'));
    }

    public function test_voucher_keeps_the_selected_guarantee_letter_order(): void
    {
        $first = $this->letter('5000.00');
        $second = $this->letter('2500.00');

        $response = $this->createVoucher([$second->id, $first->id])->assertCreated();

        $this->assertSame([$second->id, $first->id], collect($response->json('data.gls'))->pluck('id')->all());
        $this->assertSame(
            [$second->id, $first->id],
            DB::table('batch_gl')->orderBy('position')->pluck('gl_id')->all(),
        );
    }

    public function test_voucher_creation_accepts_ten_letters_and_rejects_eleven(): void
    {
        $letterIds = collect(range(1, 11))
            ->map(fn (): int => $this->letter('100.00')->id)
            ->all();

        $this->createVoucher($letterIds)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('gl_ids');

        $this->assertDatabaseCount('batches', 0);

        $this->createVoucher(array_slice($letterIds, 0, 10))
            ->assertCreated()
            ->assertJsonCount(10, 'data.gls');
    }

    public function test_it_rejects_letters_that_are_not_ready_for_voucher_creation(): void
    {
        $letter = $this->letter('5000.00', ['status_id' => Status::query()->where('name', 'On Process')->sole()->id]);

        $this->createVoucher([$letter->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('gl_ids');

        $this->assertDatabaseCount('vouchers', 0);
        $this->assertDatabaseCount('batches', 0);
    }

    public function test_it_rejects_a_guarantee_letter_already_in_a_batch(): void
    {
        $letter = $this->letter('5000.00');
        $existingBatch = Batch::factory()->create();
        $existingBatch->syncGls([$letter->id]);

        $this->createVoucher([$letter->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('gl_ids');

        $this->assertDatabaseCount('vouchers', 1);
        $this->assertDatabaseCount('batches', 1);
    }

    public function test_it_creates_a_voucher_for_a_guarantee_letter_without_an_obligation_amount(): void
    {
        $letter = $this->letter('5000.00', ['obligation_amount' => null]);

        $this->createVoucher([$letter->id])
            ->assertCreated();

        $this->assertDatabaseCount('vouchers', 1);
        $this->assertDatabaseCount('batches', 1);
    }

    public function test_it_rejects_a_conflicting_existing_fund_source(): void
    {
        $otherFundSource = FundSource::query()->create(['name' => 'Other Voucher Test Fund', 'amount' => '1000000.00']);
        $letter = $this->letter('5000.00', ['fund_source_id' => $otherFundSource->id]);

        $this->createVoucher([$letter->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fund_source_id');

        $this->assertDatabaseCount('vouchers', 0);
        $this->assertDatabaseCount('batches', 0);
    }

    public function test_it_rejects_guarantee_letters_from_different_provider_branches(): void
    {
        $first = $this->letter('5000.00');
        $second = $this->letter('2500.00', ['branch_id' => $this->createBranch('Second Voucher Test Branch')->id]);

        $this->createVoucher([$first->id, $second->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('gl_ids');

        $this->assertDatabaseCount('vouchers', 0);
        $this->assertDatabaseCount('batches', 0);
    }

    public function test_it_requires_an_authorised_user_and_an_active_fund_source(): void
    {
        $letter = $this->letter('5000.00');
        $this->actingAs(User::factory()->create(), 'api');

        $this->createVoucher([$letter->id])->assertForbidden();

        $this->actingAs($this->voucherCreator, 'api');
        $this->fundSource->update(['is_active' => false]);
        $this->createVoucher([$letter->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fund_source_id');
    }

    public function test_reviewer_forwards_every_included_letter_to_box_a_without_overwriting_voucher_remarks(): void
    {
        $first = $this->letter('5000.00');
        $second = $this->letter('2500.00');
        $this->createVoucher([$first->id, $second->id])->assertCreated();
        $batch = Batch::query()->sole();
        $reviewer = $this->reviewer();

        $this->getJson('/api/v1/voucher-review/batches')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status.name', 'For Review by RMCD');

        $this->postJson('/api/v1/voucher-review/batches/'.$batch->id.'/forward-to-box-a', [
            'date_fwd_to_dc' => '2026-09-30',
            'remarks' => 'Sent to Box A.',
        ])->assertOk()->assertJsonPath('data.status.name', 'On Process');

        foreach ([$first, $second] as $letter) {
            $fresh = $letter->fresh();
            $this->assertSame('On Process', $fresh->status->name);
            $this->assertSame('2026-09-30', $fresh->date_fwd_to_dc->toDateString());
            $this->assertSame($reviewer->id, $fresh->reviewed_by_rmcd);
        }
        $this->assertSame('Voucher creation test.', $batch->fresh()->remarks);
        $this->assertSame(2, Activity::query()->where('event', 'forward_to_box_a')->where('subject_type', GuaranteeLetter::class)->count());
        $this->getJson('/api/v1/voucher-review/batches')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_voucher_forwarding_accepts_the_current_manila_date_before_utc_midnight(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 17:00:00', 'UTC'));

        try {
            $letter = $this->letter('5000.00');
            $this->createVoucher([$letter->id])->assertCreated();
            $batch = Batch::query()->sole();
            $this->reviewer();

            $this->postJson('/api/v1/voucher-review/batches/'.$batch->id.'/forward-to-box-a', [
                'date_fwd_to_dc' => '2026-10-16',
            ])->assertOk();
        } finally {
            $this->travelBack();
        }
    }

    public function test_on_process_queue_lists_forwarded_vouchers_without_including_mixed_status_batches(): void
    {
        $first = $this->letter('5000.00');
        $second = $this->letter('2500.00');
        $this->createVoucher([$first->id, $second->id])->assertCreated();
        $batch = Batch::query()->sole();

        $this->reviewer();
        $this->getJson('/api/v1/outgoing/on-process/batches')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/outgoing/on-process/batches/'.$batch->id)->assertNotFound();

        $this->reviewer();
        $this->postJson('/api/v1/voucher-review/batches/'.$batch->id.'/forward-to-box-a', [
            'date_fwd_to_dc' => '2026-09-30',
        ])->assertOk();

        $this->actingAs($this->reviewer(), 'api');
        $this->getJson('/api/v1/outgoing/on-process/batches?search=FASS-'.now('Asia/Manila')->format('m-d').'-0001')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->boxAReviewer();
        $this->getJson('/api/v1/outgoing/on-process/batches')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $batch->id);
        $this->postJson('/api/v1/outgoing/on-process/batches/'.$batch->id.'/approve-box-a')->assertOk();

        $this->actingAs($this->reviewer(), 'api');
        $this->getJson('/api/v1/outgoing/on-process/batches?search=FASS-'.now('Asia/Manila')->format('m-d').'-0001')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $batch->id)
            ->assertJsonPath('data.0.status.name', 'On Process');
        $this->getJson('/api/v1/outgoing/on-process/batches/'.$batch->id)
            ->assertOk()
            ->assertJsonPath('data.id', $batch->id)
            ->assertJsonPath('data.status.name', 'On Process');
        $this->getJson('/api/v1/outgoing/on-process/batches?search=missing')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $second->status()->associate(Status::query()->where('name', 'For Review by RMCD')->sole());
        $second->save();

        $this->getJson('/api/v1/outgoing/on-process/batches')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/outgoing/on-process/batches/'.$batch->id)->assertNotFound();
    }

    public function test_box_a_forwarding_rejects_a_second_action(): void
    {
        $letter = $this->letter('5000.00');
        $this->createVoucher([$letter->id])->assertCreated();
        $this->reviewer();
        $endpoint = '/api/v1/voucher-review/batches/'.Batch::query()->sole()->id.'/forward-to-box-a';

        $this->postJson($endpoint, ['date_fwd_to_dc' => '2026-09-30'])->assertOk();
        $this->postJson($endpoint, ['date_fwd_to_dc' => '2026-09-30'])->assertConflict();
        $this->assertSame(1, Activity::query()->where('event', 'forward_to_box_a')->where('subject_type', GuaranteeLetter::class)->count());
    }

    public function test_box_a_forwarding_rejects_mixed_letter_statuses_without_changing_any_letter(): void
    {
        $first = $this->letter('5000.00');
        $second = $this->letter('2500.00');
        $this->createVoucher([$first->id, $second->id])->assertCreated();
        $second->status()->associate(Status::query()->where('name', 'On Process')->sole());
        $second->save();
        $this->reviewer();

        $this->postJson('/api/v1/voucher-review/batches/'.Batch::query()->sole()->id.'/forward-to-box-a', [
            'date_fwd_to_dc' => '2026-09-30',
        ])->assertConflict();

        $this->assertNull($first->fresh()->date_fwd_to_dc);
        $this->assertSame('For Review by RMCD', $first->fresh()->status->name);
        $this->getJson('/api/v1/voucher-review/batches')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_box_a_forwarding_requires_reviewer_access_and_a_valid_date(): void
    {
        $letter = $this->letter('5000.00');
        $this->createVoucher([$letter->id])->assertCreated();
        $endpoint = '/api/v1/voucher-review/batches/'.Batch::query()->sole()->id.'/forward-to-box-a';

        $this->postJson($endpoint, ['date_fwd_to_dc' => '2026-09-30'])->assertForbidden();
        $this->reviewer();
        $this->postJson($endpoint, ['date_fwd_to_dc' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('date_fwd_to_dc');
        $this->assertNull($letter->fresh()->date_fwd_to_dc);
    }

    public function test_reviewer_returns_the_entire_voucher_for_compliance(): void
    {
        $first = $this->letter('5000.00');
        $second = $this->letter('2500.00');
        $this->createVoucher([$first->id, $second->id])->assertCreated();
        $batch = Batch::query()->sole();
        $reviewer = $this->reviewer();
        $forwardedDate = now('Asia/Manila')->toDateString();

        $this->postJson('/api/v1/voucher-review/batches/'.$batch->id.'/return-for-compliance', [
            'forwarded_date' => $forwardedDate,
            'items' => [
                ['guarantee_letter_id' => $first->id, 'remarks' => 'Missing receipt.'],
                ['guarantee_letter_id' => $second->id, 'remarks' => 'Correct the provider attachment.'],
            ],
            'remarks' => 'Correct the voucher details.',
            'compliance_type_id' => 999,
        ])->assertOk()->assertJsonPath('data.status.name', 'For Compliance');

        $compliances = Compliance::query()->where('batch_id', $batch->id)->orderBy('guarantee_letter_id')->get();
        $this->assertCount(2, $compliances);
        $this->assertSame([$first->id, $second->id], $compliances->pluck('guarantee_letter_id')->all());
        $this->assertSame(['Missing receipt.', 'Correct the provider attachment.'], $compliances->pluck('remarks')->all());
        $this->assertTrue($compliances->every(fn (Compliance $compliance): bool => $compliance->complianceType->name === 'Reviewer to Voucher Creation'));
        $this->assertTrue($compliances->every(fn (Compliance $compliance): bool => $compliance->returned_at->toDateString() === $forwardedDate && $compliance->resolved_at === null));
        $this->assertSame('Voucher creation test.', $batch->fresh()->remarks);
        $this->assertSame($first->gl_no, Activity::query()
            ->where('event', 'return_for_compliance')
            ->where('subject_type', Batch::class)
            ->sole()
            ->properties['selected_gl_compliances'][0]['gl_no']);

        foreach ([$first, $second] as $letter) {
            $fresh = $letter->fresh();
            $this->assertSame('For Compliance', $fresh->status->name);
            $this->assertNull($fresh->date_fwd_to_dc);
        }

        $this->actingAs($this->voucherCreator, 'api');
        $this->getJson('/api/v1/compliance?kind=voucher')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->assertSame(1, Activity::query()->where('event', 'return_for_compliance')->where('subject_type', Batch::class)->where('causer_id', $reviewer->id)->count());
        $this->actingAs($reviewer, 'api');
        $this->getJson('/api/v1/voucher-review/batches')->assertOk()->assertJsonPath('meta.total', 0);
        $this->postJson('/api/v1/voucher-review/batches/'.$batch->id.'/forward-to-box-a', [
            'date_fwd_to_dc' => $forwardedDate,
        ])->assertConflict();
    }

    public function test_reviewer_can_only_select_an_included_gl_for_voucher_compliance(): void
    {
        $includedLetter = $this->letter('5000.00');
        $outsideLetter = $this->letter('2500.00');
        $this->createVoucher([$includedLetter->id])->assertCreated();
        $batch = Batch::query()->sole();
        $this->reviewer();

        $this->postJson('/api/v1/voucher-review/batches/'.$batch->id.'/return-for-compliance', [
            'forwarded_date' => now('Asia/Manila')->toDateString(),
            'items' => [
                ['guarantee_letter_id' => $outsideLetter->id, 'remarks' => 'Incorrect document.'],
            ],
            'remarks' => 'Correct the voucher details.',
        ])->assertUnprocessable()->assertJsonValidationErrors('items.0.guarantee_letter_id');

        $this->assertDatabaseCount('compliances', 0);
        $this->assertSame('For Review by RMCD', $includedLetter->fresh()->status->name);
    }

    public function test_voucher_creator_resolves_and_resubmits_a_voucher_compliance(): void
    {
        $first = $this->letter('5000.00');
        $second = $this->letter('2500.00');
        $this->createVoucher([$first->id, $second->id])->assertCreated();
        $batch = Batch::query()->sole();
        $returnedDate = now('Asia/Manila')->subDays(2)->toDateString();

        $this->reviewer();
        $this->postJson('/api/v1/voucher-review/batches/'.$batch->id.'/return-for-compliance', [
            'forwarded_date' => $returnedDate,
            'items' => [
                ['guarantee_letter_id' => $first->id, 'remarks' => 'Correct the first GL.'],
                ['guarantee_letter_id' => $second->id, 'remarks' => 'Correct the second GL.'],
            ],
            'remarks' => 'Correct the voucher details.',
        ])->assertOk();

        $compliance = Compliance::query()->where('batch_id', $batch->id)->orderBy('id')->firstOrFail();
        $resolvedDate = now('Asia/Manila')->toDateString();
        $this->actingAs($this->voucherCreator, 'api');

        $this->postJson('/api/v1/compliance/'.$compliance->id.'/resolve', [
            'resolved_date' => $resolvedDate,
            'remarks' => 'Corrected and ready for review.',
        ])->assertNoContent();

        $resolvedCompliance = $compliance->fresh();
        $this->assertSame($resolvedDate, $resolvedCompliance->resolved_at->toDateString());
        $this->assertSame(0, $resolvedCompliance->duration);
        $this->assertSame('Correct the first GL.', $resolvedCompliance->remarks);
        $this->assertSame(2, Compliance::query()->where('batch_id', $batch->id)->whereNotNull('resolved_at')->count());
        $this->assertSame('For Review by RMCD', $first->fresh()->status->name);
        $this->assertSame('For Review by RMCD', $second->fresh()->status->name);
        $this->assertSame(1, Activity::query()->where('event', 'resolve_compliance')->where('subject_id', $compliance->id)->count());
        $this->getJson('/api/v1/compliance?kind=voucher')->assertOk()->assertJsonPath('meta.total', 0);
        $latestResolvedCompliance = Compliance::query()->where('batch_id', $batch->id)->latest('id')->firstOrFail();
        $this->getJson('/api/v1/voucher-creation/batches/'.$batch->id)
            ->assertOk()
            ->assertJsonPath('data.latest_resolved_compliance.id', $latestResolvedCompliance->id)
            ->assertJsonPath('data.latest_resolved_compliance.duration', 0);
        $this->getJson('/api/v1/guarantee-letters/'.$first->id)
            ->assertOk()
            ->assertJsonPath('data.batches.0.latest_resolved_compliance.id', $latestResolvedCompliance->id);
    }

    public function test_reviewer_resolution_returns_box_a_compliance_to_on_process_for_box_a_reviewer(): void
    {
        $letter = $this->letter('5000.00');
        $this->createVoucher([$letter->id])->assertCreated();
        $batch = Batch::query()->sole();
        $returnedDate = now('Asia/Manila')->subDays(2)->toDateString();

        $reviewer = $this->reviewer();
        $this->postJson('/api/v1/voucher-review/batches/'.$batch->id.'/forward-to-box-a', [
            'date_fwd_to_dc' => $returnedDate,
        ])->assertOk();

        $this->boxAReviewer();
        $this->postJson('/api/v1/outgoing/on-process/batches/'.$batch->id.'/return-for-compliance', [
            'forwarded_date' => $returnedDate,
            'items' => [['guarantee_letter_id' => $letter->id, 'remarks' => 'Correct the Box A documents.']],
            'remarks' => 'Correct the Box A documents.',
        ])->assertOk();

        $compliance = Compliance::query()->where('batch_id', $batch->id)->sole();
        $this->actingAs($reviewer, 'api');
        $this->postJson('/api/v1/compliance/'.$compliance->id.'/resolve', [
            'resolved_date' => now('Asia/Manila')->toDateString(),
        ])->assertNoContent();

        $this->assertSame('On Process', $letter->fresh()->status->name);
        $this->assertSame($returnedDate, $letter->fresh()->date_fwd_to_dc->toDateString());
        $this->getJson('/api/v1/outgoing/on-process/batches')->assertOk()->assertJsonPath('meta.total', 0);

        $this->boxAReviewer();
        $this->getJson('/api/v1/outgoing/on-process/batches')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $batch->id);
    }

    public function test_compliance_queues_separate_gl_and_voucher_returns_by_recipient_role(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::connection()->getPdo()->sqliteCreateFunction(
                'MONTH',
                static fn (?string $date): ?int => $date === null ? null : (int) date('n', strtotime($date)),
            );
        }

        $batchLetter = $this->letter('5000.00');
        $this->createVoucher([$batchLetter->id])->assertCreated();
        $batch = Batch::query()->sole();
        $this->reviewer();
        $this->postJson('/api/v1/voucher-review/batches/'.$batch->id.'/return-for-compliance', [
            'forwarded_date' => now('Asia/Manila')->toDateString(),
            'items' => [['guarantee_letter_id' => $batchLetter->id, 'remarks' => 'Correct the voucher.']],
            'remarks' => 'Correct the voucher.',
        ])->assertOk();
        $batchCompliance = Compliance::query()->where('batch_id', $batch->id)->sole();

        $directLetter = $this->letter('2500.00', [
            'status_id' => Status::query()->where('name', 'For Compliance')->sole()->id,
        ]);
        $directType = ComplianceType::query()->create(['name' => 'Voucher Creation to Encoder']);
        $glCompliance = $directLetter->compliances()->create([
            'compliance_type_id' => $directType->id,
            'returned_at' => now(),
            'remarks' => 'Correct the GL.',
        ]);
        $reviewerType = ComplianceType::query()->where('name', 'Reviewer to Voucher Creation')->sole();
        $directLetter->compliances()->create([
            'compliance_type_id' => $reviewerType->id,
            'returned_at' => now()->subDay(),
            'resolved_at' => now(),
        ]);
        $this->getJson('/api/v1/compliance/access')
            ->assertOk()
            ->assertJsonPath('tabs.0', 'voucher')
            ->assertJsonPath('counts.gl', 0)
            ->assertJsonPath('counts.voucher', 0)
            ->assertJsonPath('total_open', 0);
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('data.compliance_open_total', 0);
        $this->getJson('/api/v1/compliance?kind=voucher')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/compliance/'.$batchCompliance->id)->assertForbidden();

        $this->actingAs($this->voucherCreator, 'api');
        $this->getJson('/api/v1/compliance/access')
            ->assertOk()
            ->assertJsonPath('tabs.0', 'voucher')
            ->assertJsonPath('counts.gl', 0)
            ->assertJsonPath('counts.voucher', 1)
            ->assertJsonPath('total_open', 1);
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('data.compliance_open_total', 1);
        $this->getJson('/api/v1/compliance?kind=voucher')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $batchCompliance->id)
            ->assertJsonPath('data.0.batch.id', $batch->id)
            ->assertJsonPath('data.0.compliance_type.name', 'Reviewer to Voucher Creation');
        $this->getJson('/api/v1/compliance/'.$batchCompliance->id)
            ->assertOk()
            ->assertJsonPath('data.remarks', 'Correct the voucher.')
            ->assertJsonPath('data.batch.gls.0.client.full_name', $batchLetter->client->full_name);
        $this->getJson('/api/v1/compliance?kind=gl')->assertForbidden();
        $this->getJson('/api/v1/compliance?kind=voucher&compliance_type_id='.$directType->id)
            ->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/compliance?kind=voucher&search=FASS-'.now('Asia/Manila')->format('m-d').'-0001')
            ->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/compliance?kind=voucher&compliance_type_id=999999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('compliance_type_id');

        Role::query()->firstOrCreate(['name' => 'encoder']);
        $encoder = User::factory()->create();
        $encoder->assignRole('encoder');
        $this->actingAs($encoder, 'api');
        $this->getJson('/api/v1/compliance/access')
            ->assertOk()
            ->assertJsonPath('tabs.0', 'gl')
            ->assertJsonPath('counts.gl', 1)
            ->assertJsonPath('counts.voucher', 0)
            ->assertJsonPath('total_open', 1);
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('data.compliance_open_total', 1);
        $this->getJson('/api/v1/compliance?kind=gl')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $glCompliance->id)
            ->assertJsonPath('data.0.gl.id', $directLetter->id);
        $this->getJson('/api/v1/compliance/'.$glCompliance->id)
            ->assertOk()
            ->assertJsonPath('data.gl.client.full_name', $directLetter->client->full_name);
        $this->getJson('/api/v1/compliance?kind=voucher')->assertForbidden();

        Role::query()->firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'api');
        $this->getJson('/api/v1/compliance/access')
            ->assertOk()
            ->assertJsonPath('tabs.0', 'gl')
            ->assertJsonPath('tabs.1', 'voucher')
            ->assertJsonPath('counts.gl', 1)
            ->assertJsonPath('counts.voucher', 1)
            ->assertJsonPath('total_open', 2);
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('data.compliance_open_total', 2);
        $this->getJson('/api/v1/compliance?kind=gl')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/compliance?kind=voucher')->assertOk()->assertJsonPath('meta.total', 1);

        $directType->update(['is_active' => false]);
        $this->getJson('/api/v1/compliance?kind=gl&compliance_type_id='.$directType->id)
            ->assertOk()->assertJsonPath('meta.total', 1);
        $glCompliance->update(['resolved_at' => now()->addMinute()]);
        $this->getJson('/api/v1/compliance/access')
            ->assertOk()
            ->assertJsonPath('counts.gl', 0)
            ->assertJsonPath('counts.voucher', 1)
            ->assertJsonPath('total_open', 1);
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('data.compliance_open_total', 1);
        $this->getJson('/api/v1/compliance?kind=gl')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/compliance/'.$glCompliance->id)->assertNotFound();

        $this->actingAs(User::factory()->create(), 'api');
        $this->getJson('/api/v1/compliance/access')
            ->assertOk()
            ->assertJsonPath('tabs', [])
            ->assertJsonPath('counts.gl', 0)
            ->assertJsonPath('counts.voucher', 0)
            ->assertJsonPath('total_open', 0);
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('data.compliance_open_total', 0);
        $this->getJson('/api/v1/compliance?kind=voucher')->assertForbidden();
    }

    public function test_reviewer_cannot_return_a_voucher_twice_or_with_mixed_letter_statuses(): void
    {
        $first = $this->letter('5000.00');
        $second = $this->letter('2500.00');
        $this->createVoucher([$first->id, $second->id])->assertCreated();
        $batch = Batch::query()->sole();
        $this->reviewer();
        $endpoint = '/api/v1/voucher-review/batches/'.$batch->id.'/return-for-compliance';
        $payload = ['forwarded_date' => now('Asia/Manila')->toDateString(), 'items' => [['guarantee_letter_id' => $first->id, 'remarks' => 'Correct this voucher.']], 'remarks' => 'Correct this voucher.'];

        $second->status()->associate(Status::query()->where('name', 'On Process')->sole());
        $second->save();
        $this->postJson($endpoint, $payload)->assertConflict();
        $this->assertDatabaseCount('compliances', 0);
        $this->assertSame('For Review by RMCD', $first->fresh()->status->name);

        $second->status()->associate(Status::query()->where('name', 'For Review by RMCD')->sole());
        $second->save();
        $this->postJson($endpoint, $payload)->assertOk();
        $this->postJson($endpoint, $payload)->assertConflict();
        $this->assertDatabaseCount('compliances', 1);
    }

    public function test_return_for_compliance_requires_reviewer_access_date_and_reason(): void
    {
        $letter = $this->letter('5000.00');
        $this->createVoucher([$letter->id])->assertCreated();
        $endpoint = '/api/v1/voucher-review/batches/'.Batch::query()->sole()->id.'/return-for-compliance';
        $this->postJson($endpoint, [
            'forwarded_date' => now('Asia/Manila')->toDateString(),
            'remarks' => 'Correct this voucher.',
        ])->assertForbidden();

        $this->reviewer();
        $this->postJson($endpoint, ['forwarded_date' => 'invalid', 'remarks' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['forwarded_date', 'items']);
        $this->postJson($endpoint, ['forwarded_date' => now('Asia/Manila')->addDay()->toDateString(), 'items' => [['guarantee_letter_id' => $letter->id, 'remarks' => 'Correct this voucher.']], 'remarks' => 'Correct this voucher.'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('forwarded_date');
        $this->assertDatabaseCount('compliances', 0);
        $this->assertSame('For Review by RMCD', $letter->fresh()->status->name);
    }

    public function test_reviewer_can_forward_an_on_process_voucher_to_box_b(): void
    {
        $first = $this->letter('5000.00');
        $second = $this->letter('2500.00');
        $this->createVoucher([$first->id, $second->id])->assertCreated();
        $batch = Batch::query()->sole();
        $reviewer = $this->reviewer();
        $date = now('Asia/Manila')->toDateString();

        $this->postJson('/api/v1/voucher-review/batches/'.$batch->id.'/forward-to-box-a', [
            'date_fwd_to_dc' => $date,
        ])->assertOk();

        $this->boxAReviewer();
        $this->postJson('/api/v1/outgoing/on-process/batches/'.$batch->id.'/approve-box-a')->assertOk();
        $this->actingAs($reviewer, 'api');

        $this->postJson('/api/v1/outgoing/on-process/batches/'.$batch->id.'/forward-to-box-b', [
            'date_fwd_to_budget' => $date,
            'remarks' => 'Forwarded to Financial Service Budget.',
        ])->assertOk()
            ->assertJsonPath('data.status.name', 'For Obligation');

        foreach ([$first, $second] as $letter) {
            $fresh = $letter->fresh();
            $this->assertSame('For Obligation', $fresh->status->name);
            $this->assertSame($date, $fresh->date_fwd_to_budget->toDateString());
        }

        $this->assertSame(2, Activity::query()->where('event', 'forward_to_box_b')->where('subject_type', GuaranteeLetter::class)->where('causer_id', $reviewer->id)->count());
        $this->assertSame(1, Activity::query()->where('event', 'forward_to_box_b')->where('subject_type', Batch::class)->where('subject_id', $batch->id)->count());
        $this->getJson('/api/v1/outgoing/on-process/batches')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/outgoing/for-obligation/batches')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/outgoing/for-obligation/batches/'.$batch->id)
            ->assertOk()
            ->assertJsonPath('data.status.name', 'For Obligation');

        if (DB::getDriverName() === 'sqlite') {
            DB::connection()->getPdo()->sqliteCreateFunction(
                'MONTH',
                static fn (?string $date): ?int => $date === null ? null : (int) date('n', strtotime($date)),
            );
        }

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.outgoing_voucher_counts.on_process', 0)
            ->assertJsonPath('data.outgoing_voucher_counts.for_obligation', 1);
    }

    public function test_budget_can_return_a_voucher_to_fs_compliance_and_continue_after_resolution(): void
    {
        $letter = $this->letter('5000.00');
        $this->createVoucher([$letter->id])->assertCreated();
        $batch = Batch::query()->sole();
        $date = now('Asia/Manila')->toDateString();

        $reviewer = $this->reviewer();
        $this->postJson('/api/v1/voucher-review/batches/'.$batch->id.'/forward-to-box-a', [
            'date_fwd_to_dc' => $date,
        ])->assertOk();
        $this->boxAReviewer();
        $this->postJson('/api/v1/outgoing/on-process/batches/'.$batch->id.'/approve-box-a')->assertOk();
        $this->actingAs($reviewer, 'api');
        $this->postJson('/api/v1/outgoing/on-process/batches/'.$batch->id.'/forward-to-box-b', [
            'date_fwd_to_budget' => $date,
        ])->assertOk()->assertJsonPath('data.status.name', 'For Obligation');

        $returnEndpoint = '/api/v1/outgoing/for-obligation/batches/'.$batch->id.'/return-for-compliance';
        $this->postJson($returnEndpoint, [
            'forwarded_date' => $date,
            'compliance_classification' => 'internal',
            'items' => [['guarantee_letter_id' => $letter->id, 'remarks' => 'Please correct the Budget supporting documents.']],
            'remarks' => 'Please correct the Budget supporting documents.',
        ])->assertOk()->assertJsonPath('data.status.name', 'For Compliance');

        $compliance = Compliance::query()->where('batch_id', $batch->id)->sole();
        $this->assertSame('Budget to Compliance Officer', $compliance->complianceType->name);
        $this->assertSame('internal', $compliance->compliance_classification);

        $complianceOfficer = $this->complianceOfficer();
        $this->getJson('/api/v1/compliance?kind=voucher')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $compliance->id);
        $this->postJson('/api/v1/compliance/'.$compliance->id.'/resolve', [
            'resolved_date' => $date,
        ])->assertNoContent();

        $this->assertSame('For Obligation', $letter->fresh()->status->name);
        $this->actingAs($reviewer, 'api');
        $this->getJson('/api/v1/outgoing/for-obligation/batches/'.$batch->id)
            ->assertOk()
            ->assertJsonPath('data.status.name', 'For Obligation');
        $this->postJson('/api/v1/outgoing/for-obligation/batches/'.$batch->id.'/forward-to-box-c', [
            'date_fwd_to_actg' => $date,
        ])->assertOk()->assertJsonPath('data.status.name', 'For Pre-Audit');
        $this->patchJson('/api/v1/outgoing/for-crediting/batches/'.$batch->id.'/financial-details', [
            'ors_no' => 'ORS-COMPLIANCE-001',
        ])->assertOk()->assertJsonPath('data.ors.ors_no', 'ORS-COMPLIANCE-001');
    }

    public function test_box_b_forwarding_requires_reviewer_access_and_valid_date(): void
    {
        $letter = $this->letter('5000.00');
        $this->createVoucher([$letter->id])->assertCreated();
        $batch = Batch::query()->sole();
        $endpoint = '/api/v1/outgoing/on-process/batches/'.$batch->id.'/forward-to-box-b';

        $this->postJson($endpoint, ['date_fwd_to_budget' => now('Asia/Manila')->toDateString()])->assertForbidden();

        $this->reviewer();
        $this->postJson($endpoint, ['date_fwd_to_budget' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('date_fwd_to_budget');
        $this->assertSame('For Review by RMCD', $letter->fresh()->status->name);
    }

    public function test_box_b_forwarding_rejects_wrong_status_duplicate_forwarding_and_missing_target_status(): void
    {
        $letter = $this->letter('5000.00');
        $this->createVoucher([$letter->id])->assertCreated();
        $batch = Batch::query()->sole();
        $endpoint = '/api/v1/outgoing/on-process/batches/'.$batch->id.'/forward-to-box-b';
        $date = now('Asia/Manila')->toDateString();

        $this->reviewer();
        $this->postJson($endpoint, ['date_fwd_to_budget' => $date])->assertConflict();

        $this->postJson('/api/v1/voucher-review/batches/'.$batch->id.'/forward-to-box-a', [
            'date_fwd_to_dc' => $date,
        ])->assertOk();
        $this->boxAReviewer();
        $this->postJson('/api/v1/outgoing/on-process/batches/'.$batch->id.'/approve-box-a')->assertOk();
        $this->reviewer();
        Status::query()->where('name', 'For Obligation')->update(['is_active' => false]);
        $this->postJson($endpoint, ['date_fwd_to_budget' => $date])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_fwd_to_budget');
        $this->assertSame('On Process', $letter->fresh()->status->name);
        $this->assertNull($letter->fresh()->date_fwd_to_budget);

        Status::query()->where('name', 'For Obligation')->update(['is_active' => true]);
        $this->postJson($endpoint, ['date_fwd_to_budget' => $date])->assertOk();
        $this->postJson($endpoint, ['date_fwd_to_budget' => $date])->assertConflict();
    }

    public function test_reviewer_can_forward_a_for_obligation_voucher_to_box_c(): void
    {
        $letter = $this->letter('5000.00', [
            'status_id' => Status::query()->where('name', 'For Obligation')->sole()->id,
            'date_fwd_to_budget' => now('Asia/Manila')->subDay(),
        ]);
        $batch = Batch::factory()->create();
        $batch->syncGls([$letter->id]);
        $date = now('Asia/Manila')->toDateString();

        $this->reviewer();
        $this->getJson('/api/v1/outgoing/for-obligation/batches')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
        $this->postJson('/api/v1/outgoing/for-obligation/batches/'.$batch->id.'/forward-to-box-c', [
            'date_fwd_to_actg' => $date,
            'remarks' => 'Forwarded to Box C.',
        ])->assertOk()
            ->assertJsonPath('data.status.name', 'For Pre-Audit');
        $this->patchJson('/api/v1/outgoing/for-crediting/batches/'.$batch->id.'/financial-details', [
            'ors_no' => '26-05-07590',
        ])->assertOk()
            ->assertJsonPath('data.ors.ors_no', '26-05-07590');

        $this->assertSame($date, $letter->fresh()->date_fwd_to_actg->toDateString());
        $this->assertSame('For Pre-Audit', $letter->fresh()->status->name);
        $this->assertSame(1, Activity::query()->where('event', 'forward_to_box_c')->where('subject_type', Batch::class)->where('subject_id', $batch->id)->count());
    }

    public function test_accounting_compliance_returns_to_pre_audit_then_forwards_to_box_d_for_crediting(): void
    {
        $date = now('Asia/Manila')->toDateString();
        $letter = $this->letter('5000.00', [
            'status_id' => Status::query()->where('name', 'For Pre-Audit')->sole()->id,
            'date_fwd_to_budget' => now('Asia/Manila')->subDay(),
            'date_fwd_to_actg' => now('Asia/Manila')->subDay(),
        ]);
        $batch = Batch::factory()->create();
        $batch->syncGls([$letter->id]);
        $reviewer = $this->reviewer();

        $returnEndpoint = '/api/v1/outgoing/for-pre-audit/batches/'.$batch->id.'/return-for-compliance';
        $this->postJson($returnEndpoint, [
            'forwarded_date' => $date,
            'compliance_classification' => 'internal',
            'items' => [['guarantee_letter_id' => $letter->id, 'remarks' => 'Please correct the Accounting supporting documents.']],
            'remarks' => 'Please correct the Accounting supporting documents.',
        ])->assertOk()->assertJsonPath('data.status.name', 'For Compliance');

        $compliance = Compliance::query()->where('batch_id', $batch->id)->sole();
        $this->assertSame('Accounting to Compliance Officer', $compliance->complianceType->name);
        $this->assertSame('internal', $compliance->compliance_classification);
        $this->complianceOfficer();
        $this->postJson('/api/v1/compliance/'.$compliance->id.'/resolve', [
            'resolved_date' => $date,
        ])->assertNoContent();

        $this->assertSame('For Pre-Audit', $letter->fresh()->status->name);
        $this->actingAs($reviewer, 'api');
        $this->postJson('/api/v1/outgoing/for-pre-audit/batches/'.$batch->id.'/forward-to-box-d', [
            'date_fwd_to_dir_dv' => $date,
            'remarks' => 'Forwarded to Director Box D.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status.name', 'For Crediting');
        $this->patchJson('/api/v1/outgoing/for-crediting/batches/'.$batch->id.'/financial-details', [
            'dv_no' => 'DV-MANUAL-001',
        ])->assertOk()
            ->assertJsonPath('data.dv.dv_no', 'DV-MANUAL-001');

        $this->assertSame('For Crediting', $letter->fresh()->status->name);
        $this->assertSame($date, $letter->fresh()->date_fwd_to_dir_dv->toDateString());
        $this->assertNotNull($batch->fresh()->dv_id);
        $this->boxAReviewer();
        $this->getJson('/api/v1/outgoing/for-crediting/batches/'.$batch->id)
            ->assertOk()
            ->assertJsonPath('data.status.name', 'For Crediting');

        $this->postJson('/api/v1/outgoing/for-crediting/batches/'.$batch->id.'/tag-signed', [
            'date_fwd_to_dir_fs' => $date,
        ])->assertOk();

        Role::query()->firstOrCreate(['name' => 'outgoing']);
        $outgoing = User::factory()->create();
        $outgoing->assignRole('outgoing');
        $this->actingAs($outgoing, 'api');
        $this->postJson('/api/v1/outgoing/for-crediting/batches/'.$batch->id.'/forward-to-cash', [
            'date_fwd_to_cash' => $date,
        ])->assertOk();

        $this->actingAs($reviewer, 'api');
        $this->assertSame($date, $letter->fresh()->date_fwd_to_cash->toDateString());

        $this->postJson('/api/v1/outgoing/for-crediting/batches/'.$batch->id.'/return-for-compliance', [
            'forwarded_date' => $date,
            'items' => [['guarantee_letter_id' => $letter->id, 'remarks' => 'Please correct the Cash supporting documents.']],
            'remarks' => 'Please correct the Cash supporting documents.',
        ])->assertOk()->assertJsonPath('data.status.name', 'For Compliance');

        $compliance = Compliance::query()->where('batch_id', $batch->id)->latest('id')->firstOrFail();
        $this->assertSame('Cash to Reviewer', $compliance->complianceType->name);
        $this->postJson('/api/v1/outgoing/for-crediting/batches/'.$batch->id.'/mark-as-paid', [
            'cheque_no' => 'CHEQUE-BLOCKED-001',
            'cheque_date' => $date,
        ])->assertConflict();
        $this->postJson('/api/v1/compliance/'.$compliance->id.'/resolve', [
            'resolved_date' => $date,
        ])->assertNoContent();

        $this->assertSame('For Crediting', $letter->fresh()->status->name);
        $this->getJson('/api/v1/outgoing/for-crediting/batches/'.$batch->id)
            ->assertOk()
            ->assertJsonPath('data.status.name', 'For Crediting');

        $this->postJson('/api/v1/outgoing/for-crediting/batches/'.$batch->id.'/mark-as-paid', [
            'cheque_no' => 'CHEQUE-MANUAL-001',
            'cheque_date' => $date,
        ])->assertOk()->assertJsonPath('data.status.name', 'Paid');

        $this->assertSame('Paid', $letter->fresh()->status->name);
        $this->getJson('/api/v1/outgoing/paid/batches/'.$batch->id)
            ->assertOk()
            ->assertJsonPath('data.cheque.cheque_no', 'CHEQUE-MANUAL-001');
    }

    public function test_reviewer_can_print_the_two_page_voucher_template(): void
    {
        $letter = $this->letter('5000.25');
        $this->createVoucher([$letter->id])->assertCreated();
        $batch = Batch::query()->sole();
        $this->reviewer();

        $response = $this->get('/api/v1/voucher-review/batches/'.$batch->id.'/print');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="voucher-'.$batch->id.'.pdf"');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertSame(2, preg_match_all('/\/Type\s*\/Page\b/', $response->getContent()));
        $this->assertNull($batch->fresh()->ors_id);
    }

    public function test_reviewer_can_print_a_one_page_rmcd_transmittal_with_ten_guarantee_letters(): void
    {
        $letters = [];
        for ($index = 0; $index < 10; $index++) {
            $letters[] = $this->letter('1000.25');
        }
        $this->createVoucher(array_map(fn (GuaranteeLetter $letter): int => $letter->id, $letters))->assertCreated();
        $batch = Batch::query()->sole();
        $this->reviewer();

        $response = $this->get('/api/v1/voucher-review/batches/'.$batch->id.'/print-transmittal');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="voucher-transmittal-'.$batch->id.'.pdf"');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page\b/', $response->getContent()));
    }

    public function test_rmcd_transmittal_uses_provider_gl_details_and_voucher_total(): void
    {
        $letter = $this->letter('5000.25');
        $this->createVoucher([$letter->id])->assertCreated();
        $batch = Batch::query()->sole();
        $this->reviewer();

        $document = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $document->shouldReceive('setPaper')->once()->with('a4', 'landscape')->andReturnSelf();
        $document->shouldReceive('output')->once()->andReturn('%PDF-test');
        Pdf::shouldReceive('loadView')->once()->withArgs(function (string $view, array $data) use ($batch, $letter): bool {
            $this->assertSame('printing.voucher_transmittal', $view);
            $this->assertSame('VOUCHER TEST PROVIDER', $data['providerName']);
            $this->assertSame('Manila', $data['providerAddress']);
            $this->assertSame($batch->voucher->voucher_no, $data['voucherNumber']);
            $this->assertSame('FRANS AUDREY S SANDICON', $data['preparedBy']);
            $this->assertSame('Administrative Assistant II', $data['preparedByPosition']);
            $this->assertSame('ASSIGNED REVIEWER', $data['reviewedBy']);
            $this->assertSame('Administrative Officer IV', $data['reviewedByPosition']);
            $this->assertSame([$letter->id], $data['letters']->pluck('id')->all());
            $this->assertSame('Php 5,000.25', $data['totalAmount']);
            $html = view($view, $data)->render();
            $this->assertSame(10, substr_count($html, '<td class="number">'));
            $this->assertStringContainsString('Voucher Test Client', $html);
            $this->assertStringContainsString('Self', $html);
            $this->assertStringContainsString('Voucher Test Beneficiary', $html);
            $this->assertStringContainsString('Department of Social Welfare and Development', $html);
            $this->assertStringContainsString('Crisis Intervention Program', $html);
            $this->assertStringContainsString('PREPARED BY :<div class="name">FRANS AUDREY S SANDICON</div><div class="position">Administrative Assistant II</div>', $html);
            $this->assertStringContainsString('REVIEWED BY:<div class="name">ASSIGNED REVIEWER</div><div class="position">Administrative Officer IV</div>', $html);
            $this->assertStringNotContainsString('MARY GRACE D. DE LEON', $html);

            return true;
        })->andReturn($document);

        $this->get('/api/v1/voucher-review/batches/'.$batch->id.'/print-transmittal')->assertOk();
    }

    public function test_rmcd_transmittal_requires_batch_access_and_review_status(): void
    {
        $letter = $this->letter('5000.25');
        $this->createVoucher([$letter->id])->assertCreated();
        $batch = Batch::query()->sole();
        $endpoint = '/api/v1/voucher-review/batches/'.$batch->id.'/print-transmittal';

        $otherCreator = User::factory()->create();
        $otherCreator->assignRole('voucher creation');
        $this->actingAs($otherCreator, 'api');
        $this->get($endpoint)->assertForbidden();

        $this->reviewer();
        $this->postJson('/api/v1/voucher-review/batches/'.$batch->id.'/forward-to-box-a', [
            'date_fwd_to_dc' => '2026-09-30',
        ])->assertOk();
        $this->get($endpoint)->assertNotFound();
    }

    public function test_rmcd_transmittal_rejects_a_batch_with_more_than_ten_guarantee_letters(): void
    {
        $letters = [];
        for ($index = 0; $index < 10; $index++) {
            $letters[] = $this->letter('1000.00');
        }
        $this->createVoucher(array_map(fn (GuaranteeLetter $letter): int => $letter->id, $letters))->assertCreated();
        $batch = Batch::query()->sole();
        $extraLetter = $this->letter('1000.00');
        $extraLetter->status_id = Status::query()->where('name', 'For Review by RMCD')->sole()->id;
        $extraLetter->save();
        $batch->gls()->attach($extraLetter->id, ['position' => 11]);
        $this->reviewer();

        $response = $this->get('/api/v1/voucher-review/batches/'.$batch->id.'/print-transmittal');

        $response->assertConflict();
    }

    public function test_second_ors_page_covers_template_footer_before_printing_voucher_number(): void
    {
        $html = view('printing.voucher_ors_pages', [
            'pageImages' => [1 => '', 2 => ''],
            'payee' => '',
            'address' => '',
            'particulars' => '',
            'amount' => '',
            'fundSource' => '',
            'voucherNumber' => 'VOUCHER-001',
        ])->render();

        $this->assertSame(1, substr_count($html, 'class="footer-mask"'));
        $this->assertMatchesRegularExpression(
            '/class="page ors-page".*class="footer-mask" style="left: 0; top: 806pt; width: 595pt; height: 24pt;".*VOUCHER-001/s',
            $html,
        );
    }

    public function test_reviewer_can_print_the_three_page_disbursement_voucher_template(): void
    {
        $letter = $this->letter('5000.25');
        $this->createVoucher([$letter->id])->assertCreated();
        $batch = Batch::query()->sole();
        $this->reviewer();

        $response = $this->get('/api/v1/voucher-review/batches/'.$batch->id.'/print-dv');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="dv-'.$batch->id.'.pdf"');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertSame(3, preg_match_all('/\/Type\s*\/Page\b/', $response->getContent()));
    }

    public function test_second_dv_page_covers_template_voucher_placeholder_before_printing_number(): void
    {
        $html = view('printing.voucher_dv_pages', [
            'pageImages' => [1 => '', 2 => '', 3 => ''],
            'date' => '',
            'dvNumber' => '',
            'payee' => '',
            'address' => '',
            'particulars' => '',
            'amount' => '',
            'fundSource' => '',
            'voucherNumber' => 'VOUCHER-001',
        ])->render();

        $this->assertSame(1, substr_count($html, 'top: 796pt; width: 155pt; height: 23pt;'));
        $this->assertMatchesRegularExpression(
            '/top: 796pt; width: 155pt; height: 23pt;".*top: 798pt; width: 140pt;">VOUCHER-001/s',
            $html,
        );
    }

    public function test_reviewer_can_print_ors_and_dv_in_one_five_page_pdf(): void
    {
        $letter = $this->letter('5000.25');
        $this->createVoucher([$letter->id])->assertCreated();
        $batch = Batch::query()->sole();
        $this->reviewer();
        Http::preventStrayRequests();

        $response = $this->get('/api/v1/voucher-review/batches/'.$batch->id.'/print-combined');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="ors-dv-'.$batch->id.'.pdf"');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertSame(5, preg_match_all('/\/Type\s*\/Page\b/', $response->getContent()));
        Http::assertNothingSent();
    }

    public function test_combined_pdf_requires_batch_access_and_review_status(): void
    {
        $letter = $this->letter('5000.25');
        $this->createVoucher([$letter->id])->assertCreated();
        $batch = Batch::query()->sole();
        $endpoint = '/api/v1/voucher-review/batches/'.$batch->id.'/print-combined';

        $otherCreator = User::factory()->create();
        $otherCreator->assignRole('voucher creation');
        $this->actingAs($otherCreator, 'api');
        $this->get($endpoint)->assertForbidden();

        $this->reviewer();
        $this->postJson('/api/v1/voucher-review/batches/'.$batch->id.'/forward-to-box-a', [
            'date_fwd_to_dc' => '2026-09-30',
        ])->assertOk();
        $this->get($endpoint)->assertNotFound();
    }

    public function test_disbursement_voucher_uses_provider_details_and_today_date(): void
    {
        $letter = $this->letter('5000.25');
        $this->createVoucher([$letter->id])->assertCreated();
        $batch = Batch::query()->sole();
        $this->reviewer();

        $document = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $document->shouldReceive('setPaper')->once()->with('a4')->andReturnSelf();
        $document->shouldReceive('output')->once()->andReturn('%PDF-test');
        Pdf::shouldReceive('loadView')->once()->withArgs(function (string $view, array $data) use ($batch): bool {
            $this->assertSame('printing.voucher_dv', $view);
            $this->assertSame(now('Asia/Manila')->format('m/d/Y'), $data['date']);
            $this->assertSame('VOUCHER TEST PROVIDER', $data['payee']);
            $this->assertSame($this->branch->serviceProvider->address, $data['address']);
            $this->assertSame('5,000.25', $data['amount']);
            $this->assertSame($batch->voucher->voucher_no, $data['voucherNumber']);
            $this->assertCount(3, $data['pageImages']);

            return true;
        })->andReturn($document);

        $this->get('/api/v1/voucher-review/batches/'.$batch->id.'/print-dv')->assertOk();
    }

    public function test_disbursement_voucher_print_requires_batch_access_and_review_status(): void
    {
        $letter = $this->letter('5000.25');
        $this->createVoucher([$letter->id])->assertCreated();
        $batch = Batch::query()->sole();
        $endpoint = '/api/v1/voucher-review/batches/'.$batch->id.'/print-dv';

        $otherCreator = User::factory()->create();
        $otherCreator->assignRole('voucher creation');
        $this->actingAs($otherCreator, 'api');
        $this->get($endpoint)->assertForbidden();

        $this->reviewer();
        $this->postJson('/api/v1/voucher-review/batches/'.$batch->id.'/forward-to-box-a', [
            'date_fwd_to_dc' => '2026-09-30',
        ])->assertOk();
        $this->get($endpoint)->assertNotFound();
    }

    public function test_print_pdf_uses_the_first_letter_for_particulars_and_the_batch_total(): void
    {
        $second = $this->letter('2500.00');
        $first = $this->letter('5000.25', [
            'client_id' => Client::query()->create(['full_name' => 'Amalia Uy Baguio', 'sex' => 2, 'address' => 'Manila'])->id,
            'beneficiary_id' => Beneficiary::query()->create(['full_name' => 'Ricky Jimenez Baguio'])->id,
            'relationship_id' => Relationship::query()->firstOrCreate(['name' => 'Husband'])->id,
            'assistance_type_id' => AssistanceType::query()->firstOrCreate(['name' => 'Medical'])->id,
        ]);
        $this->createVoucher([$first->id, $second->id])->assertCreated();
        $batch = Batch::query()->sole();
        $this->reviewer();

        $document = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $document->shouldReceive('setPaper')->once()->with('a4')->andReturnSelf();
        $document->shouldReceive('output')->once()->andReturn('%PDF-test');
        Pdf::shouldReceive('loadView')->once()->withArgs(function (string $view, array $data): bool {
            $this->assertSame('printing.voucher_ors', $view);
            $this->assertSame('Payment for the medical assistance of AMALIA UY BAGUIO for her husband RICKY JIMENEZ BAGUIO et al.', $data['particulars']);
            $this->assertSame('Php 7,500.25', $data['amount']);
            $this->assertSame('VOUCHER TEST PROVIDER', $data['payee']);
            $this->assertCount(2, $data['pageImages']);

            return true;
        })->andReturn($document);

        $this->get('/api/v1/voucher-review/batches/'.$batch->id.'/print')->assertOk();
    }

    public function test_creator_and_reviewer_can_print_a_batch_that_is_still_for_review(): void
    {
        $letter = $this->letter('5000.25');
        $this->createVoucher([$letter->id])->assertCreated();
        $batch = Batch::query()->sole();
        $endpoint = '/api/v1/voucher-review/batches/'.$batch->id.'/print';

        $this->get($endpoint)->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $otherCreator = User::factory()->create();
        $otherCreator->assignRole('voucher creation');
        $this->actingAs($otherCreator, 'api');
        $this->get($endpoint)->assertForbidden();

        $this->reviewer();
        $this->get($endpoint)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->postJson('/api/v1/voucher-review/batches/'.$batch->id.'/forward-to-box-a', [
            'date_fwd_to_dc' => '2026-09-30',
        ])->assertOk();

        $this->get($endpoint)->assertNotFound();
    }

    public function test_voucher_prints_without_signing_configuration_or_cached_signature(): void
    {
        $letter = $this->letter('5000.25');
        $this->createVoucher([$letter->id])->assertCreated();
        $batch = Batch::query()->sole();
        $this->reviewer();
        Storage::fake('local');
        Storage::disk('local')->put("signed-vouchers/ors-v3/{$batch->id}.pdf", '%PDF-cached-signed-ors');
        config()->set('services.pdf_signer.project_folder', null);
        Http::preventStrayRequests();

        $content = $this->get('/api/v1/voucher-review/batches/'.$batch->id.'/print')->assertOk()->getContent();

        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertNotSame('%PDF-cached-signed-ors', $content);
        $this->assertSame(2, preg_match_all('/\/Type\s*\/Page\b/', $content));
        Http::assertNothingSent();
    }

    public function test_disbursement_voucher_prints_without_signing_configuration_or_cached_signature(): void
    {
        $letter = $this->letter('5000.25');
        $this->createVoucher([$letter->id])->assertCreated();
        $batch = Batch::query()->sole();
        $this->reviewer();
        Storage::fake('local');
        Storage::disk('local')->put('signed-vouchers/dv-v1/'.now('Asia/Manila')->toDateString()."/{$batch->id}.pdf", '%PDF-cached-signed-dv');
        config()->set('services.pdf_signer.project_folder', null);
        Http::preventStrayRequests();

        $content = $this->get('/api/v1/voucher-review/batches/'.$batch->id.'/print-dv')->assertOk()->getContent();

        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertNotSame('%PDF-cached-signed-dv', $content);
        $this->assertSame(3, preg_match_all('/\/Type\s*\/Page\b/', $content));
        Http::assertNothingSent();
    }

    private function reviewer(): User
    {
        Role::query()->firstOrCreate(['name' => 'reviewer']);
        $reviewer = User::factory()->create();
        $reviewer->assignRole('reviewer');
        $this->actingAs($reviewer, 'api');

        return $reviewer;
    }

    private function boxAReviewer(): User
    {
        $reviewer = User::factory()->create();
        $reviewer->assignRole('box a reviewer');
        $this->actingAs($reviewer, 'api');

        return $reviewer;
    }

    private function complianceOfficer(): User
    {
        $officer = User::factory()->create();
        $officer->assignRole('compliance');
        $this->actingAs($officer, 'api');

        return $officer;
    }

    /** @param list<int> $glIds */
    private function createVoucher(array $glIds)
    {
        return $this->postJson('/api/v1/voucher-creation/batches', [
            'gl_ids' => $glIds,
            'fund_source_id' => $this->fundSource->id,
            'reviewer_id' => $this->assignedReviewer->id,
            'remarks' => 'Voucher creation test.',
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function letter(string $amount, array $overrides = []): GuaranteeLetter
    {
        $region = PsgcRegion::query()->firstOrCreate(
            ['code' => '0000000020'],
            ['code_correspondence' => '000000020', 'name' => 'Central Office'],
        );
        $client = Client::query()->firstOrCreate(['full_name' => 'Voucher Test Client'], ['sex' => 1, 'address' => 'Manila']);
        $beneficiary = Beneficiary::query()->firstOrCreate(['full_name' => 'Voucher Test Beneficiary']);
        $office = Office::query()->firstOrCreate(['name' => 'Voucher Test Office'], ['region_id' => $region->reg_id]);
        $relationship = Relationship::query()->firstOrCreate(['name' => 'Self']);
        $signatory = Signatory::query()->firstOrCreate(['name' => 'Voucher Test Signatory'], [
            'designation' => 'Director', 'minimum_amount' => '0.00', 'maximum_amount' => '99999999.99',
        ]);
        $swo = Swo::query()->firstOrCreate(['name' => 'Voucher Test SWO']);

        return GuaranteeLetter::query()->create(array_replace([
            'gl_no' => 'GL-VOUCHER-'.(DB::table('guarantee_letters')->count() + 1),
            'gl_date' => '2026-09-01',
            'gl_amount' => $amount,
            'gl_validity' => '2026-10-01',
            'office_id' => $office->id,
            'client_id' => $client->id,
            'beneficiary_id' => $beneficiary->id,
            'address' => 'Manila',
            'relationship_id' => $relationship->id,
            'service_provider_id' => $this->branch->service_provider_id,
            'branch_id' => $this->branch->id,
            'category_id' => Category::query()->firstOrCreate(['name' => 'Voucher Test Category'])->id,
            'assistance_type_id' => AssistanceType::query()->firstOrCreate(['name' => 'Voucher Test Assistance'])->id,
            'status_id' => Status::query()->where('name', 'For Creation')->sole()->id,
            'signatory_id' => $signatory->id,
            'swo_id' => $swo->id,
            'obligation_amount' => $amount,
            'created_by' => $this->voucherCreator->id,
        ], $overrides));
    }

    private function createBranch(string $name = 'Voucher Test Branch'): Branch
    {
        $region = PsgcRegion::query()->firstOrCreate(
            ['code' => '0000000020'],
            ['code_correspondence' => '000000020', 'name' => 'Central Office'],
        );
        $provider = ServiceProvider::query()->firstOrCreate(['name' => 'Voucher Test Provider'], [
            'region_id' => $region->reg_id,
            'address' => 'Manila',
            'salutation' => 'Dear',
            'addressee' => 'Director',
            'designation' => 'Director',
        ]);

        return Branch::query()->firstOrCreate(
            ['name' => $name],
            ['service_provider_id' => $provider->id, 'address' => 'Manila'],
        );
    }
}
