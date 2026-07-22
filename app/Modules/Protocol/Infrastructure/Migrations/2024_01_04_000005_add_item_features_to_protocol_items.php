<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1.1 & P1.3: Add item features for photos and baseline linking.
 *
 * is_valuable: requires triple-shot (3 photos from different angles)
 * requires_serial_plate: requires photo of serial number plate
 * baseline_item_id: links check-out item to its baseline check-in item
 * name_snapshot: frozen name at time of protocol creation
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('protocol_items', function (Blueprint $table): void {
            // Item features
            $table->boolean('is_valuable')->default(false)->after('quantity');
            $table->boolean('requires_serial_plate')->default(false)->after('is_valuable');

            // Condition state enum (new/good/used/damaged/not_working)
            $table->string('condition_state')->nullable()->after('condition_notes');

            // Baseline linking for check-out
            $table->uuid('baseline_item_id')->nullable()->after('condition_state');

            // Frozen name snapshot (from catalog at creation time)
            $table->jsonb('name_snapshot')->nullable()->after('baseline_item_id');

            // Indexes
            $table->index('baseline_item_id');
            $table->index('is_valuable');

            // Foreign key
            $table->foreign('baseline_item_id')
                ->references('id')
                ->on('protocol_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('protocol_items', function (Blueprint $table): void {
            $table->dropForeign(['baseline_item_id']);
            $table->dropIndex(['baseline_item_id']);
            $table->dropIndex(['is_valuable']);

            $table->dropColumn([
                'is_valuable',
                'requires_serial_plate',
                'condition_state',
                'baseline_item_id',
                'name_snapshot',
            ]);
        });
    }
};
