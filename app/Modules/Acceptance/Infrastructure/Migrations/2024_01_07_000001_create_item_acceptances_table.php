<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('protocol_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('protocol_item_id')->constrained('protocol_items')->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();

            $table->string('status')->default('pending'); // pending, accepted, disputed
            $table->text('dispute_reason')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();

            $table->timestamps();

            // Each participant can only have one acceptance record per item
            $table->unique(['protocol_item_id', 'participant_id']);
            $table->index(['protocol_id', 'status']);
            $table->index(['participant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_acceptances');
    }
};
