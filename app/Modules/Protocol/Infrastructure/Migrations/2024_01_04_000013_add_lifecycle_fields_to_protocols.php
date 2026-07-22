<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D8: Lifecycle fields for protocols.
 *
 * Canon: access_expires_at = paid_at + 12 months
 *        retention_until = access_expires_at + 3 years
 *        Then soft-delete with audit trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('protocols', function (Blueprint $table): void {
            // When user's access to this protocol expires
            // Default: paid_at + 12 months (configured)
            $table->timestamp('access_expires_at')->nullable()->after('deleted_at');

            // When data can be purged (RODO retention)
            // Default: access_expires_at + 3 years (configured)
            $table->timestamp('retention_until')->nullable()->after('access_expires_at');

            // When the protocol was paid for (triggers access period)
            $table->timestamp('paid_at')->nullable()->after('retention_until');

            $table->index('access_expires_at');
            $table->index('retention_until');
        });
    }

    public function down(): void
    {
        Schema::table('protocols', function (Blueprint $table): void {
            $table->dropIndex(['access_expires_at']);
            $table->dropIndex(['retention_until']);
            $table->dropColumn(['access_expires_at', 'retention_until', 'paid_at']);
        });
    }
};
