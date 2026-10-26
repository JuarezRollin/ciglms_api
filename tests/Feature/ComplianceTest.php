<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Compliance;
use App\Models\ComplianceType;
use App\Models\GuaranteeLetter;
use Database\Seeders\ComplianceTypeSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class ComplianceTest extends TestCase
{
    private Migration $complianceMigration;

    private Migration $complianceTypeMigration;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('guarantee_letters', function (Blueprint $table): void {
            $table->id();
            $table->decimal('gl_amount', 15, 2)->nullable();
            $table->timestamps();
        });
        Schema::create('batches', function (Blueprint $table): void {
            $table->id();
            $table->decimal('total_amount', 15, 2)->default('0.00');
            $table->timestamps();
        });
        Schema::create('batch_gl', function (Blueprint $table): void {
            $table->foreignId('batch_id');
            $table->foreignId('gl_id');
            $table->unique(['batch_id', 'gl_id']);
        });

        $this->complianceTypeMigration = require database_path('migrations/2026_09_24_135955_create_compliance_types_table.php');
        $this->complianceTypeMigration->up();
        $this->seed(ComplianceTypeSeeder::class);

        $this->complianceMigration = require database_path('migrations/2026_09_24_135956_create_compliances_table.php');
        $this->complianceMigration->up();
    }

    protected function tearDown(): void
    {
        $this->complianceMigration->down();
        $this->complianceTypeMigration->down();
        Schema::dropIfExists('batch_gl');
        Schema::dropIfExists('batches');
        Schema::dropIfExists('guarantee_letters');

        parent::tearDown();
    }

    public function test_compliance_types_are_seeded_once_without_overwriting_their_active_status(): void
    {
        $inactiveType = ComplianceType::query()->where('name', 'Voucher Creation to Encoder')->firstOrFail();
        $inactiveType->update(['is_active' => false]);
        $this->seed(ComplianceTypeSeeder::class);

        $this->assertDatabaseCount('compliance_types', 7);
        $this->assertTrue(Schema::hasForeignKey('compliances', ['compliance_type_id']));
        $this->assertDatabaseHas('compliance_types', [
            'name' => 'Voucher Creation to Encoder',
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('compliance_types', [
            'name' => 'Cash to Reviewer',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('compliance_types', [
            'name' => 'GL Processing to GL Creation',
            'is_active' => true,
        ]);
    }

    public function test_new_compliance_type_can_be_used_without_changing_the_model_or_seeder(): void
    {
        $type = ComplianceType::query()->create([
            'name' => 'New Review Step',
        ]);

        $glCompliance = $this->letter()->compliances()->create([
            'compliance_type_id' => $type->id,
            'returned_at' => '2026-09-20 09:00:00',
        ]);
        $batchCompliance = Batch::query()->create()->compliances()->create([
            'compliance_type_id' => $type->id,
            'returned_at' => '2026-09-20 10:00:00',
            'remarks' => 'Batch needs review.',
        ]);

        $this->seed(ComplianceTypeSeeder::class);

        $this->assertDatabaseCount('compliance_types', 8);
        $this->assertTrue($type->fresh()->is_active);
        $this->assertSame($type->id, $glCompliance->complianceType->id);
        $this->assertSame($type->id, $batchCompliance->complianceType->id);
        $this->assertNull($glCompliance->remarks);
    }

    public function test_batch_flag_tracks_its_own_and_member_gl_compliances(): void
    {
        $batch = Batch::query()->create();
        $glX = $this->letter();
        $glY = $this->letter();
        $glZ = $this->letter();

        DB::table('batch_gl')->insert([
            ['batch_id' => $batch->id, 'gl_id' => $glX->id],
            ['batch_id' => $batch->id, 'gl_id' => $glY->id],
            ['batch_id' => $batch->id, 'gl_id' => $glZ->id],
        ]);

        $batchCompliance = $batch->compliances()->create([
            'compliance_type_id' => $this->typeId('Box A Reviewer to Reviewer'),
            'returned_at' => '2026-09-20 09:00:00',
            'remarks' => 'Batch documents need review.',
        ]);
        $glXCompliance = $glX->compliances()->create([
            'compliance_type_id' => $this->typeId('Voucher Creation to Encoder'),
            'returned_at' => '2026-09-20 10:00:00',
            'remarks' => 'Correct GL X details.',
        ]);
        $glYCompliance = $glY->compliances()->create([
            'compliance_type_id' => $this->typeId('Reviewer to Voucher Creation'),
            'returned_at' => '2026-09-20 11:00:00',
            'remarks' => 'Correct GL Y voucher.',
        ]);

        $this->assertTrue($batch->hasOpenCompliance());
        $this->assertTrue($glX->hasOpenCompliance());
        $this->assertTrue($glY->hasOpenCompliance());
        $this->assertFalse($glZ->hasOpenCompliance());
        $this->assertSame([$glX->id, $glY->id], $batch->glsWithOpenCompliance()
            ->orderBy('guarantee_letters.id')->pluck('guarantee_letters.id')->all());
        $this->assertSame(1, $batch->compliances()->count());
        $this->assertSame('Box A Reviewer to Reviewer', $batchCompliance->complianceType->name);

        $batchCompliance->update(['resolved_at' => '2026-09-21 09:00:00']);
        $this->assertTrue($batch->hasOpenCompliance());

        $glXCompliance->update(['resolved_at' => '2026-09-21 10:00:00']);
        $this->assertTrue($batch->hasOpenCompliance());
        $this->assertFalse($glX->hasOpenCompliance());

        $glYCompliance->update(['resolved_at' => '2026-09-21 11:00:00']);
        $this->assertFalse($batch->hasOpenCompliance());
        $this->assertSame(0, $batch->glsWithOpenCompliance()->count());
    }

    public function test_compliance_rejects_invalid_targets_and_inactive_types(): void
    {
        $batch = Batch::query()->create();
        $gl = $this->letter();
        $inactiveType = ComplianceType::query()->create(['name' => 'Inactive Type', 'is_active' => false]);
        $base = [
            'compliance_type_id' => $this->typeId('Box A Reviewer to Reviewer'),
            'returned_at' => '2026-09-20 09:00:00',
            'remarks' => 'Needs correction.',
        ];
        $invalidCases = [
            [[], 'A compliance must belong to a guarantee letter or batch.'],
            [['batch_id' => $batch->id, 'compliance_type_id' => $inactiveType->id], 'The compliance type is inactive.'],
            [['guarantee_letter_id' => $gl->id, 'compliance_type_id' => 999999], 'The compliance type is invalid.'],
        ];

        foreach ($invalidCases as [$attributes, $message]) {
            try {
                Compliance::query()->create([...$base, ...$attributes]);
                $this->fail('Invalid compliance should not be saved.');
            } catch (InvalidArgumentException $exception) {
                $this->assertSame($message, $exception->getMessage());
            }
        }

        $this->assertDatabaseCount('compliances', 0);

        $voucherCompliance = Compliance::query()->create([
            ...$base,
            'batch_id' => $batch->id,
            'guarantee_letter_id' => $gl->id,
        ]);

        $this->assertSame($batch->id, $voucherCompliance->batch_id);
        $this->assertSame($gl->id, $voucherCompliance->guarantee_letter_id);
    }

    public function test_existing_compliance_can_be_fixed_after_its_type_is_deactivated(): void
    {
        $type = ComplianceType::query()->where('name', 'Voucher Creation to Encoder')->firstOrFail();
        $compliance = Batch::query()->create()->compliances()->create([
            'compliance_type_id' => $type->id,
            'returned_at' => '2026-09-20 09:00:00',
        ]);

        $type->update(['is_active' => false]);
        $compliance->update(['resolved_at' => '2026-09-21 09:00:00']);

        $this->assertSame('2026-09-21 09:00:00', $compliance->fresh()->resolved_at->toDateTimeString());
        $this->assertFalse($type->fresh()->is_active);
    }

    public function test_compliance_keeps_repeated_returns_and_rejects_a_fix_before_return(): void
    {
        $gl = $this->letter();
        $first = $gl->compliances()->create([
            'compliance_type_id' => $this->typeId('Voucher Creation to Encoder'),
            'returned_at' => '2026-09-20 09:00:00',
            'resolved_at' => '2026-09-20 15:00:00',
            'remarks' => 'First return.',
        ]);
        $second = $gl->compliances()->create([
            'compliance_type_id' => $this->typeId('Voucher Creation to Encoder'),
            'returned_at' => '2026-09-21 09:00:00',
            'remarks' => 'Returned again.',
        ]);

        $this->assertSame(2, $gl->compliances()->count());
        $this->assertSame($second->id, $gl->compliances()->open()->sole()->id);
        $this->assertTrue($gl->hasOpenCompliance());
        $this->assertSame('2026-09-20 15:00:00', $first->resolved_at->toDateTimeString());

        $this->expectException(InvalidArgumentException::class);
        $second->update(['resolved_at' => '2026-09-20 08:00:00']);
    }

    private function letter(): GuaranteeLetter
    {
        return GuaranteeLetter::withoutEvents(fn (): GuaranteeLetter => GuaranteeLetter::query()->create());
    }

    private function typeId(string $name): int
    {
        return ComplianceType::query()->where('name', $name)->firstOrFail()->id;
    }
}
