<?php

namespace Tests\Feature;

use App\Models\PsgcRegion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RefreshWithoutPsgcCommandTest extends TestCase
{
    public function test_refresh_preserves_psgc_rows_and_recreates_other_tables(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        PsgcRegion::query()->create([
            'reg_id' => 1,
            'code_correspondence' => '010000000',
            'name' => 'Region I',
            'code' => '0100000000',
        ]);
        DB::table('categories')->insert(['name' => 'Before refresh']);

        $this->artisan('db:fresh-no-psgc', ['--force' => true])->assertSuccessful();

        $this->assertDatabaseHas('psgc_regions', ['reg_id' => 1, 'name' => 'Region I']);
        $this->assertDatabaseCount('categories', 0);
        $this->assertSame(1, DB::table('migrations')->where('migration', '2026_08_31_000000_create_psgc_tables')->count());

        $this->artisan('db:fresh-no-psgc', [
            '--force' => true,
            '--seed' => true,
            '--seeder' => RefreshWithoutPsgcTestSeeder::class,
        ])->assertSuccessful();

        $this->assertDatabaseHas('psgc_regions', ['reg_id' => 1, 'name' => 'Region I']);
        $this->assertDatabaseHas('categories', ['name' => 'Seeded after refresh']);
    }

    public function test_missing_psgc_tables_stop_before_any_table_is_dropped(): void
    {
        Schema::create('temporary_table', function (Blueprint $table): void {
            $table->id();
        });

        $this->artisan('db:fresh-no-psgc', ['--force' => true])->assertFailed();

        $this->assertTrue(Schema::hasTable('temporary_table'));
    }
}

class RefreshWithoutPsgcTestSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('categories')->insert(['name' => 'Seeded after refresh']);
    }
}
