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
 * D5: Document hash freeze tests.
 *
 * document_hash is calculated ONCE and does NOT change on PDF regeneration.
 */
class DocumentHashFreezeTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private Property $property;

    private Protocol $protocol;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlord = User::create([
            'name' => 'Jan Kowalski',
            'email' => 'landlord@example.com',
            'password' => 'password',
        ]);

        $this->property = Property::create([
            'user_id' => $this->landlord->id,
            'name' => 'Mieszkanie testowe',
            'street' => 'Testowa',
            'building_number' => '1',
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

        Participant::create([
            'protocol_id' => $this->protocol->id,
            'user_id' => $this->landlord->id,
            'role' => ParticipantRole::LANDLORD,
            'is_initiator' => true,
            'signed_at' => now(),
        ]);
    }

    /**
     * D5: document_hash is frozen on first generation.
     *
     * Once set, it should not change even if we create new document versions.
     */
    public function test_document_hash_is_frozen_on_first_generation(): void
    {
        // Initially protocol has no document_hash
        $this->assertNull($this->protocol->document_hash);

        // First "generation" - set the hash
        $firstHash = hash('sha256', 'first-pdf-content-'.time());
        $this->protocol->document_hash = $firstHash;
        $this->protocol->save();

        // Create first document
        $firstDocument = GeneratedDocument::create([
            'protocol_id' => $this->protocol->id,
            'type' => DocumentType::PROTOCOL_PDF,
            'locale' => 'pl',
            'filename' => 'protocol_v1.pdf',
            'path' => 'documents/protocol_v1.pdf',
            'disk' => 'minio',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'hash' => $firstHash,
            'generated_at' => now(),
        ]);

        // Reload protocol
        $this->protocol->refresh();

        // document_hash should be set
        $this->assertEquals($firstHash, $this->protocol->document_hash);

        // Simulate "regeneration" - create second document with different content hash
        $secondContentHash = hash('sha256', 'second-pdf-content-different');

        $secondDocument = GeneratedDocument::create([
            'protocol_id' => $this->protocol->id,
            'type' => DocumentType::PROTOCOL_PDF,
            'locale' => 'pl',
            'version' => '1.1',
            'filename' => 'protocol_v1.1.pdf',
            'path' => 'documents/protocol_v1.1.pdf',
            'disk' => 'minio',
            'mime_type' => 'application/pdf',
            'size' => 2048,
            'hash' => $secondContentHash,
            'generated_at' => now(),
        ]);

        // Reload protocol again
        $this->protocol->refresh();

        // D5: document_hash on protocol MUST remain the same (frozen)
        $this->assertEquals($firstHash, $this->protocol->document_hash);
        $this->assertNotEquals($secondContentHash, $this->protocol->document_hash);
    }

    /**
     * D5: Protocol document_hash is not overwritten by subsequent documents.
     */
    public function test_protocol_hash_not_overwritten_by_new_documents(): void
    {
        $originalHash = hash('sha256', 'original-content');

        // Set initial hash on protocol
        $this->protocol->document_hash = $originalHash;
        $this->protocol->save();

        // Create multiple documents with different hashes
        for ($i = 1; $i <= 3; $i++) {
            GeneratedDocument::create([
                'protocol_id' => $this->protocol->id,
                'type' => DocumentType::PROTOCOL_PDF,
                'locale' => 'pl',
                'version' => "1.{$i}",
                'filename' => "protocol_v1.{$i}.pdf",
                'path' => "documents/protocol_v1.{$i}.pdf",
                'disk' => 'minio',
                'mime_type' => 'application/pdf',
                'size' => 1024 * $i,
                'hash' => hash('sha256', "content-version-{$i}"),
                'generated_at' => now(),
            ]);
        }

        // Reload and verify
        $this->protocol->refresh();

        // Protocol's document_hash must still be the original
        $this->assertEquals($originalHash, $this->protocol->document_hash);

        // Multiple documents exist
        $this->assertEquals(3, GeneratedDocument::where('protocol_id', $this->protocol->id)->count());
    }

    /**
     * D5: Each GeneratedDocument has its own content hash.
     */
    public function test_each_document_version_has_own_hash(): void
    {
        $hashes = [];

        for ($i = 1; $i <= 3; $i++) {
            $hash = hash('sha256', "unique-content-{$i}-".microtime());
            $hashes[] = $hash;

            GeneratedDocument::create([
                'protocol_id' => $this->protocol->id,
                'type' => DocumentType::PROTOCOL_PDF,
                'locale' => 'pl',
                'version' => "1.{$i}",
                'filename' => "protocol_v1.{$i}.pdf",
                'path' => "documents/protocol_v1.{$i}.pdf",
                'disk' => 'minio',
                'mime_type' => 'application/pdf',
                'size' => 1024,
                'hash' => $hash,
                'generated_at' => now(),
            ]);
        }

        // All documents should have unique hashes
        $documents = GeneratedDocument::where('protocol_id', $this->protocol->id)->get();

        $documentHashes = $documents->pluck('hash')->toArray();
        $this->assertEquals($hashes, $documentHashes);
        $this->assertEquals(count($hashes), count(array_unique($documentHashes)));
    }

    /**
     * D5: QR verification uses protocol's frozen document_hash.
     */
    public function test_qr_verification_uses_frozen_protocol_hash(): void
    {
        $frozenHash = hash('sha256', 'frozen-protocol-hash');

        $this->protocol->document_hash = $frozenHash;
        $this->protocol->save();

        // Create document with different hash
        GeneratedDocument::create([
            'protocol_id' => $this->protocol->id,
            'type' => DocumentType::PROTOCOL_PDF,
            'locale' => 'pl',
            'filename' => 'protocol.pdf',
            'path' => 'documents/protocol.pdf',
            'disk' => 'minio',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'hash' => $frozenHash,
            'generated_at' => now(),
        ]);

        // QR verification should work with the frozen hash
        $response = $this->get("/verify/{$frozenHash}");
        $response->assertStatus(200);
        $response->assertViewIs('qr.verification');
    }
}
