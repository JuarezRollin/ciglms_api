<?php

namespace Tests\Feature\Models;

use App\Models\AssistanceType;
use App\Models\Branch;
use App\Models\FundSource;
use App\Models\GuaranteeLetter;
use App\Models\Relationship;
use App\Models\ServiceProvider;
use App\Models\Signatory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GuaranteeLetterRelationshipsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_amount_matrix_includes_its_minimum_and_maximum_amounts(): void
    {
        Signatory::query()->create([
            'name' => 'Sample Signatory',
            'designation' => 'Regional Director',
            'minimum_amount' => 10001,
            'maximum_amount' => 150000,
        ]);

        $this->assertSame('Sample Signatory', Signatory::coveringAmount(10001)->sole()->name);
        $this->assertSame('Sample Signatory', Signatory::coveringAmount(150000)->sole()->name);
        $this->assertSame(0, Signatory::coveringAmount(10000)->count());
        $this->assertSame(0, Signatory::coveringAmount(150001)->count());
    }

    public function test_guarantee_letter_defines_relationship_and_signatory_associations(): void
    {
        $guaranteeLetter = new GuaranteeLetter;

        $this->assertSame(Relationship::class, $guaranteeLetter->relationship()->getRelated()::class);
        $this->assertSame(Signatory::class, $guaranteeLetter->signatory()->getRelated()::class);
        $this->assertSame(ServiceProvider::class, $guaranteeLetter->serviceProvider()->getRelated()::class);
    }

    public function test_service_provider_reaches_guarantee_letters_directly(): void
    {
        $serviceProvider = new ServiceProvider;

        $this->assertSame(GuaranteeLetter::class, $serviceProvider->guaranteeLetters()->getRelated()::class);
    }

    public function test_guarantee_letter_accepts_gl_creation_columns(): void
    {
        $guaranteeLetter = new GuaranteeLetter([
            'swo_id' => 12,
            'address' => 'Manila',
            'referring_party_id' => 34,
            'creation_remarks' => 'For review',
            'created_by' => 56,
        ]);

        $this->assertSame(12, $guaranteeLetter->swo_id);
        $this->assertSame('Manila', $guaranteeLetter->address);
        $this->assertSame(34, $guaranteeLetter->referring_party_id);
        $this->assertSame('For review', $guaranteeLetter->creation_remarks);
        $this->assertSame(56, $guaranteeLetter->created_by);
    }

    public function test_guarantee_letter_accepts_workflow_dates_and_separates_system_managed_columns(): void
    {
        $guaranteeLetter = new GuaranteeLetter;
        $columns = Schema::getColumnListing('guarantee_letters');

        $this->assertSame([], array_values(array_diff($guaranteeLetter->getFillable(), $columns)));
        foreach (['signed_pdf_path', 'signed_pdf_filename', 'signed_at'] as $column) {
            $this->assertContains($column, $columns);
            $this->assertNotContains($column, $guaranteeLetter->getFillable());
        }
        $this->assertSame([], array_values(array_diff($columns, $guaranteeLetter->getFillable(), ['id', 'created_at', 'updated_at', 'signed_pdf_path', 'signed_pdf_filename', 'signed_at'])));

        $workflowActors = [
            'reviewed_by_creation',
            'approved_by',
            'released_by',
            'endorsed_by',
            'onhold_by',
            'cancelled_by_creation',
            'received_by',
            'reviewed_by_rmcd',
            'cancelled_by_rmcd',
        ];
        $guaranteeLetter->fill(array_fill_keys($workflowActors, 12));
        foreach ($workflowActors as $column) {
            $this->assertSame(12, $guaranteeLetter->{$column});
        }

        $workflowDates = [
            'date_reviewed_by_creation',
            'date_approved',
            'date_released',
            'date_endorsed',
            'date_onhold',
            'date_cancelled_by_creation',
            'date_cancelled_by_rmcd',
        ];
        $guaranteeLetter->fill([
            'date_fwd_to_dir_fs' => '2026-09-21',
            'date_received_gl' => '2026-09-23 14:30:00',
        ] + array_fill_keys($workflowDates, '2026-09-24 15:30:00'));

        $this->assertSame('2026-09-21', $guaranteeLetter->date_fwd_to_dir_fs->toDateString());
        $this->assertSame('2026-09-23 14:30:00', $guaranteeLetter->date_received_gl->toDateTimeString());
        foreach ($workflowDates as $column) {
            $this->assertContains($column, $columns);
            $this->assertSame('2026-09-24 15:30:00', $guaranteeLetter->{$column}->toDateTimeString());
        }
    }

    public function test_models_accept_the_columns_defined_by_their_migrations(): void
    {
        $serviceProvider = new ServiceProvider([
            'name' => 'Sample Provider',
            'address' => 'Sample Address',
            'salutation' => 'Dear Sir/Madam',
            'addressee' => 'Sample Addressee',
            'designation' => 'Administrator',
            'is_active' => 1,
        ]);
        $branch = new Branch([
            'branch_salutation' => 'Dear Doctor',
            'branch_addressee' => 'Sample Branch Addressee',
            'branch_designation' => 'Medical Director',
            'is_active' => 1,
        ]);
        $fundSource = new FundSource(['is_active' => 1]);
        $assistanceType = new AssistanceType(['is_active' => 1]);
        $signatory = new Signatory(['is_active' => 1]);

        $this->assertSame('Sample Addressee', $serviceProvider->addressee);
        $this->assertTrue($serviceProvider->is_active);
        $this->assertSame('Dear Doctor', $branch->branch_salutation);
        $this->assertSame('Sample Branch Addressee', $branch->branch_addressee);
        $this->assertSame('Medical Director', $branch->branch_designation);
        $this->assertTrue($branch->is_active);
        $this->assertTrue($fundSource->is_active);
        $this->assertTrue($assistanceType->is_active);
        $this->assertTrue($signatory->is_active);
    }
}
