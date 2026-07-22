<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('protocol_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('role'); // landlord, tenant
            $table->boolean('is_initiator')->default(false);

            $table->string('invited_email')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['protocol_id', 'role']);
            $table->index(['protocol_id', 'is_initiator']);
            $table->index(['user_id', 'role']);
            $table->index('invited_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participants');
    }
};
