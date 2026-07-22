<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D5/D10: Add document_hash and pdf_status to protocols.
 *
 * document_hash is frozen after first generation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('protocols', function (Blueprint $table): void {
            $table->string('document_hash', 64)->nullable()->after('metadata');
            $table->string('pdf_status', 20)->default('not_generated')->after('document_hash');
            $table->timestamp('document_generated_at')->nullable()->after('pdf_status');
        });
    }

    public function down(): void
    {
        Schema::table('protocols', function (Blueprint $table): void {
            $table->dropColumn(['document_hash', 'pdf_status', 'document_generated_at']);
        });
    }
};
