<?php

namespace Tests\Feature;

use App\Models\AssistanceType;
use App\Models\Batch;
use App\Models\Beneficiary;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Cheque;
use App\Models\Client;
use App\Models\Dv;
use App\Models\FundSource;
use App\Models\GuaranteeLetter;
use App\Models\Office;
use App\Models\Ors;
use App\Models\PsgcRegion;
use App\Models\Relationship;
use App\Models\ServiceProvider;
use App\Models\Signatory;
use App\Models\Status;
use App\Models\Swo;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => 'batch-tests-only-secret-0123456789abcdef']);
    }

    public function test_membership_and_amount_changes_keep_decimal_totals_current(): void
    {
        $first = $this->letter('0.10');
        $second = $this->letter('0.20');
        $unknown = $this->letter('0.00');
        $batch = Batch::factory()->create();
        $other = Batch::factory()->create();

        $batch->syncGls([$first->id, $second->id, $unknown->id]);
        $first->batches()->attach($other);

        $this->assertSame('0.30', $batch->fresh()->total_amount);
        $this->assertSame('0.10', $other->fresh()->total_amount);
        $this->assertSame(2, $first->batches()->count());
        $first->update(['gl_amount' => '1000000.01']);
        $this->assertSame('1000000.21', $batch->fresh()->total_amount);
        $this->assertSame('1000000.01', $other->fresh()->total_amount);
        $batch->gls()->detach($second);
        $this->assertSame('1000000.01', $batch->fresh()->total_amount);
        $first->delete();
        $this->assertSame('0.00', $batch->fresh()->total_amount);
        $this->assertSame('0.00', $other->fresh()->total_amount);
        $this->assertDatabaseMissing('batch_gl', ['gl_id' => $first->id]);
        $batch->gls()->detach();
        $this->assertSame(0, $batch->gls()->count());
    }

    public function test_sync_replaces_members_and_batch_deletion_keeps_letters(): void
    {
        $first = $this->letter('5000.00');
        $second = $this->letter('3500.25');
        $batch = Batch::factory()->create();
        $batch->syncGls([$first->id]);

        $batch->syncGls([$second->id]);

        $this->assertSame('3500.25', $batch->fresh()->total_amount);
        $this->assertDatabaseMissing('batch_gl', ['batch_id' => $batch->id, 'gl_id' => $first->id]);
        $batch->delete();
        $this->assertDatabaseMissing('batch_gl', ['batch_id' => $batch->id]);
        $this->assertModelExists($first);
        $this->assertModelExists($second);
    }

    public function test_duplicate_pivot_records_are_rejected_without_changing_total(): void
    {
        $letter = $this->letter('5000.00');
        $batch = Batch::factory()->create();
        $batch->gls()->attach($letter);

        try {
            $batch->gls()->attach($letter);
            $this->fail('Duplicate membership should fail.');
        } catch (QueryException) {
            $this->assertSame(1, $batch->gls()->count());
            $this->assertSame('5000.00', $batch->fresh()->total_amount);
        }
    }

    /** @return array<string, array{class-string, string}> */
    public static function accountingReferences(): array
    {
        return [
            'voucher' => [Voucher::class, 'voucher'],
            'ors' => [Ors::class, 'ors'],
            'dv' => [Dv::class, 'dv'],
            'cheque' => [Cheque::class, 'cheque'],
        ];
    }

    #[DataProvider('accountingReferences')]
    public function test_deleting_accounting_reference_is_rejected_while_a_batch_uses_it(string $model, string $relationship): void
    {
        $reference = $model::factory()->create();
        $batch = Batch::factory()->create([$relationship.'_id' => $reference->id]);
        $this->assertTrue($batch->{$relationship}->is($reference));
        $this->assertTrue($reference->batches->sole()->is($batch));

        try {
            $reference->delete();
            $this->fail('A batch must keep its accounting reference.');
        } catch (QueryException) {
            $this->assertSame($reference->id, $batch->fresh()->{$relationship.'_id'});
        }
        $this->assertModelExists($batch);
    }

    #[DataProvider('accountingReferences')]
    public function test_accounting_reference_gets_distinct_gls_through_its_batches(string $model, string $relationship): void
    {
        $reference = $model::factory()->create();
        $first = $this->letter('100.00');
        $second = $this->letter('200.00');
        $firstBatch = Batch::factory()->create([$relationship.'_id' => $reference->id]);
        $secondBatch = Batch::factory()->create([$relationship.'_id' => $reference->id]);
        $firstBatch->syncGls([$first->id, $second->id]);
        $secondBatch->syncGls([$first->id]);

        $gls = $reference->gls()->orderBy('guarantee_letters.id')->get();

        $this->assertCount(2, $gls);
        $this->assertTrue($gls[0]->is($first));
        $this->assertTrue($gls[1]->is($second));
    }

    public function test_invalid_accounting_foreign_key_is_rejected(): void
    {
        $this->expectException(QueryException::class);
        Batch::factory()->create(['voucher_id' => 999999]);
    }

    public function test_api_returns_batches_and_searches_accounting_numbers(): void
    {
        $letter = $this->letter('5000.25');
        $cheque = Cheque::factory()->create([
            'cheque_no' => 'CHEQUE-SEARCH-123',
            'cheque_date' => '2026-09-09',
        ]);
        $batch = Batch::factory()->create(['cheque_id' => $cheque->id]);
        $batch->syncGls([$letter->id]);
        $this->actingAs(User::findOrFail($letter->created_by), 'api');

        $this->getJson('/api/v1/guarantee-letters?search=CHEQUE-SEARCH-123')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $letter->id)
            ->assertJsonPath('data.0.batches.0.total_amount', '5000.25')
            ->assertJsonPath('data.0.batches.0.cheque.cheque_no', 'CHEQUE-SEARCH-123')
            ->assertJsonPath('data.0.batches.0.cheque.cheque_date', '2026-09-09T00:00:00.000000Z')
            ->assertJsonMissingPath('data.0.cheque_no')
            ->assertJsonMissingPath('data.0.cheque_date')
            ->assertJsonMissingPath('data.0.batch_items');
    }

    public function test_failed_sync_restores_memberships_and_total(): void
    {
        $letter = $this->letter('123.45');
        $batch = Batch::factory()->create();
        $batch->syncGls([$letter->id]);

        try {
            $batch->syncGls([999999]);
            $this->fail('Invalid GL should fail.');
        } catch (QueryException) {
            $this->assertDatabaseHas('batch_gl', ['batch_id' => $batch->id, 'gl_id' => $letter->id]);
            $this->assertSame('123.45', $batch->fresh()->total_amount);
        }
    }

    public function test_direct_pivot_key_updates_are_rejected_without_changing_membership(): void
    {
        $letter = $this->letter('123.45');
        $batch = Batch::factory()->create();
        $other = Batch::factory()->create();
        $batch->syncGls([$letter->id]);

        try {
            $batch->gls()->updateExistingPivot($letter->id, ['batch_id' => $other->id]);
            $this->fail('Pivot keys should only change through membership methods.');
        } catch (\LogicException) {
            $this->assertDatabaseHas('batch_gl', ['batch_id' => $batch->id, 'gl_id' => $letter->id]);
            $this->assertSame('123.45', $batch->fresh()->total_amount);
            $this->assertSame('0.00', $other->fresh()->total_amount);
        }
    }

    public function test_api_rejects_accounting_references_on_individual_letters(): void
    {
        $letter = $this->letter('123.45');
        $this->actingAs(User::findOrFail($letter->created_by), 'api');
        $payload = [
            'gl_no' => $letter->gl_no, 'gl_date' => '2026-09-09',
            'client_id' => $letter->client_id, 'branch_id' => $letter->branch_id,
            'fund_source_id' => $letter->fund_source_id, 'assistance_type_id' => $letter->assistance_type_id,
            'created_by' => $letter->created_by,
            'voucher_no' => 'V-1', 'voucher_id' => 1, 'ors_no' => 'ORS-1', 'ors_id' => 1,
            'dv_no' => 'DV-1', 'dv_id' => 1, 'cheque_no' => 'C-1', 'cheque_id' => 1,
            'cheque_date' => '2026-09-09',
        ];

        $this->putJson('/api/v1/guarantee-letters/'.$letter->id, $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['voucher_no', 'voucher_id', 'ors_no', 'ors_id', 'dv_no', 'dv_id', 'cheque_no', 'cheque_id', 'cheque_date']);
        $this->assertSame('123.45', $letter->fresh()->gl_amount);
        $this->assertDatabaseCount('batches', 0);
    }

    public function test_pivot_foreign_keys_reject_missing_gl(): void
    {
        $batch = Batch::factory()->create();

        $this->expectException(QueryException::class);
        $batch->gls()->attach(999999);
    }

    private function letter(string $amount): GuaranteeLetter
    {
        $region = PsgcRegion::query()->firstOrCreate(
            ['code' => '0000000020'],
            ['code_correspondence' => '000000020', 'name' => 'Central Office'],
        );
        $provider = ServiceProvider::query()->firstOrCreate(['name' => 'Batch Test Provider'], [
            'region_id' => $region->reg_id, 'address' => 'Manila', 'salutation' => 'Dear', 'addressee' => 'Director', 'designation' => 'Director',
        ]);
        $branch = Branch::query()->firstOrCreate(['name' => 'Batch Test Branch'], ['service_provider_id' => $provider->id, 'address' => 'Manila']);
        $client = Client::query()->firstOrCreate(['full_name' => 'Batch Test Client'], ['sex' => 1, 'address' => 'Manila']);
        $beneficiary = Beneficiary::query()->firstOrCreate(['full_name' => 'Batch Test Beneficiary']);
        $office = Office::query()->firstOrCreate(['name' => 'Batch Test Office'], ['region_id' => $region->reg_id]);
        $relationship = Relationship::query()->firstOrCreate(['name' => 'Self']);
        $signatory = Signatory::query()->firstOrCreate(['name' => 'Batch Test Signatory'], [
            'designation' => 'Director', 'minimum_amount' => '0.00', 'maximum_amount' => '99999999.99',
        ]);
        $status = Status::query()->firstOrCreate(['name' => 'Awaiting SOA']);
        $swo = Swo::query()->firstOrCreate(['name' => 'Batch Test SWO']);
        $fund = FundSource::query()->firstOrCreate(['name' => 'Batch Test Fund']);
        $assistance = AssistanceType::query()->firstOrCreate(['name' => 'Batch Test Assistance']);
        $user = User::query()->first() ?? User::factory()->create();

        return GuaranteeLetter::query()->create([
            'gl_no' => 'GL-'.(DB::table('guarantee_letters')->count() + 1),
            'gl_date' => '2026-09-09', 'gl_amount' => $amount, 'gl_validity' => '2026-10-09',
            'office_id' => $office->id, 'client_id' => $client->id, 'beneficiary_id' => $beneficiary->id,
            'address' => 'Manila', 'relationship_id' => $relationship->id,
            'service_provider_id' => $provider->id, 'branch_id' => $branch->id,
            'category_id' => Category::query()->firstOrCreate(['name' => 'Batch Test Category'])->id,
            'fund_source_id' => $fund->id, 'assistance_type_id' => $assistance->id,
            'status_id' => $status->id, 'signatory_id' => $signatory->id, 'swo_id' => $swo->id,
            'created_by' => $user->id,
        ]);
    }
}
