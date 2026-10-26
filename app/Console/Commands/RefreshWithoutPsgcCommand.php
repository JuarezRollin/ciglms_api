<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

#[Signature('db:fresh-no-psgc {--seed : Seed the database after refreshing} {--seeder= : The class name of the database seeder to run} {--force : Force the operation to run when in production and skip confirmation}')]
#[Description('Drop non-PSGC tables and rerun migrations while preserving PSGC data')]
class RefreshWithoutPsgcCommand extends Command
{
    private const PSGC_TABLES = [
        'psgc_regions',
        'psgc_provinces',
        'psgc_city_4q25',
        'psgc_barangays',
    ];

    private const PSGC_MIGRATION = '2026_08_31_000000_create_psgc_tables';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            throw new RuntimeException('This command cannot be run in production unless --force is provided.');
        }

        if ($this->option('seeder') && ! $this->option('seed')) {
            $this->components->error('The --seeder option requires --seed.');

            return self::INVALID;
        }

        foreach (self::PSGC_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                $this->components->error("Required PSGC table {$table} does not exist. Run migrations before refreshing.");

                return self::FAILURE;
            }
        }

        if (! is_file(database_path('migrations/'.self::PSGC_MIGRATION.'.php'))) {
            $this->components->error('The PSGC migration file is missing.');

            return self::FAILURE;
        }

        $this->warn('This will drop all non-PSGC database tables and their data.');

        if (! $this->option('force') && ! $this->confirm('Do you want to continue?')) {
            $this->info('Aborted.');

            return self::FAILURE;
        }

        $preservedMigrations = Schema::hasTable('migrations')
            ? DB::table('migrations')->where('migration', 'like', '%psgc%')->pluck('migration')->all()
            : [];
        $preservedMigrations = array_unique([self::PSGC_MIGRATION, ...$preservedMigrations]);

        $tablesToDrop = array_filter(
            Schema::getTableListing(schemaQualified: false),
            fn (string $table): bool => ! str_starts_with(strtolower($table), 'psgc_'),
        );

        Schema::disableForeignKeyConstraints();

        try {
            foreach ($tablesToDrop as $table) {
                Schema::dropIfExists($table);
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        if ($this->call('migrate:install', ['--no-interaction' => true]) !== self::SUCCESS) {
            return self::FAILURE;
        }

        DB::table('migrations')->insert(array_map(
            fn (string $migration): array => ['migration' => $migration, 'batch' => 1],
            $preservedMigrations,
        ));

        $migrateOptions = ['--force' => true, '--no-interaction' => true];

        if ($this->option('seed')) {
            $migrateOptions['--seed'] = true;

            if ($this->option('seeder')) {
                $migrateOptions['--seeder'] = $this->option('seeder');
            }
        }

        if ($this->call('migrate', $migrateOptions) !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->components->info('Database refreshed without dropping the PSGC tables.');

        return self::SUCCESS;
    }
}
