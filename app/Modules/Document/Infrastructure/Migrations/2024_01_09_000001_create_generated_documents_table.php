<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P4.0: Generated documents table for storing PDF documents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('protocol_id')->constrained()->cascadeOnDelete();

            $table->string('type'); // protocol_pdf, summary_pdf, evidence_archive
            $table->string('template_type')->nullable(); // D4: PdfTemplateType
            $table->string('locale')->default('pl');
            $table->string('version')->default('1.0');

            $table->string('filename');
            $table->string('path');
            $table->string('disk')->default('minio');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('hash', 64)->nullable();

            $table->timestamp('generated_at');
            $table->foreignId('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index('protocol_id');
            $table->index(['protocol_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_documents');
    }
};
