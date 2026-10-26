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
        Schema::create('guarantee_letter_supporting_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guarantee_letter_id')->constrained(indexName: 'gl_docs_letter_fk')->restrictOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users', indexName: 'gl_docs_uploader_fk')->nullOnDelete();
            $table->string('disk');
            $table->string('path')->unique();
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guarantee_letter_supporting_documents');
    }
};
