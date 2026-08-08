<?php

declare(strict_types=1);

namespace Tests\Feature\Protocol;

use App\Modules\Billing\Domain\Enums\AllowedAction;
use App\Modules\Billing\Domain\Enums\ProductCode;
use App\Modules\Billing\Infrastructure\Models\Entitlement;
use App\Modules\Document\Domain\Enums\PdfTemplateType;
use App\Modules\Document\Infrastructure\Models\GeneratedDocument;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Participation\Domain\Enums\ParticipantRole;
use App\Modules\Property\Domain\Enums\DeclarationType;
use App\Modules\Property\Infrastructure\Models\Property;
use App\Modules\Protocol\Domain\Enums\LegalMode;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * M11, Scenario B: tenant-initiated check-in, exercised through the real
 * HTTP controllers (unlike the domain-level ScenarioBTest, which calls
 * InvitationService/FinalizeCheckInAction directly and never goes through
 * ProtocolSubmitController — meaning it never proved the role-flip that
 * controller performs from property.declaration_type actually reaches the
 * HTTP layer, or that legal_mode/template selection follow it correctly).
 */
class ScenarioBHttpTest extends TestCase
{
    use RefreshDatabase;

    private User $tenant;

    private User $landlord;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->tenant = User::create([
            'name' => 'Anna Najemca',
            'email' => 'tenant@example.com',
            'password' => 'password123',
        ]);

