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
        Schema::table('categories', function(Blueprint $table){
            $table->string('first_reviewer')->nullable()->after('description');
            $table->string('second_reviewer')->nullable()->after('first_reviewer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function(Blueprint $table){
            $table->dropColumn('first_reviewer');
            $table->dropColumn('second_reviewer');
        });
    }
};
