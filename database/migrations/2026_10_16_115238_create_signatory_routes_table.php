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
        Schema::create('signatory_routes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guarantee_letter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('signatory_id')->constrained();
            $table->unsignedInteger('sequence');
            $table->dateTime('date_sent')->nullable();
            $table->dateTime('date_returned')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['guarantee_letter_id', 'sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signatory_routes');
    }
};