        $this->landlord = User::create([
            'name' => 'Jan Wynajmujący',
            'email' => 'landlord@example.com',
            'password' => 'password123',
        ]);
    }

    /**
     * Creates a tenant_declared property + check-in protocol + entitlement,
     * submits to the landlord via the real HTTP endpoints, and returns the
     * fresh protocol plus the raw guest magic-link token.
     *
     * @return array{0: Protocol, 1: string}
     */
    private function createAndSubmitTenantInitiatedProtocol(): array
    {
        $this->actingAs($this->tenant)->post(route('properties.store'), [
            'name' => 'Wynajmowane mieszkanie',
            'street' => 'ul. Najemcy',
            'building_number' => '20',
            'city' => 'Kraków',
            'postal_code' => '30-001',
            'declaration_type' => DeclarationType::TENANT_DECLARED->value,
        ]);

        $property = Property::where('user_id', $this->tenant->id)->firstOrFail();

        $this->actingAs($this->tenant)->post(route('protocols.store'), [
            'property_id' => $property->id,
        ]);

        $protocol = Protocol::where('property_id', $property->id)->firstOrFail();

        Entitlement::create([
            'user_id' => $this->tenant->id,
            'product_code' => ProductCode::WJAZD,
            'allowed_action' => AllowedAction::CREATE_CHECK_IN,
            'quantity_total' => 1,
            'quantity_used' => 0,
            'valid_until' => now()->addMonth(),
            'activated_at' => now(),
        ]);

        $this->actingAs($this->tenant)->post(route('protocols.submit', $protocol), [
            'counterparty_email' => $this->landlord->email,
        ]);

        $protocol->refresh();
        $link = $protocol->metadata['dev_magic_link'] ?? null;
        $this->assertNotNull($link, 'dev_magic_link should be present outside production');
        $token = Str::afterLast($link, '/');

        return [$protocol, $token];
    }

    /* --- role-flip, proven through the real HTTP submit path --- */

    public function test_role_flip_is_persisted_via_real_submit_endpoint(): void
    {
        [$protocol] = $this->createAndSubmitTenantInitiatedProtocol();

        $this->assertEquals(ParticipantRole::TENANT, $protocol->initiator_role);
        $this->assertEquals(ParticipantRole::LANDLORD, $protocol->counterparty_role);

        $tenantParticipant = $protocol->participants()->where('user_id', $this->tenant->id)->firstOrFail();
        $this->assertTrue($tenantParticipant->is_initiator);
        $this->assertEquals(ParticipantRole::TENANT, $tenantParticipant->role);

        $landlordParticipant = $protocol->participants()->where('invited_email', $this->landlord->email)->firstOrFail();
        $this->assertFalse($landlordParticipant->is_initiator);
        $this->assertEquals(ParticipantRole::LANDLORD, $landlordParticipant->role);
    }

    /* --- bilateral branch: landlord signs -> real bilateral baseline --- */

    public function test_bilateral_branch_produces_bilateral_completed_document(): void
    {
        [$protocol, $token] = $this->createAndSubmitTenantInitiatedProtocol();

        $this->post(route('invitation.sign', $token), ['consent' => true]);
        $this->actingAs($this->tenant)->post(route('protocols.sign', $protocol));

        $protocol->refresh();
        $this->assertEquals(ProtocolStatus::SIGNED, $protocol->status);

        $response = $this->actingAs($this->tenant)->post(route('protocols.finalize', $protocol));
        $response->assertRedirect(route('protocols.show', $protocol));

        $protocol->refresh();
        $this->assertEquals(ProtocolStatus::COMPLETED, $protocol->status);
        $this->assertEquals(LegalMode::BILATERAL_COMPLETED, $protocol->legal_mode);

        $document = GeneratedDocument::where('protocol_id', $protocol->id)->firstOrFail();
        $this->assertEquals(PdfTemplateType::BILATERAL_CHECKIN->value, $document->template_type);
    }

    /* --- unilateral fallback: landlord never signs -> notice document --- */

    public function test_unilateral_fallback_produces_notice_document(): void
    {
        [$protocol] = $this->createAndSubmitTenantInitiatedProtocol();

        // Landlord never opens the link. Tenant (initiator) signs their own side.
        $this->actingAs($this->tenant)->post(route('protocols.sign', $protocol));

        $protocol->refresh();
        $this->assertEquals(ProtocolStatus::PENDING_SIGNATURES, $protocol->status);

        $response = $this->actingAs($this->tenant)->post(route('protocols.finalize', $protocol), [
            'unilateral' => true,
        ]);
        $response->assertRedirect(route('protocols.show', $protocol));

        $protocol->refresh();
        $this->assertEquals(ProtocolStatus::COMPLETED, $protocol->status);
        $this->assertEquals(LegalMode::UNILATERAL_TENANT, $protocol->legal_mode);
        $this->assertFalse($protocol->legal_mode->requiresBothSignatures());

        $document = GeneratedDocument::where('protocol_id', $protocol->id)->firstOrFail();
        $this->assertEquals(PdfTemplateType::UNILATERAL_TENANT_CHECKIN->value, $document->template_type);

        // Only the tenant's signature exists — genuinely unilateral.
        $this->assertDatabaseCount('acceptances', 1);
    }

    /* --- guard 2: bilateral finalize still requires the real signature --- */

    public function test_bilateral_finalize_rejected_while_landlord_unsigned(): void
    {
        [$protocol] = $this->createAndSubmitTenantInitiatedProtocol();

        $this->actingAs($this->tenant)->post(route('protocols.sign', $protocol));

        $response = $this->actingAs($this->tenant)->post(route('protocols.finalize', $protocol));

        $response->assertSessionHasErrors(['finalize']);
        $protocol->refresh();
        $this->assertNotEquals(ProtocolStatus::COMPLETED, $protocol->status);
    }

    /* --- guard 2: "unilateral" flag is ignored once truly bilateral --- */

    public function test_unilateral_flag_is_ignored_once_counterparty_has_signed(): void
    {
        [$protocol, $token] = $this->createAndSubmitTenantInitiatedProtocol();

        $this->post(route('invitation.sign', $token), ['consent' => true]);
        $this->actingAs($this->tenant)->post(route('protocols.sign', $protocol));

        $protocol->refresh();
        $this->assertEquals(ProtocolStatus::SIGNED, $protocol->status);

        // Misuse: post unilateral=true even though the landlord already signed.
        $this->actingAs($this->tenant)->post(route('protocols.finalize', $protocol), [
            'unilateral' => true,
        ]);

        $protocol->refresh();
        $this->assertEquals(LegalMode::BILATERAL_COMPLETED, $protocol->legal_mode);

        $document = GeneratedDocument::where('protocol_id', $protocol->id)->firstOrFail();
        $this->assertEquals(PdfTemplateType::BILATERAL_CHECKIN->value, $document->template_type);
        $this->assertDatabaseCount('acceptances', 2);
    }

    /* --- guard 2: unilateral finalize is scoped to tenant-initiated protocols --- */

    public function test_unilateral_finalize_rejected_for_landlord_initiated_protocol(): void
    {
        // Scenario A shape: owner property, landlord initiator.
        $property = Property::create([
            'user_id' => $this->landlord->id,
            'name' => 'Mieszkanie właściciela',
            'street' => 'ul. Właściciela',
            'building_number' => '1',
            'city' => 'Warszawa',
            'postal_code' => '00-001',
            'declaration_type' => DeclarationType::OWNER,
        ]);

        $protocol = Protocol::create([
            'property_id' => $property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_IN,
            'title' => 'Protokół wjazdu',
        ]);

        Entitlement::create([
            'user_id' => $this->landlord->id,
            'product_code' => ProductCode::WJAZD,
            'allowed_action' => AllowedAction::CREATE_CHECK_IN,
            'quantity_total' => 1,
            'quantity_used' => 0,
            'valid_until' => now()->addMonth(),
            'activated_at' => now(),
        ]);

        $this->actingAs($this->landlord)->post(route('protocols.submit', $protocol), [
            'counterparty_email' => $this->tenant->email,
        ]);

        $this->actingAs($this->landlord)->post(route('protocols.sign', $protocol));

        $protocol->refresh();
        $this->assertEquals(ParticipantRole::LANDLORD, $protocol->initiator_role);
        $this->assertEquals(ProtocolStatus::PENDING_SIGNATURES, $protocol->status);

        $response = $this->actingAs($this->landlord)->post(route('protocols.finalize', $protocol), [
            'unilateral' => true,
        ]);

        $response->assertStatus(422);
        $protocol->refresh();
        $this->assertNotEquals(ProtocolStatus::COMPLETED, $protocol->status);
    }
}
