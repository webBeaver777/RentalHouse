<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1.2: Defects table for tracking damage/issues.
 *
 * Features:
 * - marker_x/y (0-100) for photo annotation
 * - severity level
 * - estimated cost for deposit calculation
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('protocol_defects', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('protocol_id')->constrained()->cascadeOnDelete();

            // Link to item or room
            $table->foreignUuid('protocol_item_id')->nullable()
                ->constrained()->cascadeOnDelete();
            $table->foreignUuid('protocol_room_id')->nullable()
                ->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            // Severity for prioritization
            $table->string('severity'); // DefectSeverity enum

            // Photo marker position (0-100 scale for responsive positioning)
            $table->decimal('marker_x', 5, 2)->nullable();
            $table->decimal('marker_y', 5, 2)->nullable();

            // Cost tracking for deposit calculation
            $table->decimal('estimated_cost', 10, 2)->nullable();
            $table->boolean('is_cost_manual')->default(false);

            // Reference to main photo showing the defect
            $table->foreignUuid('evidence_id')->nullable()
                ->constrained('evidences')->nullOnDelete();

            // Defect type from catalog
            $table->foreignId('defect_catalog_item_id')->nullable()
                ->constrained('catalog_items')->nullOnDelete();

            $table->unsignedInteger('sort_order')->default(0);
            $table->jsonb('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['protocol_id', 'severity']);
            $table->index('protocol_item_id');
            $table->index('protocol_room_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protocol_defects');
    }
};
