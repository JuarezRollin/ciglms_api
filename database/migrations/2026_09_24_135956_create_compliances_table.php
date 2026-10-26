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
        Schema::create('compliances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guarantee_letter_id')->nullable()->constrained('guarantee_letters');
            $table->foreignId('batch_id')->nullable()->constrained();
            $table->foreignId('compliance_type_id')->constrained('compliance_types');
            $table->dateTime('returned_at');
            $table->dateTime('resolved_at')->nullable();
            $table->integer('duration')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliances');
    }
};
