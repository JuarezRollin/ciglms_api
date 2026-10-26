<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('gl_transmittal', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gl_transmittal_id')->constrained('gl_transmittals')->cascadeOnDelete();
            $table->foreignId('gl_id')->constrained('guarantee_letters');
            $table->unsignedTinyInteger('position');
            $table->unique(['gl_transmittal_id', 'gl_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gl_transmittal');
    }
};
