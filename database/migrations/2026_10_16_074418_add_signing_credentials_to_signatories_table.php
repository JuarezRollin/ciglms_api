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
        Schema::table('signatories', function (Blueprint $table): void {
            $table->string('p12_path')->nullable();
            $table->string('signature_image_path')->nullable();
            $table->text('pnpki_password')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('signatories', function (Blueprint $table): void {
            $table->dropColumn(['p12_path', 'signature_image_path', 'pnpki_password']);
        });
    }
};
