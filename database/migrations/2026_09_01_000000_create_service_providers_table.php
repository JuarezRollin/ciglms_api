<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_providers', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedInteger('region_id');
            $table->foreign('region_id')->references('reg_id')->on('psgc_regions');
            $table->unsignedInteger('source_row')->nullable()->unique();
            $table->string('name')->index();
            $table->string('status', 20)->nullable();
            $table->string('address');
            $table->string('salutation')->nullable();
            $table->string('addressee')->nullable();
            $table->string('designation')->nullable();
            $table->string('category', 100)->nullable();
            $table->text('sp_remarks')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('contact_person')->nullable();
            $table->text('email')->nullable();
            $table->string('business_name')->nullable();
            $table->boolean('moa')->default(false);
            $table->string('jurisdiction', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_providers');
    }
};
