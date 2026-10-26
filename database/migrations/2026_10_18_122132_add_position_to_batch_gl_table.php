<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batch_gl', function (Blueprint $table): void {
            $table->unsignedInteger('position')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('batch_gl', function (Blueprint $table): void {
            $table->dropColumn('position');
        });
    }
};
