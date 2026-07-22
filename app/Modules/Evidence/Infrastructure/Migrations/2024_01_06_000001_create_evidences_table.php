<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('protocol_id')->constrained()->cascadeOnDelete();

            // Polymorphic relation (can be attached to ProtocolItem, ProtocolRoom, etc.)
            $table->nullableUuidMorphs('evidenceable');

            $table->foreignId('uploaded_by_user_id')->constrained('users')->cascadeOnDelete();

            $table->string('type'); // photo, document, video
            $table->string('filename');
            $table->string('original_filename');
            $table->string('path');
            $table->string('disk')->default('minio');
            $table->string('mime_type');
            $table->unsignedBigInteger('size'); // bytes

            $table->string('caption')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->jsonb('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['protocol_id', 'type']);
            // evidenceable index created automatically by nullableUuidMorphs
            $table->index('uploaded_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidences');
    }
};
