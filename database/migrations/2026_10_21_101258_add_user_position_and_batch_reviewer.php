<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('position')->nullable()->after('name');
        });

        $positions = ['Administrative Assistant I', 'Administrative Assistant II', 'Administrative Assistant III', 'Administrative Officer I', 'Administrative Officer II', 'Administrative Officer III'];
        DB::table('users')->select('id')->orderBy('id')->chunkById(500, function ($users) use ($positions): void {
            foreach ($users as $user) {
                DB::table('users')->where('id', $user->id)->update(['position' => $positions[$user->id % count($positions)]]);
            }
        });

        Schema::table('batches', function (Blueprint $table): void {
            $table->foreignId('reviewer_id')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewer_id');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('position');
        });
    }
};
