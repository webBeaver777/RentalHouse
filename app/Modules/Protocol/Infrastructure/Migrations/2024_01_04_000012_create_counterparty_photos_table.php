<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D6: Counterparty photos table.
 *
 * Photos uploaded by the counterparty during review.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counterparty_photos', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('protocol_id')->constrained()->cascadeOnDelete();

            // Who uploaded
            $table->string('uploaded_by_role', 20); // landlord, tenant
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // What this photo is attached to
            $table->foreignUuid('room_id')->nullable()->constrained('protocol_rooms')->nullOnDelete();
            $table->foreignUuid('item_id')->nullable()->constrained('protocol_items')->nullOnDelete();

            // File info
            $table->string('path');
            $table->string('disk', 20)->default('minio');
            $table->string('filename');
            $table->string('mime_type', 50);
            $table->unsignedInteger('size');

            // Integrity hash
            $table->string('hash', 64); // SHA-256

            // Capture metadata
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('uploaded_at');

            // Device info for forensics
            $table->string('device_info')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['protocol_id', 'uploaded_at']);
            $table->index('uploaded_by_role');
            $table->index('hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counterparty_photos');
    }
};
