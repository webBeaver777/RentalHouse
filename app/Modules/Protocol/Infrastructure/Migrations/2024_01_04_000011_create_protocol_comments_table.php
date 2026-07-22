<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D6: Protocol comments table.
 *
 * Allows counterparty to comment on protocol/room/item/defect/deduction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('protocol_comments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('protocol_id')->constrained()->cascadeOnDelete();

            // Author info
            $table->string('author_role', 20); // landlord, tenant
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Comment body
            $table->text('body');

            // What this comment is attached to (polymorphic-like)
            $table->string('commentable_type', 50)->nullable(); // protocol, room, item, defect, deduction
            $table->uuid('commentable_id')->nullable();

            // Forensic context
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('device_fingerprint', 64)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['protocol_id', 'created_at']);
            $table->index('author_role');
            $table->index(['commentable_type', 'commentable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protocol_comments');
    }
};
