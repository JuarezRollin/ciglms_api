<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cheques', function (Blueprint $table): void {
            $table->id();
            $table->string('cheque_no')->index();
            $table->date('cheque_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cheques');
    }
};
