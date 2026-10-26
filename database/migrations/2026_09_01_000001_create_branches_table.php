<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id('id');
            $table->foreignId('service_provider_id');
            $table->string('name');
            $table->text('address');
            $table->string('branch_salutation')->nullable();
            $table->string('branch_addressee')->nullable();
            $table->string('branch_designation')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('service_provider_id')->references('id')->on('service_providers');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
