<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1.0: Add asymmetric protocol fields.
 *
 * Key fields for asymmetric state machines:
 * - initiator_role / counterparty_role: who started and who is the other party
 * - legal_mode: determines PDF template and legal text
 * - act_issued_at: when the act was officially issued (check-out specific)
 * - objection_window_ends_at: end of objection period (check-out specific)
 * - reference_mode: how baseline is handled for check-out
 * - linked_checkin_id: links check-out to its baseline check-in
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('protocols', function (Blueprint $table): void {
            // Roles: who initiated and who is counterparty
            $table->string('initiator_role')->nullable()->after('type');
            $table->string('counterparty_role')->nullable()->after('initiator_role');

            // Legal mode determines PDF template and consent texts
            $table->string('legal_mode')->default('standard')->after('counterparty_role');

            // Asymmetric finalization fields
            $table->timestamp('act_issued_at')->nullable()->after('completed_at');
            $table->timestamp('objection_window_ends_at')->nullable()->after('act_issued_at');

            // Reference mode for check-out (system_baseline, uploaded_reference, no_reference)
            $table->string('reference_mode')->nullable()->after('objection_window_ends_at');

            // Link check-out to its baseline check-in
            $table->uuid('linked_checkin_id')->nullable()->after('reference_mode');

            // Protocol locale (for PDF generation and notifications)
            $table->string('locale', 5)->default('pl')->after('linked_checkin_id');

            // Snapshot of property declaration_type at protocol creation
            $table->string('property_declaration_type')->nullable()->after('locale');

            // Deposit tracking
            $table->decimal('deposit_amount', 10, 2)->nullable()->after('property_declaration_type');

            // Indexes
            $table->index('initiator_role');
            $table->index('legal_mode');
            $table->index('reference_mode');
            $table->index('linked_checkin_id');

            // Foreign key for linked check-in (self-referential)
            $table->foreign('linked_checkin_id')
                ->references('id')
                ->on('protocols')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('protocols', function (Blueprint $table): void {
            $table->dropForeign(['linked_checkin_id']);
            $table->dropIndex(['initiator_role']);
            $table->dropIndex(['legal_mode']);
            $table->dropIndex(['reference_mode']);
            $table->dropIndex(['linked_checkin_id']);

            $table->dropColumn([
                'initiator_role',
                'counterparty_role',
                'legal_mode',
                'act_issued_at',
                'objection_window_ends_at',
                'reference_mode',
                'linked_checkin_id',
                'locale',
                'property_declaration_type',
                'deposit_amount',
            ]);
        });
    }
};
