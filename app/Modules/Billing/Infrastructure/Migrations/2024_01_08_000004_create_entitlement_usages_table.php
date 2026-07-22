<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D2: Entitlement usages - tracks consumption of entitlements.
 *
 * Links entitlement_id ↔ protocol_id (inspection_id per ТЗ).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entitlement_usages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('entitlement_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('protocol_id')->constrained()->cascadeOnDelete();

            // When the entitlement was consumed
            $table->timestamp('consumed_at');

            // What action was performed
            $table->string('action', 50);

            // Forensic context
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('entitlement_id');
            $table->index('protocol_id');
            $table->unique(['entitlement_id', 'protocol_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entitlement_usages');
    }
};
