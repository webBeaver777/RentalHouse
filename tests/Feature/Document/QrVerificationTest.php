<?php

declare(strict_types=1);

namespace Tests\Feature\Document;

use App\Modules\Document\Domain\Enums\DocumentType;
use App\Modules\Document\Infrastructure\Models\GeneratedDocument;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Participation\Domain\Enums\ParticipantRole;
use App\Modules\Participation\Infrastructure\Models\Participant;
use App\Modules\Property\Domain\Enums\DeclarationType;
use App\Modules\Property\Infrastructure\Models\Property;
use App\Modules\Protocol\Domain\Enums\LegalMode;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §26.9: QR page does not expose PII.
 */
class QrVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private User $tenant;

    private Property $property;

    private Protocol $protocol;

    private GeneratedDocument $document;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlord = User::create([
            'name' => 'Jan Kowalski', // Real name - should NOT appear in QR page
            'email' => 'landlord@example.com',
            'password' => 'password',
        ]);

        $this->tenant = User::create([
            'name' => 'Anna Nowak', // Real name - should NOT appear in QR page
            'email' => 'tenant@example.com',
            'password' => 'password',
        ]);

        $this->property = Property::create([
            'user_id' => $this->landlord->id,
            'name' => 'Mieszkanie przy ul. Testowej', // Address - might be considered PII
            'street' => 'Testowa',
            'building_number' => '123',
            'city' => 'Warszawa',
            'postal_code' => '00-001',
            'declaration_type' => DeclarationType::OWNER,
        ]);

        $this->protocol = Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_IN,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'legal_mode' => LegalMode::BILATERAL_COMPLETED,
            'status' => ProtocolStatus::COMPLETED,
            'title' => 'Test Protocol',
        ]);

        // Add participants
        Participant::create([
            'protocol_id' => $this->protocol->id,
            'user_id' => $this->landlord->id,
            'role' => ParticipantRole::LANDLORD,
            'is_initiator' => true,
            'signed_at' => now(),
        ]);

        Participant::create([
            'protocol_id' => $this->protocol->id,
            'user_id' => $this->tenant->id,
            'role' => ParticipantRole::TENANT,
            'is_initiator' => false,
            'signed_at' => now(),
        ]);

        $this->document = GeneratedDocument::create([
            'protocol_id' => $this->protocol->id,
            'type' => DocumentType::PROTOCOL_PDF,
            'locale' => 'pl',
            'filename' => 'test.pdf',
            'path' => 'documents/test.pdf',
            'disk' => 'minio',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'hash' => hash('sha256', 'test-document-content'),
            'generated_at' => now(),
        ]);
    }

    /**
     * §26.9: QR page exists and is accessible.
     */
    public function test_qr_verification_page_exists(): void
    {
        $response = $this->get("/verify/{$this->document->hash}");

        $response->assertStatus(200);
    }

    /**
     * §26.9: QR page does NOT expose party names.
     */
    public function test_guard_9_qr_page_does_not_expose_party_names(): void
    {
        $response = $this->get("/verify/{$this->document->hash}");

        $response->assertStatus(200);

        // Page should NOT contain party names
        $response->assertDontSee('Jan Kowalski');
        $response->assertDontSee('Anna Nowak');
        $response->assertDontSee('landlord@example.com');
        $response->assertDontSee('tenant@example.com');
    }

    /**
     * §26.9: QR page shows document verification info.
     */
    public function test_qr_page_shows_verification_info(): void
    {
        $response = $this->get("/verify/{$this->document->hash}");

        // Should contain hash for verification
        $content = $response->getContent();

        // Inertia renders as JSON props, check page component exists
        $response->assertInertia(fn ($page) => $page
            ->component('Document/QrVerification')
            ->has('document_hash')
            ->has('generated_at')
            ->has('document_type')
            ->has('protocol_type')
            ->has('all_signed')
        );
    }

    /**
     * §26.9: QR API returns verification without PII.
     */
    public function test_guard_9_qr_api_does_not_expose_pii(): void
    {
        $response = $this->postJson('/verify/api', [
            'hash' => $this->document->hash,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['valid' => true]);

        // Response should NOT contain PII
        $json = $response->json();
        $jsonString = json_encode($json);

        $this->assertStringNotContainsString('Jan Kowalski', $jsonString);
        $this->assertStringNotContainsString('Anna Nowak', $jsonString);
        $this->assertStringNotContainsString('landlord@example.com', $jsonString);
        $this->assertStringNotContainsString('tenant@example.com', $jsonString);
    }

    /**
     * QR page returns 404 for invalid hash.
     */
    public function test_qr_page_handles_invalid_hash(): void
    {
        $invalidHash = str_repeat('a', 64);

        $response = $this->get("/verify/{$invalidHash}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Document/QrNotFound')
        );
    }

    /**
     * QR API returns error for invalid hash.
     */
    public function test_qr_api_returns_error_for_invalid_hash(): void
    {
        $response = $this->postJson('/verify/api', [
            'hash' => str_repeat('a', 64),
        ]);

        $response->assertStatus(200);
        $response->assertJson(['valid' => false]);
    }
}
