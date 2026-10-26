<?php

namespace Tests\Feature;

use App\Models\PsgcBarangay;
use App\Models\PsgcCity;
use App\Models\PsgcProvince;
use App\Models\PsgcRegion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PsgcImportTest extends TestCase
{
    private Migration $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migration = require database_path('migrations/2026_08_31_000000_create_psgc_tables.php');
        $this->migration->up();
    }

    protected function tearDown(): void
    {
        $this->migration->down();

        parent::tearDown();
    }

    public function test_import_uses_dump_table_names_and_can_be_rerun(): void
    {
        $this->artisan('psgc:import-4q25', ['--chunk' => 500])->assertSuccessful();

        $this->assertDatabaseCount('psgc_regions', 19);
        $this->assertDatabaseCount('psgc_provinces', 88);
        $this->assertDatabaseCount('psgc_city_4q25', 1656);
        $this->assertDatabaseCount('psgc_barangays', 42011);
        $this->assertSame('0100000000', PsgcRegion::findOrFail(1)->code);
        $this->assertSame(1, PsgcProvince::findOrFail(1)->parentRegion->reg_id);
        $this->assertSame(1, PsgcCity::findOrFail(1)->parentProvince->prov_id);
        $this->assertSame(1, PsgcBarangay::findOrFail(1)->parentCity->city_id);

        $this->artisan('psgc:import-4q25', ['--chunk' => 500])->assertSuccessful();

        $this->assertDatabaseCount('psgc_barangays', 42011);
    }

    public function test_invalid_chunk_size_does_not_import(): void
    {
        $this->artisan('psgc:import-4q25', ['--chunk' => 0])->assertExitCode(2);

        $this->assertSame(0, PsgcRegion::count());
        $this->assertTrue(Schema::hasTable('psgc_barangays'));
    }
}
