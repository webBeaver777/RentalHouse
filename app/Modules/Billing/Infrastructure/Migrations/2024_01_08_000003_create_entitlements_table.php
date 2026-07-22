<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D2: Entitlements table for the entitlement-based billing model.
 *
 * Replaces subscription-based model per ТЗ §16.
 * Products in MVP: WJAZD, WYJAZD
 * Forward-compatible: FULL_CYCLE, TENANT_PROTECTION, LANDLORD_PRO, AGENCY
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entitlements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Product code: WJAZD, WYJAZD (MVP), forward-compatible for other products
            $table->string('product_code', 50);

            // Allowed action: create_check_in, create_check_out
            // Forward-compatible: monthly_protocol_quota, agency_protocol_quota
            $table->string('allowed_action', 50);

            // Quantity management
            $table->unsignedInteger('quantity_total')->default(1);
            $table->unsignedInteger('quantity_used')->default(0);

            // Validity period
            $table->timestamp('valid_until')->nullable();

            // Payment reference
            $table->uuid('source_payment_id')->nullable();

            // Activation tracking
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expired_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['user_id', 'allowed_action']);
            $table->index(['user_id', 'product_code']);
            $table->index('valid_until');
            $table->index('source_payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entitlements');
    }
};
