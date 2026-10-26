<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FreshDatabaseTest extends TestCase
{
    public function test_fresh_seed_creates_final_schema_and_rolls_back_cleanly(): void
    {
        config(['jwt.secret' => 'fresh-tests-only-secret-0123456789abcdef']);

        $this->artisan('migrate:fresh', ['--seed' => true, '--force' => true])->assertSuccessful();

        $this->assertDatabaseCount('users', 22);
        $this->assertSame(0, User::query()->whereNull('office_id')->count());
        $this->assertDatabaseHas('roles', ['name' => 'admin']);
        $this->assertDatabaseCount('statuses', 17);
        $this->assertDatabaseHas('statuses', ['id' => 1, 'name' => 'For Review by CRD']);
        $this->assertTrue(Schema::hasColumn('statuses', 'is_active'));
        $this->assertFalse(Schema::hasColumn('statuses', 'description'));
        $this->assertDatabaseHas('categories', ['name' => 'Hospital Bill']);
        $this->assertDatabaseHas('assistance_types', ['name' => 'Funeral', 'is_active' => true]);
        $this->assertDatabaseMissing('assistance_types', ['name' => 'Burial']);
        $this->assertTrue(Schema::hasColumn('guarantee_letters', 'category_id'));
        $this->assertTrue(Schema::hasColumn('guarantee_letters', 'date_fwd_to_dc'));
        $this->assertTrue(Schema::hasColumn('guarantee_letters', 'box_a_reviewed_by'));
        $this->assertFalse(Schema::hasColumn('guarantee_letters', 'date_signed_by_cip_dir'));
        $this->assertFalse(Schema::hasColumn('batches', 'box_a_reviewed_by'));
        $this->assertFalse(Schema::hasColumn('batches', 'status_id'));
        $this->assertFalse(Schema::hasColumn('batches', 'date_fwd_to_box_a'));
        $this->assertFalse(Schema::hasColumn('guarantee_letters', 'compliance_remarks'));
        $this->assertTrue(Schema::hasTable('compliances'));
        $this->assertTrue(Schema::hasColumn('cheques', 'cheque_date'));
        foreach (['category', 'voucher_no', 'voucher_id', 'ors_no', 'ors_id', 'dv_no', 'dv_id', 'cheque_no', 'cheque_id', 'cheque_date'] as $column) {
            $this->assertFalse(Schema::hasColumn('guarantee_letters', $column));
        }
        foreach (['glbatches', 'glbatch_items', 'legacy_gl_accounting'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
        $this->assertSame(['batch_id', 'gl_id'], Schema::getColumnListing('batch_gl'));
        $this->assertTrue(Schema::hasTable('gl_transmittals'));
        $this->assertTrue(Schema::hasColumn('gl_transmittals', 'received_by'));
        $this->assertTrue(Schema::hasForeignKey('gl_transmittals', ['received_by']));
        $this->assertTrue(Schema::hasTable('gl_transmittal'));
        $this->assertDatabaseCount('guarantee_letters', 850);
        $this->assertDatabaseCount('batches', 7);
        $this->assertDatabaseCount('batch_gl', 350);

        $this->artisan('migrate:rollback', ['--force' => true])->assertSuccessful();

        foreach (['guarantee_letters', 'categories', 'batch_gl', 'batches', 'vouchers', 'ors', 'dv', 'cheques', 'compliances', 'gl_transmittals', 'gl_transmittal'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
    }
}
