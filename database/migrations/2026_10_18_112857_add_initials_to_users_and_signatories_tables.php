<?php

use App\Support\NameInitials;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'signatories'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('initials', 30)->nullable()->after('name');
            });

            DB::table($tableName)->select(['id', 'name'])->chunkById(500, function ($records) use ($tableName): void {
                foreach ($records as $record) {
                    DB::table($tableName)->where('id', $record->id)->update([
                        'initials' => NameInitials::fromName($record->name),
                    ]);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['users', 'signatories'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('initials');
            });
        }
    }
};
