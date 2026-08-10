<?php

declare(strict_types=1);

namespace Tests\Feature\Protocol;

use App\Modules\Acceptance\Application\Actions\SignProtocolAction;
use App\Modules\Billing\Domain\Enums\AllowedAction;
use App\Modules\Billing\Domain\Enums\ProductCode;
use App\Modules\Billing\Infrastructure\Models\Entitlement;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Participation\Domain\Enums\ParticipantRole;
use App\Modules\Participation\Infrastructure\Models\Participant;
use App\Modules\Property\Domain\Enums\DeclarationType;
use App\Modules\Property\Infrastructure\Models\Property;
use App\Modules\Protocol\Application\Actions\FinalizeCheckInAction;
use App\Modules\Protocol\Application\Actions\IssueCheckOutAction;
use App\Modules\Protocol\Domain\Enums\LegalMode;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Domain\Enums\ReferenceMode;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M13 step 0 regression: legal_mode is now determined INSIDE the domain
 * actions (FinalizeCheckInAction, IssueCheckOutAction), not by
 * ProtocolFinalizeController wiring. Every test here calls the action
 * directly — no HTTP layer, no controller in the call chain — to prove the
 * domain is the single source of truth.
 */
class LegalModeDomainActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private User $tenant;

    private Property $property;

    private SignProtocolAction $signAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([LocaleSeeder::class, CatalogSeeder::class]);

        $this->landlord = User::create([
            'name' => 'Jan Wynajmujący',
            'email' => 'landlord@example.com',
            'password' => 'password123',
        ]);

        $this->tenant = User::create([
            'name' => 'Anna Najemca',
            'email' => 'tenant@example.com',
            'password' => 'password123',
        ]);

        $this->property = Property::create([
            'user_id' => $this->landlord->id,
            'name' => 'Mieszkanie przy ul. Testowej',
            'street' => 'ul. Testowa',
            'building_number' => '15',
            'city' => 'Warszawa',
            'postal_code' => '00-001',
            'country' => 'PL',
            'declaration_type' => DeclarationType::OWNER,
        ]);

        $this->signAction = app(SignProtocolAction::class);
    }

    private function grantEntitlement(User $user, ProductCode $product, AllowedAction $action): void
    {
        Entitlement::create([
            'user_id' => $user->id,
            'product_code' => $product,
            'allowed_action' => $action,
            'quantity_total' => 1,
            'quantity_used' => 0,
            'valid_until' => now()->addMonth(),
            'activated_at' => now(),
        ]);
    }

    /**
     * (a) Direct FinalizeCheckInAction call, both parties signed -> bilateral.
     * No ProtocolFinalizeController anywhere in the chain.
     */
    public function test_finalize_check_in_action_sets_legal_mode_bilateral_without_controller(): void
    {
        $this->grantEntitlement($this->landlord, ProductCode::WJAZD, AllowedAction::CREATE_CHECK_IN);

        $protocol = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_IN,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'status' => ProtocolStatus::DRAFT,
            'title' => 'legal_mode bilateral regression',
        ]);

        $landlordParticipant = Participant::create([
            'protocol_id' => $protocol->id,
            'user_id' => $this->landlord->id,
            'role' => ParticipantRole::LANDLORD,
            'is_initiator' => true,
            'invited_at' => now(),
            'accepted_at' => now(),
        ]);

        $tenantParticipant = Participant::create([
            'protocol_id' => $protocol->id,
            'user_id' => $this->tenant->id,
            'role' => ParticipantRole::TENANT,
            'is_initiator' => false,
            'invited_at' => now(),
            'accepted_at' => now(),
        ]);

        $protocol->transitionTo(ProtocolStatus::PENDING_COUNTERPARTY);
        $protocol->transitionTo(ProtocolStatus::PENDING_SIGNATURES);

        $protocol = $this->signAction->execute($protocol, $landlordParticipant, 'landlord-sig', '10.0.0.1', 'UA', 'fp-landlord');
        $protocol = $this->signAction->execute($protocol, $tenantParticipant->fresh(), 'tenant-sig', '10.0.0.2', 'UA', 'fp-tenant');

        $protocol = app(FinalizeCheckInAction::class)->execute($protocol, false);

        $this->assertEquals(LegalMode::BILATERAL_COMPLETED, $protocol->legal_mode);
        $this->assertTrue($protocol->legal_mode->requiresBothSignatures());
    }

    /**
     * (a) Direct FinalizeCheckInAction call, only initiator (tenant) signed,
     * allowUnilateral=true -> unilateral tenant. No controller involved.
     */
    public function test_finalize_check_in_action_sets_legal_mode_unilateral_without_controller(): void
    {
        $this->grantEntitlement($this->tenant, ProductCode::WJAZD, AllowedAction::CREATE_CHECK_IN);

        $protocol = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->tenant->id,
            'type' => ProtocolType::CHECK_IN,
            'initiator_role' => ParticipantRole::TENANT,
            'counterparty_role' => ParticipantRole::LANDLORD,
            'status' => ProtocolStatus::DRAFT,
            'title' => 'legal_mode unilateral regression',
        ]);

        $tenantParticipant = Participant::create([
            'protocol_id' => $protocol->id,
            'user_id' => $this->tenant->id,
            'role' => ParticipantRole::TENANT,
            'is_initiator' => true,
            'invited_at' => now(),
            'accepted_at' => now(),
        ]);

        Participant::create([
            'protocol_id' => $protocol->id,
            'user_id' => $this->landlord->id,
            'role' => ParticipantRole::LANDLORD,
            'is_initiator' => false,
            'invited_at' => now(),
            // never accepted/signed
        ]);

        $protocol->transitionTo(ProtocolStatus::PENDING_COUNTERPARTY);
        $protocol->transitionTo(ProtocolStatus::PENDING_SIGNATURES);

        $protocol = $this->signAction->execute($protocol, $tenantParticipant, 'tenant-sig', '10.0.0.1', 'UA', 'fp-tenant');

        $protocol = app(FinalizeCheckInAction::class)->execute($protocol, true);

        $this->assertEquals(LegalMode::UNILATERAL_TENANT, $protocol->legal_mode);
        $this->assertFalse($protocol->legal_mode->requiresBothSignatures());
    }

    /**
     * (b) Direct IssueCheckOutAction call -> legal_mode is always unilateral
     * (Guard 1/11 — one acceptance issues the act), labelled by initiator role.
     */
    public function test_issue_check_out_action_sets_legal_mode_unilateral(): void
    {
        $this->grantEntitlement($this->landlord, ProductCode::WJAZD, AllowedAction::CREATE_CHECK_IN);
        $this->grantEntitlement($this->landlord, ProductCode::WYJAZD, AllowedAction::CREATE_CHECK_OUT);

        $baselineCheckIn = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_IN,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'status' => ProtocolStatus::COMPLETED,
            'title' => 'Baseline check-in',
            'completed_at' => now()->subMonths(6),
        ]);

        $checkOut = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_OUT,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'status' => ProtocolStatus::DRAFT,
            'title' => 'legal_mode checkout regression',
            'reference_mode' => ReferenceMode::SYSTEM_BASELINE,
            'linked_checkin_id' => $baselineCheckIn->id,
            'deposit_amount' => 1000.00,
        ]);

        $landlordParticipant = Participant::create([
            'protocol_id' => $checkOut->id,
            'user_id' => $this->landlord->id,
            'role' => ParticipantRole::LANDLORD,
            'is_initiator' => true,
            'invited_at' => now(),
            'accepted_at' => now(),
        ]);

        $checkOut->transitionTo(ProtocolStatus::PENDING_COUNTERPARTY);
        $checkOut->transitionTo(ProtocolStatus::PENDING_SIGNATURES);

        $checkOut = $this->signAction->execute($checkOut, $landlordParticipant, 'landlord-checkout-sig', '10.0.0.1', 'UA', 'fp-landlord');

        $checkOut = app(IssueCheckOutAction::class)->execute($checkOut, 72, '10.0.0.1', 'UA');

        $this->assertEquals(LegalMode::UNILATERAL_LANDLORD, $checkOut->legal_mode);
        $this->assertFalse($checkOut->legal_mode->requiresBothSignatures());
    }
}
