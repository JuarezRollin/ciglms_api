<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\FundSource;
use App\Models\GlTransmittal;
use App\Models\GuaranteeLetter;
use App\Models\Signatory;
use App\Models\Status;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\TransactionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => 'transaction-tests-only-secret-0123456789abcdef']);
    }

    public function test_database_seeder_creates_complete_workflow_transactions(): void
    {
        $this->seed();
        $oldestDate = CarbonImmutable::today('Asia/Manila')->subWeeks(2);
        $yesterday = CarbonImmutable::yesterday('Asia/Manila');

        $this->assertDatabaseCount('signatories', 8);
        foreach ([
            ['Sharon A. Pattugalan', 'Social Welfare Officer II', 0, 10000],
            ['Maria Gracia O. Tomniak', 'Social Welfare Officer II', 0, 10000],
            ['Ma. Lilian A Malig-On', 'Social Welfare Officer III', 0, 10000],
            ['Lea B. Bangalan', 'Director III', 10001, 150000],
            ['Edwin S. Morata', 'Director IV', 10001, 150000],
            ['Ada A. Colico', 'Assistant Secretary for Statutory Programs', 150001, 300000],
            ['Monina Josefina H. Romualdez', 'Undersecretary for Operations', 300001, 500000],
            ['Rex Gatchalian', 'Secretary', 500001, '9999999999999.99'],
        ] as [$name, $designation, $minimum, $maximum]) {
            $this->assertDatabaseHas('signatories', [
                'name' => $name, 'designation' => $designation,
                'minimum_amount' => $minimum, 'maximum_amount' => $maximum, 'is_active' => true,
            ]);
        }
        $this->assertSame('Rex Gatchalian', Signatory::query()->coveringAmount('9999999999999.99')->sole()->name);

        $this->assertDatabaseCount('guarantee_letters', 850);
        $this->assertDatabaseCount('batches', 7);
        $this->assertDatabaseCount('batch_gl', 350);
        $this->assertDatabaseCount('gl_transmittals', 4);
        $this->assertDatabaseCount('gl_transmittal', 100);
        foreach (['FAS-SAMPLE-0001', 'FAS-SAMPLE-0002'] as $bundleNo) {
            $transmittal = GlTransmittal::query()->where('bundle_no', $bundleNo)->firstOrFail();
            $this->assertNull($transmittal->cancelled_at);
            $this->assertNull($transmittal->received_by);
            $this->assertSame(25, $transmittal->gls()->count());
            $this->assertSame(25, $transmittal->gls()->where('status_id', Status::FOR_ENDORSEMENT_ID)->count());
        }
        $encoderId = User::query()->where('email', 'john.reyes@dswd.gov.ph')->firstOrFail()->id;
        foreach (['FAS-SAMPLE-0003', 'FAS-SAMPLE-0004'] as $bundleNo) {
            $transmittal = GlTransmittal::query()->where('bundle_no', $bundleNo)->firstOrFail();
            $this->assertSame($encoderId, $transmittal->received_by);
            $this->assertSame(25, $transmittal->gls()->count());
            $this->assertSame(25, $transmittal->gls()->where('status_id', Status::AWAITING_SOA_ID)->where('received_by', $encoderId)->count());
        }
        $this->assertSame(0, GuaranteeLetter::query()
            ->where('status_id', Status::FOR_ENDORSEMENT_ID)
            ->whereDoesntHave('transmittals', fn ($query) => $query->whereNull('gl_transmittals.cancelled_at'))
            ->count());
        $this->assertSame($oldestDate->toDateString(), substr((string) GuaranteeLetter::query()->min('gl_date'), 0, 10));
        $this->assertSame($yesterday->toDateString(), substr((string) GuaranteeLetter::query()->max('gl_date'), 0, 10));
        foreach ([
            'created_at', 'date_reviewed_by_creation', 'date_approved', 'date_released',
            'date_endorsed', 'date_onhold', 'date_cancelled_by_creation', 'date_received_gl',
            'date_received_soa', 'date_pulled_out', 'date_fwd_to_reviewer',
            'date_cancelled_by_rmcd', 'date_fwd_to_budget', 'date_fwd_to_actg',
            'date_fwd_to_dir_dv', 'date_fwd_to_cash',
        ] as $column) {
            $this->assertSame(0, GuaranteeLetter::query()->whereDate($column, '<', $oldestDate->toDateString())->count(), $column);
            $this->assertSame(0, GuaranteeLetter::query()->whereDate($column, '>', $yesterday->toDateString())->count(), $column);
        }
        $this->assertSame(0, GuaranteeLetter::query()
            ->join('signatories', 'signatories.id', '=', 'guarantee_letters.signatory_id')
            ->whereColumn('guarantee_letters.gl_amount', '>', 'signatories.maximum_amount')
            ->count());

        foreach (Status::query()->pluck('id') as $statusId) {
            $this->assertSame(50, GuaranteeLetter::query()->where('status_id', $statusId)->count());
        }

        foreach ([
            1 => 'For Review by RMCD',
            2 => 'On Process',
            3 => 'For Obligation',
            4 => 'For Pre-Audit',
            5 => 'For Crediting',
            6 => 'Paid',
            7 => 'Cancelled by RMCD',
        ] as $sequence => $statusName) {
            $batch = Batch::query()->where('remarks', sprintf('Sample accounting batch %d', $sequence))->firstOrFail();
            $this->assertNotNull($batch->voucher_id);
            $this->assertSame(50, $batch->gls()->count());
            $this->assertSame(50, $batch->gls()->where('status_id', $this->statusId($statusName))->count());
            $this->assertSame(1, Batch::query()->atStatus($statusName)->count());
        }

        $reviewBatch = Batch::query()->where('remarks', 'Sample accounting batch 1')->firstOrFail();
        $this->assertNull($reviewBatch->ors_id);
        $this->assertNull($reviewBatch->dv_id);
        $this->assertNull($reviewBatch->cheque_id);

        $preAuditBatch = Batch::query()->where('remarks', 'Sample accounting batch 4')->firstOrFail();
        $this->assertNotNull($preAuditBatch->ors_id);
        $this->assertNotNull($preAuditBatch->dv_id);

        $paidBatch = Batch::query()->where('remarks', 'Sample accounting batch 6')->firstOrFail();
        $this->assertNotNull($paidBatch->voucher_id);
        $this->assertNotNull($paidBatch->ors_id);
        $this->assertNotNull($paidBatch->dv_id);
        $this->assertSame($yesterday->toDateString(), $paidBatch->cheque->cheque_date->toDateString());
        $this->assertSame(50, $paidBatch->ors->gls()->where('status_id', $this->statusId('Paid'))->count());

        $review = GuaranteeLetter::query()->where('status_id', $this->statusId('For Review by CRD'))->firstOrFail();
        $this->assertSame($oldestDate->toDateString(), $review->created_at->toDateString());
        $this->assertSame($review->created_at->toDateString(), $review->gl_date->toDateString());
        $this->assertSame($review->created_at->copy()->addDays(30)->toDateString(), $review->gl_validity->toDateString());
        $this->assertNull($review->fund_source_id);
        $this->assertNull($review->obligation_amount);
        $this->assertNull($review->date_received_gl);

        foreach (['For Review by CRD', 'Approved', 'Released', 'On Hold', 'Cancelled by CRD', 'For Endorsement'] as $statusName) {
            $letter = GuaranteeLetter::query()->where('status_id', $this->statusId($statusName))->firstOrFail();
            $this->assertFalse($letter->batches()->exists());
            $this->assertNull($letter->fund_source_id);
            $this->assertNull($letter->date_received_gl);
        }

        $approved = GuaranteeLetter::query()->where('status_id', $this->statusId('Approved'))->firstOrFail();
        $this->assertTrue($approved->date_reviewed_by_creation->greaterThan($approved->created_at));
        $onHold = GuaranteeLetter::query()->where('status_id', $this->statusId('On Hold'))->firstOrFail();
        $this->assertTrue($onHold->date_onhold->greaterThan($onHold->created_at));
        $cancelledCrd = GuaranteeLetter::query()->where('status_id', $this->statusId('Cancelled by CRD'))->firstOrFail();
        $this->assertTrue($cancelledCrd->date_cancelled_by_creation->greaterThan($cancelledCrd->created_at));
        $endorsement = GuaranteeLetter::query()->where('status_id', $this->statusId('For Endorsement'))->firstOrFail();
        $this->assertTrue($endorsement->date_released->greaterThan($endorsement->created_at));
        $this->assertTrue($endorsement->date_approved->greaterThan($endorsement->date_reviewed_by_creation));
        $this->assertTrue($endorsement->date_released->greaterThan($endorsement->date_approved));
        $this->assertNull($endorsement->date_received_gl);

        $awaitingSoa = GuaranteeLetter::query()->where('status_id', $this->statusId('Awaiting SOA'))->firstOrFail();
        $this->assertTrue($awaitingSoa->date_received_gl->greaterThan($awaitingSoa->created_at));
        $this->assertTrue($awaitingSoa->date_received_gl->greaterThan($awaitingSoa->date_endorsed));
        $this->assertNull($awaitingSoa->fund_source_id);

        $rmcdReview = GuaranteeLetter::query()->where('status_id', $this->statusId('For Review by RMCD'))->firstOrFail();
        $this->assertNotNull($rmcdReview->fund_source_id);
        $this->assertSame($rmcdReview->gl_amount, $rmcdReview->obligation_amount);
        $this->assertTrue($rmcdReview->date_pulled_out->greaterThan($rmcdReview->date_received_soa));
        $this->assertTrue($rmcdReview->date_received_soa->greaterThan($rmcdReview->date_received_gl));

        $cancelledRmcd = GuaranteeLetter::query()->where('status_id', $this->statusId('Cancelled by RMCD'))->firstOrFail();
        $this->assertTrue($cancelledRmcd->date_cancelled_by_rmcd->greaterThan($cancelledRmcd->date_pulled_out));
        $this->assertTrue($cancelledRmcd->batches()->exists());

        $paid = GuaranteeLetter::query()->where('status_id', $this->statusId('Paid'))->firstOrFail();
        $this->assertNotNull($paid->office_id);
        $this->assertNotNull($paid->service_provider_id);
        $this->assertNotNull($paid->swo_id);
        $previousDate = $paid->created_at;
        foreach ([
            'date_reviewed_by_creation', 'date_approved', 'date_released', 'date_endorsed',
            'date_received_gl', 'date_received_soa', 'date_pulled_out', 'date_fwd_to_reviewer',
            'date_fwd_to_budget', 'date_fwd_to_actg', 'date_fwd_to_dir_dv', 'date_fwd_to_cash',
        ] as $column) {
            $this->assertTrue($paid->{$column}->greaterThan($previousDate), $column);
            $previousDate = $paid->{$column};
        }
        $this->assertLessThanOrEqual($yesterday->toDateString(), $paid->date_fwd_to_cash->toDateString());

        $latestPaid = GuaranteeLetter::query()->where('status_id', $this->statusId('Paid'))
            ->orderByDesc('created_at')->firstOrFail();
        $this->assertSame($yesterday->toDateString(), $latestPaid->gl_date->toDateString());
        $this->assertSame($yesterday->toDateString(), $latestPaid->date_fwd_to_cash->toDateString());
    }

    public function test_reseeding_refreshes_transaction_dates_relative_to_the_new_day(): void
    {
        $this->seed();
        $this->travelTo(now('Asia/Manila')->addDays(2));
        $this->seed(TransactionSeeder::class);

        $oldestDate = CarbonImmutable::today('Asia/Manila')->subWeeks(2);
        $yesterday = CarbonImmutable::yesterday('Asia/Manila');
        $created = GuaranteeLetter::query()->where('gl_no', 'GL-2026-0001')->firstOrFail();
        $paidBatch = Batch::query()->where('remarks', 'Sample accounting batch 6')->firstOrFail();

        $this->assertDatabaseCount('guarantee_letters', 850);
        $this->assertDatabaseCount('gl_transmittals', 4);
        $this->assertDatabaseCount('gl_transmittal', 100);
        $this->assertSame($oldestDate->toDateString(), $created->gl_date->toDateString());
        $this->assertSame($oldestDate->toDateString(), $created->created_at->toDateString());
        $this->assertSame($oldestDate->addDays(30)->toDateString(), $created->gl_validity->toDateString());
        $this->assertSame($yesterday->toDateString(), $paidBatch->cheque->cheque_date->toDateString());
    }

    public function test_status_validation_requires_progressive_dates_and_batch_references(): void
    {
        $this->seed();
        $letter = GuaranteeLetter::query()->where('gl_no', 'GL-2026-0002')->firstOrFail();
        $this->actingAs($letter->creator, 'api');

        $this->putJson(
            '/api/v1/guarantee-letters/'.$letter->id,
            $this->payload($letter, ['status_id' => $this->statusId('For Pullout')]),
        )->assertUnprocessable()->assertJsonValidationErrors('date_received_soa');

        $processingLetter = GuaranteeLetter::query()->where('status_id', $this->statusId('Awaiting SOA'))->firstOrFail();
        $this->putJson(
            '/api/v1/guarantee-letters/'.$processingLetter->id,
            $this->payload($processingLetter, [
                'status_id' => $this->statusId('On Process'),
                'fund_source_id' => FundSource::query()->firstOrFail()->id,
                'obligation_amount' => $processingLetter->gl_amount,
                'date_received_soa' => $processingLetter->gl_date->copy()->addDay()->toDateString(),
                'date_pulled_out' => $processingLetter->gl_date->copy()->addDays(2)->toDateString(),
            ]),
        )->assertUnprocessable()->assertJsonValidationErrors('batches');

        $paid = GuaranteeLetter::query()->where('status_id', $this->statusId('Paid'))->firstOrFail();
        $this->putJson(
            '/api/v1/guarantee-letters/'.$paid->id,
            $this->payload($paid, ['date_fwd_to_cash' => null]),
        )->assertUnprocessable()->assertJsonValidationErrors('date_fwd_to_cash');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(GuaranteeLetter $letter, array $overrides = []): array
    {
        return array_merge($letter->only([
            'office_id', 'gl_no', 'gl_date', 'client_id', 'beneficiary_id', 'address',
            'relationship_id', 'service_provider_id', 'branch_id', 'fund_source_id',
            'assistance_type_id', 'status_id', 'gl_amount', 'obligation_amount',
            'signatory_id', 'swo_id', 'gl_validity', 'category_id', 'norsa_no',
            'date_received_gl', 'date_received_soa', 'date_pulled_out',
            'date_fwd_to_reviewer', 'date_fwd_to_dc', 'date_fwd_to_dir_ors',
            'date_fwd_to_budget', 'date_fwd_to_actg', 'date_fwd_to_dir_dv',
            'date_fwd_to_cash', 'created_by',
        ]), $overrides);
    }

    private function statusId(string $name): int
    {
        return Status::query()->where('name', $name)->firstOrFail()->id;
    }
}
