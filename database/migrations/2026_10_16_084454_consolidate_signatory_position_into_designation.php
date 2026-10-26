<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('signatories')
            ->whereNotNull('position')
            ->where('position', '!=', '')
            ->where(function (Builder $query): void {
                $query->whereNull('designation')->orWhereIn('designation', ['', 'Authorized Signatory']);
            })
            ->update(['designation' => DB::raw('position')]);

        DB::table('signatories')->whereNull('designation')->orWhere('designation', '')->update(['designation' => 'Authorized Signatory']);

        Schema::table('signatories', function (Blueprint $table): void {
            $table->dropColumn('position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('signatories', function (Blueprint $table): void {
            $table->string('position')->nullable();
        });
    }
};
