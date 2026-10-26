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
        Schema::create('psgc_regions', function (Blueprint $table): void {
            $table->increments('reg_id');
            $table->char('code_correspondence', 9)->unique();
            $table->string('name', 100)->index();
            $table->string('altName', 10)->nullable()->index();
            $table->char('code', 10)->unique();
            $table->char('geo_level', 3)->nullable()->default('Reg')->index();
            $table->string('remarks')->nullable();
            $table->boolean('archive')->nullable()->index();
        });

        Schema::create('psgc_provinces', function (Blueprint $table): void {
            $table->increments('prov_id');
            $table->char('code_correspondence', 9)->nullable()->unique();
            $table->string('name', 100)->index();
            $table->char('code', 10)->unique();
            $table->char('geo_level', 4)->nullable();
            $table->string('old_name', 100)->nullable();
            $table->string('income_classification', 20)->nullable();
            $table->char('region', 10)->index();
            $table->char('region_correspondence', 9)->index();
            $table->unsignedInteger('reg_id')->index();
            $table->boolean('archive')->nullable()->index();
            $table->string('remarks')->nullable();

            $table->foreign('reg_id')->references('reg_id')->on('psgc_regions')->restrictOnDelete()->cascadeOnUpdate();
        });

        Schema::create('psgc_city_4q25', function (Blueprint $table): void {
            $table->increments('city_id');
            $table->char('code_correspondence', 9)->nullable()->unique();
            $table->string('name', 150)->index();
            $table->char('code', 10)->unique();
            $table->char('classification', 20)->nullable()->index();
            $table->string('old_name', 150)->nullable();
            $table->string('city_class', 50)->nullable()->index();
            $table->string('income_classification', 50)->nullable()->index();
            $table->char('province', 10)->index();
            $table->char('province_correspondence', 9)->nullable()->index();
            $table->unsignedInteger('prov_id')->index();
            $table->boolean('archive')->nullable()->index();
            $table->string('remarks')->nullable();

            $table->foreign('prov_id')->references('prov_id')->on('psgc_provinces')->restrictOnDelete()->cascadeOnUpdate();
        });

        Schema::create('psgc_barangays', function (Blueprint $table): void {
            $table->increments('brgy_id');
            $table->char('code_correspondence', 9)->nullable()->unique();
            $table->string('name', 150)->index();
            $table->char('code', 10)->nullable()->unique();
            $table->char('geo_level', 3)->nullable()->default('Bgy');
            $table->string('old_name', 150)->nullable();
            $table->string('city_class', 50)->nullable();
            $table->char('urb_rur', 1)->nullable()->index();
            $table->char('city', 10)->nullable()->index();
            $table->char('city_correspondence', 9)->nullable()->index();
            $table->unsignedInteger('city_id')->nullable()->index();
            $table->boolean('archive')->nullable()->index();
            $table->string('remarks')->nullable();

            $table->foreign('city_id')->references('city_id')->on('psgc_city_4q25')->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('psgc_barangays');
        Schema::dropIfExists('psgc_city_4q25');
        Schema::dropIfExists('psgc_provinces');
        Schema::dropIfExists('psgc_regions');
    }
};
