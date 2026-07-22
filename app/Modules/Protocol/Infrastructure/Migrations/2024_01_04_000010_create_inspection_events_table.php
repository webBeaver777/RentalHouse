<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D7: Persistent timeline table for inspection events.
 *
 * Доменные события в шине ≠ доказательный таймлайн.
 * Персистентная таблица для юридической доказательной базы.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('protocol_id')->constrained()->cascadeOnDelete();

            // Who performed the action
            $table->string('actor_role', 20); // landlord, tenant, system
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Event type (§15 full set)
            $table->string('event_type', 50);

            // Event payload
            $table->jsonb('payload')->nullable();

            // When it happened
            $table->timestamp('occurred_at');

            // Forensic context
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('device_fingerprint', 64)->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['protocol_id', 'occurred_at']);
            $table->index('event_type');
            $table->index('actor_role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_events');
    }
};
