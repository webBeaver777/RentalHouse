<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G1: Extended fields for counterparty_participations (§11).
 *
 * Full spectrum of statuses and forensic tracking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table): void {
            // Extended status tracking (§11 full spectrum)
            // not_sent, sent, delivery_failed, opened, viewed_sections,
            // commented, photo_added, accepted, accepted_with_comments,
            // objected, declined_to_sign, expired_no_response
            $table->string('participation_status', 30)->default('not_sent')->after('declined_at');

            // Timestamp tracking
            $table->timestamp('sent_at')->nullable()->after('participation_status');
            $table->timestamp('opened_at')->nullable()->after('sent_at');
            $table->timestamp('expires_at')->nullable()->after('opened_at');
            $table->timestamp('last_activity_at')->nullable()->after('expires_at');

            // Forensic context
            $table->string('ip_address', 45)->nullable()->after('last_activity_at');
            $table->string('user_agent')->nullable()->after('ip_address');
            $table->string('device_fingerprint', 64)->nullable()->after('user_agent');

            // Indexes
            $table->index('participation_status');
            $table->index('sent_at');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table): void {
            $table->dropIndex(['participation_status']);
            $table->dropIndex(['sent_at']);
            $table->dropIndex(['expires_at']);

            $table->dropColumn([
                'participation_status',
                'sent_at',
                'opened_at',
                'expires_at',
                'last_activity_at',
                'ip_address',
                'user_agent',
                'device_fingerprint',
            ]);
        });
    }
};
