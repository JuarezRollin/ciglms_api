<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_gl', function (Blueprint $table): void {
            $table->foreignId('batch_id')->index()->constrained('batches')->cascadeOnDelete();
            $table->foreignId('gl_id')->index()->constrained('guarantee_letters')->cascadeOnDelete();
            $table->unique(['batch_id', 'gl_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_gl');
    }
};
