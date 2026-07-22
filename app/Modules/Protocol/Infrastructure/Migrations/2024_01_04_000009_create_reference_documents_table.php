<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1.3: Reference documents table for uploaded_reference mode.
 *
 * When check-out uses uploaded_reference mode, external documents
 * (previous protocol PDFs, photos) are stored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('protocol_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            // File info
            $table->string('filename');
            $table->string('original_filename');
            $table->string('path');
            $table->string('disk')->default('minio');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('hash', 64)->nullable();

            // When document was created (from document metadata if available)
            $table->date('document_date')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->jsonb('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('protocol_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_documents');
    }
};
