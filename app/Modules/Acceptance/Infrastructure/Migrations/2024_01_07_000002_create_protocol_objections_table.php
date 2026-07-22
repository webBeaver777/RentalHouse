<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2.1: Protocol objections for check-out objection window.
 *
 * Counterparty can raise objections within 72h after check-out signing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('protocol_objections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('protocol_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();

            $table->text('reason');
            $table->jsonb('item_ids')->nullable();

            $table->timestamp('raised_at');
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->string('resolution_outcome')->nullable();

            $table->timestamps();

            $table->index('protocol_id');
            $table->index(['protocol_id', 'participant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protocol_objections');
    }
};
