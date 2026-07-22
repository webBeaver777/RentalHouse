<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1.2: Keys table for tracking keys and access devices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('protocol_keys', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('protocol_id')->constrained()->cascadeOnDelete();

            $table->string('name'); // e.g., "Klucz do drzwi wejściowych"
            $table->unsignedInteger('quantity')->default(1);
            $table->string('description')->nullable();

            // Optional: photo of keys
            $table->foreignUuid('evidence_id')->nullable()
                ->constrained('evidences')->nullOnDelete();

            $table->unsignedInteger('sort_order')->default(0);
            $table->jsonb('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('protocol_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protocol_keys');
    }
};
