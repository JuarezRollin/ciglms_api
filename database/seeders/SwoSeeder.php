<?php

namespace Database\Seeders;

use App\Models\Swo;
use App\Services\XlsxSheetReader;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SwoSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $path = database_path('swos/swos.xlsx');

        DB::transaction(function () use ($path): void {
            $count = 0;

            foreach (app(XlsxSheetReader::class)->rows($path, 'Sheet1') as $row) {
                $name = trim($row['cells']['A'] ?? '');
                $position = trim($row['cells']['B'] ?? '');

                if ($row['row'] === 1) {
                    if ($name !== 'Name' || $position !== 'Position') {
                        throw new RuntimeException('The SWO workbook has unexpected column headers.');
                    }

                    continue;
                }

                if ($name === '' || $position === '') {
                    throw new RuntimeException("Missing SWO name or position on workbook row {$row['row']}.");
                }

                Swo::query()->updateOrCreate(['name' => $name], ['position' => $position]);
                $count++;
            }

            if ($count === 0) {
                throw new RuntimeException('The SWO workbook contains no data rows.');
            }
        });
    }
}
