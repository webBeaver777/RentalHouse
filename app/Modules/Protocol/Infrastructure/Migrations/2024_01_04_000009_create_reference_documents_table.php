<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G4: Reference documents table for uploaded_reference mode.
 *
 * When check-out uses uploaded_reference mode, external documents
 * (previous protocol PDFs, photos, contracts) are stored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('protocol_id')->constrained()->cascadeOnDelete();

            // G4: Document type
            $table->string('type', 30); // pdf, photo, paper_protocol, lease_contract, other

            $table->string('title');
            $table->text('note')->nullable(); // G4: renamed from description

            // File info
            $table->string('filename');
            $table->string('original_filename');
            $table->string('path'); // file_path
            $table->string('disk')->default('minio');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('hash', 64); // G4: required, SHA-256

            // G4: When file was uploaded
            $table->timestamp('uploaded_at');
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // When document was created (from document metadata if available)
            $table->date('document_date')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->jsonb('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('protocol_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_documents');
    }
};
