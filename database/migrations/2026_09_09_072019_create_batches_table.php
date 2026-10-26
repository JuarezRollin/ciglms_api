<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('voucher_id')->index()->constrained('vouchers');
            $table->foreignId('ors_id')->nullable()->index()->constrained('ors');
            $table->foreignId('dv_id')->nullable()->index()->constrained('dv');
            $table->foreignId('cheque_id')->nullable()->index()->constrained('cheques');
            $table->decimal('total_amount', 15, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
