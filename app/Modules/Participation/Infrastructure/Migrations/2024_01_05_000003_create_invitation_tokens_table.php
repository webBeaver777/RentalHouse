<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2.0: Invitation tokens for secure protocol participation links.
 *
 * Tokens are single-use and expire after a configurable period.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitation_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();

            $table->string('token', 64)->unique();
            $table->string('email');

            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->string('used_ip')->nullable();
            $table->string('used_user_agent')->nullable();

            $table->timestamps();

            $table->index('token');
            $table->index('email');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitation_tokens');
    }
};
