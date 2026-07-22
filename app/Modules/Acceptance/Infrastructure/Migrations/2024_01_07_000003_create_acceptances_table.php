<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G2: Acceptances table.
 *
 * One row = one acceptance action.
 * Cardinality varies by protocol type (check-in vs check-out).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acceptances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('protocol_id')->constrained()->cascadeOnDelete();

            // Who accepted
            $table->string('accepted_by_role', 20); // landlord, tenant
            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Link to participation (null for initiator)
            $table->foreignId('counterparty_participation_id')
                ->nullable()
                ->constrained('participants')
                ->nullOnDelete();

            // Type of acceptance
            // accepted, accepted_with_comments, acknowledgement_only
            $table->string('acceptance_type', 30);

            // Consent text snapshot at moment of acceptance
            $table->text('consent_text_snapshot')->nullable();

            // When accepted
            $table->timestamp('accepted_at');

            // Forensic context
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('device_fingerprint', 64)->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['protocol_id', 'accepted_at']);
            $table->index('accepted_by_role');
            $table->index('acceptance_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acceptances');
    }
};
