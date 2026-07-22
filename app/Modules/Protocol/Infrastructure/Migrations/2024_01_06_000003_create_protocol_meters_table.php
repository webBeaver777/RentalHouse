<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1.2: Meters table for tracking utility readings.
 *
 * Tracks: electricity, cold water, hot water, gas, heating.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('protocol_meters', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('protocol_id')->constrained()->cascadeOnDelete();

            $table->string('type'); // MeterType enum
            $table->string('meter_number')->nullable();
            $table->decimal('reading', 12, 3);
            $table->string('unit')->nullable();

            // Optional: photo of meter
            $table->foreignUuid('evidence_id')->nullable()
                ->constrained('evidences')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->jsonb('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['protocol_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protocol_meters');
    }
};
