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
        Schema::create('gl_transmittals', function (Blueprint $table): void {
            $table->id();
            $table->string('bundle_no', 100)->unique();
            $table->foreignId('category_id')->constrained('categories');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gl_transmittals');
    }
};
