<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('protocol_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('protocol_room_id')->constrained('protocol_rooms')->cascadeOnDelete();
            $table->foreignId('catalog_item_id')->constrained('catalog_items')->cascadeOnDelete();
            $table->foreignId('condition_catalog_item_id')->nullable()->constrained('catalog_items')->nullOnDelete();

            $table->string('custom_name')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->text('condition_notes')->nullable();
            $table->jsonb('defects')->nullable(); // Array of defect catalog_item_ids with optional notes
            $table->unsignedInteger('sort_order')->default(0);
            $table->jsonb('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['protocol_room_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protocol_items');
    }
};
