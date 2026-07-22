<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('protocol_rooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('protocol_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_item_id')->constrained('catalog_items')->cascadeOnDelete();

            $table->string('custom_name')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->jsonb('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['protocol_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protocol_rooms');
    }
};
