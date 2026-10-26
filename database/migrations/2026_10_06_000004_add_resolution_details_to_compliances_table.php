<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compliances', function (Blueprint $table): void {
            $table->uuid('compliance_group_id')->nullable()->index()->after('compliance_type_id');
            $table->text('resolution_remarks')->nullable()->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('compliances', function (Blueprint $table): void {
            $table->dropIndex(['compliance_group_id']);
            $table->dropColumn(['compliance_group_id', 'resolution_remarks']);
        });
    }
};
