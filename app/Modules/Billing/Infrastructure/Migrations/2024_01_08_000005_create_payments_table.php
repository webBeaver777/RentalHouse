<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D1: Payments table for Przelewy24 integration.
 *
 * Stores transaction records and status for payment processing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Przelewy24 transaction identifiers
            $table->string('p24_session_id', 100)->unique();
            $table->string('p24_order_id', 100)->nullable();
            $table->string('p24_transaction_id', 100)->nullable();

            // Payment details
            $table->string('product_code', 50);
            $table->unsignedInteger('amount'); // in grosze (1/100 PLN)
            $table->string('currency', 3)->default('PLN');
            $table->string('description');

            // Status: pending, started, paid, failed, cancelled, refunded
            $table->string('status', 20)->default('pending');

            // Timestamps for timeline events
            $table->timestamp('started_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason')->nullable();

            // Verification data
            $table->string('verification_sign')->nullable();
            $table->boolean('is_verified')->default(false);

            // Buyer info (for P24)
            $table->string('buyer_email');
            $table->string('buyer_name')->nullable();

            // Sandbox mode flag
            $table->boolean('is_sandbox')->default(false);

            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('status');
            $table->index('p24_order_id');
            $table->index('p24_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
