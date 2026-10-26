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
        Schema::table('signatories', function(Blueprint $table){
            $table->string('position', '255')->nullable()->after('designation');
            $table->string('office', '255')->nullable()->after('position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('signatories', function(Blueprint $table){
            $table->dropColumn('position');
            $table->dropColumn('office');
        });
    }
};
