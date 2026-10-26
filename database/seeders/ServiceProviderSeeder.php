<?php

namespace Database\Seeders;

use App\Models\PsgcRegion;
use App\Models\ServiceProvider;
use App\Services\XlsxSheetReader;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ServiceProviderSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! PsgcRegion::query()->whereKey(20)->where('name', 'Central Office')->exists()) {
            throw new RuntimeException('Import PSGC data before seeding Central Office service providers.');
        }

        $path = database_path('service-providers/Service Provider Database Central Office.xlsx');

        DB::transaction(function () use ($path): void {
            $count = 0;

            foreach (app(XlsxSheetReader::class)->rows($path, 'SERVICE PROVIDER NEW DATA') as $row) {
                if ($row['row'] === 1) {
                    continue;
                }

                $cells = $row['cells'];
                $name = $this->value($cells, 'B');
                $address = $this->value($cells, 'C');
                $moa = $this->value($cells, 'M');

                if ($name === null || $address === null || ! in_array($moa, ['0', '1'], true)) {
                    throw new RuntimeException("Invalid service provider data on workbook row {$row['row']}.");
                }

                $status = $this->value($cells, 'A');
                $jurisdiction = $this->value($cells, 'N');

                ServiceProvider::query()->updateOrCreate(
                    ['source_row' => $row['row']],
                    [
                        'region_id' => 20,
                        'name' => $name,
                        'status' => $status,
                        'address' => $address,
                        'salutation' => $this->value($cells, 'D'),
                        'addressee' => $this->value($cells, 'E'),
                        'designation' => $this->value($cells, 'F'),
                        'category' => $this->value($cells, 'G'),
                        'sp_remarks' => $this->value($cells, 'H'),
                        'contact_number' => $this->value($cells, 'I'),
                        'contact_person' => $this->value($cells, 'J'),
                        'email' => $this->value($cells, 'K'),
                        'business_name' => $this->value($cells, 'L'),
                        'moa' => $moa === '1',
                        'jurisdiction' => $jurisdiction,
                        'is_active' => strcasecmp($status ?? '', 'Active') === 0,
                    ],
                );

                $count++;
            }

            if ($count === 0) {
                throw new RuntimeException('The service provider workbook contains no data rows.');
            }
        });
    }

    /** @param array<string, string> $cells */
    private function value(array $cells, string $column): ?string
    {
        $value = trim($cells[$column] ?? '');

        return $value === '' ? null : $value;
    }
}
