<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1.1: Add forensic fields to evidences table.
 *
 * These fields ensure evidence integrity and provide audit trail:
 * - hash: SHA-256 hash of file content (integrity verification)
 * - captured_at: when the photo/video was actually taken (from EXIF or device)
 * - device_info: device model/OS info for audit
 * - server_received_at: when server received the upload
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evidences', function (Blueprint $table): void {
            // SHA-256 hash for integrity verification
            $table->string('hash', 64)->nullable()->after('size');

            // When evidence was captured (from EXIF or device)
            $table->timestamp('captured_at')->nullable()->after('hash');

            // Device information (model, OS, etc.)
            $table->string('device_info')->nullable()->after('captured_at');

            // When server received the upload
            $table->timestamp('server_received_at')->nullable()->after('device_info');

            // For audit: client IP address
            $table->string('uploaded_from_ip', 45)->nullable()->after('server_received_at');

            // Indexes for forensic queries
            $table->index('hash');
            $table->index('captured_at');
        });
    }

    public function down(): void
    {
        Schema::table('evidences', function (Blueprint $table): void {
            $table->dropIndex(['hash']);
            $table->dropIndex(['captured_at']);

            $table->dropColumn([
                'hash',
                'captured_at',
                'device_info',
                'server_received_at',
                'uploaded_from_ip',
            ]);
        });
    }
};
