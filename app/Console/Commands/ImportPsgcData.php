<?php

namespace App\Console\Commands;

use App\Services\PsgcSqlDumpReader;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

#[Signature('psgc:import-4q25 {--chunk=500 : Number of PSGC rows written per database operation}')]
#[Description('Import the 4Q25 PSGC SQL data into the PSGC tables')]
final class ImportPsgcData extends Command
{
    /**
     * @var list<array{
     *     label: string,
     *     file: string,
     *     table: non-empty-string,
     *     primary_key: non-empty-string,
     *     expected_rows: int,
     *     columns: list<non-empty-string>,
     *     integer_columns: list<non-empty-string>
     * }>
     */
    private const DATASETS = [
        [
            'label' => 'Regions',
            'file' => 'psgc_region_4q25.sql',
            'table' => 'psgc_regions',
            'primary_key' => 'reg_id',
            'expected_rows' => 19,
            'columns' => [
                'reg_id',
                'code_correspondence',
                'name',
                'altName',
                'code',
                'geo_level',
                'remarks',
                'archive',
            ],
            'integer_columns' => ['reg_id', 'archive'],
        ],
        [
            'label' => 'Provinces',
            'file' => 'psgc_province_4q25.sql',
            'table' => 'psgc_provinces',
            'primary_key' => 'prov_id',
            'expected_rows' => 88,
            'columns' => [
                'prov_id',
                'code_correspondence',
                'name',
                'code',
                'geo_level',
                'old_name',
                'income_classification',
                'region',
                'region_correspondence',
                'reg_id',
                'archive',
                'remarks',
            ],
            'integer_columns' => ['prov_id', 'reg_id', 'archive'],
        ],
        [
            'label' => 'Cities and municipalities',
            'file' => 'psgc_city_4q25.sql',
            'table' => 'psgc_city_4q25',
            'primary_key' => 'city_id',
            'expected_rows' => 1656,
            'columns' => [
                'city_id',
                'code_correspondence',
                'name',
                'code',
                'classification',
                'old_name',
                'city_class',
                'income_classification',
                'province',
                'province_correspondence',
                'prov_id',
                'archive',
                'remarks',
            ],
            'integer_columns' => ['city_id', 'prov_id', 'archive'],
        ],
        [
            'label' => 'Barangays',
            'file' => 'psgc_brgy_4q25.sql',
            'table' => 'psgc_barangays',
            'primary_key' => 'brgy_id',
            'expected_rows' => 42011,
            'columns' => [
                'brgy_id',
                'code_correspondence',
                'name',
                'code',
                'geo_level',
                'old_name',
                'city_class',
                'urb_rur',
                'city',
                'city_correspondence',
                'city_id',
                'archive',
                'remarks',
            ],
            'integer_columns' => ['brgy_id', 'city_id', 'archive'],
        ],
    ];

    /**
     * Execute the console command.
     */
    public function handle(PsgcSqlDumpReader $reader): int
    {
        $chunkSize = (int) $this->option('chunk');

        if ($chunkSize < 1 || $chunkSize > 1000) {
            $this->components->error('The --chunk value must be between 1 and 1000.');

            return self::INVALID;
        }

        try {
            $this->validateInputs();
            $lock = $this->acquireLock();
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $startedAt = microtime(true);

        try {
            $summaries = DB::transaction(
                fn (): array => $this->importDatasets($reader, $chunkSize),
                attempts: 1,
            );
        } catch (Throwable $exception) {
            $this->components->error('PSGC import failed and was rolled back: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        $this->newLine();
        $this->table(['Dataset', 'Rows', 'Inserted', 'Updated'], $summaries);
        $this->components->info(sprintf(
            '4Q25 PSGC import completed in %.2f seconds.',
            microtime(true) - $startedAt,
        ));

        return self::SUCCESS;
    }

    private function validateInputs(): void
    {
        foreach (self::DATASETS as $dataset) {
            $path = database_path('psgc/4q25/'.$dataset['file']);

            if (! is_readable($path)) {
                throw new RuntimeException("PSGC SQL file is not readable: {$path}");
            }

            if (! Schema::hasTable($dataset['table'])) {
                throw new RuntimeException(
                    "Required table {$dataset['table']} does not exist. Run the migrations before importing PSGC data.",
                );
            }
        }
    }

    /** @return resource */
    private function acquireLock()
    {
        $path = sys_get_temp_dir().'/psgc-import-4q25-'.sha1(base_path()).'.lock';
        $lock = fopen($path, 'c');

        if ($lock === false) {
            throw new RuntimeException("Unable to open the PSGC import lock: {$path}");
        }

        if (! flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);

            throw new RuntimeException('Another 4Q25 PSGC import is already running.');
        }

        return $lock;
    }

    /**
     * @return list<array{string, int, int, int}>
     */
    private function importDatasets(PsgcSqlDumpReader $reader, int $chunkSize): array
    {
        $summaries = [];

        foreach (self::DATASETS as $dataset) {
            $path = database_path('psgc/4q25/'.$dataset['file']);
            $processed = 0;
            $inserted = 0;
            $updated = 0;
            $chunk = [];

            $this->components->info("Importing {$dataset['label']}...");
            $progressBar = $this->output->createProgressBar($dataset['expected_rows']);
            $progressBar->start();

            foreach ($reader->rows(
                $path,
                $dataset['table'],
                $dataset['columns'],
                $dataset['integer_columns'],
            ) as $row) {
                $chunk[] = $row;
                $processed++;
                $progressBar->advance();

                if (count($chunk) === $chunkSize) {
                    [$chunkInserted, $chunkUpdated] = $this->upsertChunk($dataset, $chunk);
                    $inserted += $chunkInserted;
                    $updated += $chunkUpdated;
                    $chunk = [];
                }
            }

            if ($chunk !== []) {
                [$chunkInserted, $chunkUpdated] = $this->upsertChunk($dataset, $chunk);
                $inserted += $chunkInserted;
                $updated += $chunkUpdated;
            }

            $progressBar->finish();
            $this->newLine();

            if ($processed !== $dataset['expected_rows']) {
                throw new RuntimeException(
                    "{$dataset['file']} contains {$processed} rows; expected {$dataset['expected_rows']}.",
                );
            }

            $summaries[] = [$dataset['label'], $processed, $inserted, $updated];
        }

        return $summaries;
    }

    /**
     * @param  array{table: non-empty-string, primary_key: non-empty-string, columns: list<non-empty-string>}  $dataset
     * @param  list<array<string, int|string|null>>  $rows
     * @return array{int, int}
     */
    private function upsertChunk(array $dataset, array $rows): array
    {
        $primaryKey = $dataset['primary_key'];
        $primaryKeys = array_column($rows, $primaryKey);
        $existing = DB::table($dataset['table'])
            ->whereIn($primaryKey, $primaryKeys)
            ->count();

        DB::table($dataset['table'])->upsert(
            $rows,
            [$primaryKey],
            array_values(array_diff($dataset['columns'], [$primaryKey])),
        );

        return [count($rows) - $existing, $existing];
    }
}
