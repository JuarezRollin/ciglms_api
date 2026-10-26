<?php

namespace Tests\Feature;

use App\Models\GuaranteeLetter;
use App\Models\Swo;
use Database\Seeders\SwoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SwoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_workbook_seeds_swos_and_can_be_rerun_without_reactivating_them(): void
    {
        $this->seed(SwoSeeder::class);

        $this->assertDatabaseCount('swos', 127);
        $this->assertDatabaseHas('swos', [
            'name' => 'MORATA, EDWIN S.',
            'position' => 'DIRECTOR IV',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('swos', [
            'name' => 'Dela Cruz, Mariel Z.',
            'position' => 'Project Development Officer IV',
        ]);

        $swo = Swo::query()->where('name', 'MORATA, EDWIN S.')->firstOrFail();
        $swo->update(['position' => 'Changed', 'is_active' => false]);
        $this->seed(SwoSeeder::class);

        $this->assertDatabaseCount('swos', 127);
        $this->assertSame('DIRECTOR IV', $swo->fresh()->position);
        $this->assertFalse($swo->fresh()->is_active);
        $this->assertInstanceOf(Swo::class, (new GuaranteeLetter)->swo()->getRelated());
    }
}
