<?php

declare(strict_types=1);

namespace Tests\Feature\Evidence;

use App\Modules\Catalog\Domain\Enums\CatalogItemType;
use App\Modules\Catalog\Infrastructure\Models\CatalogItem;
use App\Modules\Evidence\Domain\Enums\EvidenceType;
use App\Modules\Evidence\Infrastructure\Models\Evidence;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Property\Domain\Enums\DeclarationType;
use App\Modules\Property\Infrastructure\Models\Property;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use App\Modules\Protocol\Infrastructure\Models\ProtocolItem;
use App\Modules\Protocol\Infrastructure\Models\ProtocolRoom;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvidenceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Protocol $protocol;

    private ProtocolItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogSeeder::class);

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $property = Property::create([
            'user_id' => $this->user->id,
            'name' => 'Test Property',
            'street' => 'Test Street',
            'building_number' => '1',
            'city' => 'Warsaw',
            'postal_code' => '00-001',
            'declaration_type' => DeclarationType::OWNER,
        ]);

        $this->protocol = Protocol::create([
            'property_id' => $property->id,
            'created_by_user_id' => $this->user->id,
            'type' => ProtocolType::CHECK_IN,
            'title' => 'Test Protocol',
        ]);

        $roomCatalog = CatalogItem::ofType(CatalogItemType::ROOM)->first();
        $itemCatalog = CatalogItem::ofType(CatalogItemType::ITEM)->first();

        $room = ProtocolRoom::create([
            'protocol_id' => $this->protocol->id,
            'catalog_item_id' => $roomCatalog->id,
        ]);

        $this->item = ProtocolItem::create([
            'protocol_room_id' => $room->id,
            'catalog_item_id' => $itemCatalog->id,
        ]);
    }

    /**
     * Evidence can be created as photo.
     */
    public function test_evidence_can_be_created_as_photo(): void
    {
        $evidence = Evidence::create([
            'protocol_id' => $this->protocol->id,
            'evidenceable_type' => ProtocolItem::class,
            'evidenceable_id' => $this->item->id,
            'uploaded_by_user_id' => $this->user->id,
            'type' => EvidenceType::PHOTO,
            'filename' => 'photo_123.jpg',
            'original_filename' => 'living_room.jpg',
            'path' => 'protocols/'.$this->protocol->id.'/photo_123.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024000,
        ]);

        $this->assertDatabaseHas('evidences', [
            'id' => $evidence->id,
            'type' => 'photo',
        ]);
    }

    /**
     * Evidence has UUID.
     */
    public function test_evidence_has_uuid(): void
    {
        $evidence = Evidence::create([
            'protocol_id' => $this->protocol->id,
            'uploaded_by_user_id' => $this->user->id,
            'type' => EvidenceType::PHOTO,
            'filename' => 'photo_123.jpg',
            'original_filename' => 'test.jpg',
            'path' => 'test/photo_123.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
        ]);

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $evidence->id
        );
    }

    /**
     * Evidence belongs to protocol.
     */
    public function test_evidence_belongs_to_protocol(): void
    {
        $evidence = Evidence::create([
            'protocol_id' => $this->protocol->id,
            'uploaded_by_user_id' => $this->user->id,
            'type' => EvidenceType::PHOTO,
            'filename' => 'photo_123.jpg',
            'original_filename' => 'test.jpg',
            'path' => 'test/photo_123.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
        ]);

        $this->assertEquals($this->protocol->id, $evidence->protocol->id);
    }

    /**
     * Evidence belongs to uploader.
     */
    public function test_evidence_belongs_to_uploader(): void
    {
        $evidence = Evidence::create([
            'protocol_id' => $this->protocol->id,
            'uploaded_by_user_id' => $this->user->id,
            'type' => EvidenceType::PHOTO,
            'filename' => 'photo_123.jpg',
            'original_filename' => 'test.jpg',
            'path' => 'test/photo_123.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
        ]);

        $this->assertEquals($this->user->id, $evidence->uploadedBy->id);
    }

    /**
     * Evidence can be attached to protocol item.
     */
    public function test_evidence_can_be_attached_to_item(): void
    {
        $evidence = Evidence::create([
            'protocol_id' => $this->protocol->id,
            'evidenceable_type' => ProtocolItem::class,
            'evidenceable_id' => $this->item->id,
            'uploaded_by_user_id' => $this->user->id,
            'type' => EvidenceType::PHOTO,
            'filename' => 'photo_123.jpg',
            'original_filename' => 'test.jpg',
            'path' => 'test/photo_123.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
        ]);

        $this->assertEquals($this->item->id, $evidence->evidenceable->id);
        $this->assertInstanceOf(ProtocolItem::class, $evidence->evidenceable);
    }

    /**
     * Protocol item has evidences relationship.
     */
    public function test_item_has_evidences(): void
    {
        Evidence::create([
            'protocol_id' => $this->protocol->id,
            'evidenceable_type' => ProtocolItem::class,
            'evidenceable_id' => $this->item->id,
            'uploaded_by_user_id' => $this->user->id,
            'type' => EvidenceType::PHOTO,
            'filename' => 'photo_1.jpg',
            'original_filename' => 'test1.jpg',
            'path' => 'test/photo_1.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'sort_order' => 1,
        ]);

        Evidence::create([
            'protocol_id' => $this->protocol->id,
            'evidenceable_type' => ProtocolItem::class,
            'evidenceable_id' => $this->item->id,
            'uploaded_by_user_id' => $this->user->id,
            'type' => EvidenceType::PHOTO,
            'filename' => 'photo_2.jpg',
            'original_filename' => 'test2.jpg',
            'path' => 'test/photo_2.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 2048,
            'sort_order' => 2,
        ]);

        $this->assertEquals(2, $this->item->evidences()->count());
        $this->assertEquals(2, $this->item->photos()->count());
    }

    /**
     * Evidence can have caption.
     */
    public function test_evidence_can_have_caption(): void
    {
        $evidence = Evidence::create([
            'protocol_id' => $this->protocol->id,
            'uploaded_by_user_id' => $this->user->id,
            'type' => EvidenceType::PHOTO,
            'filename' => 'photo_123.jpg',
            'original_filename' => 'test.jpg',
            'path' => 'test/photo_123.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'caption' => 'Zarysowanie na ścianie',
        ]);

        $this->assertEquals('Zarysowanie na ścianie', $evidence->caption);
    }

    /**
     * Evidence has human-readable size.
     */
    public function test_evidence_has_human_readable_size(): void
    {
        $evidence = Evidence::create([
            'protocol_id' => $this->protocol->id,
            'uploaded_by_user_id' => $this->user->id,
            'type' => EvidenceType::PHOTO,
            'filename' => 'photo_123.jpg',
            'original_filename' => 'test.jpg',
            'path' => 'test/photo_123.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1536000, // ~1.5 MB
        ]);

        $this->assertStringContainsString('MB', $evidence->human_size);
    }

    /**
     * Evidence type checks work.
     */
    public function test_evidence_type_checks(): void
    {
        $photo = Evidence::create([
            'protocol_id' => $this->protocol->id,
            'uploaded_by_user_id' => $this->user->id,
            'type' => EvidenceType::PHOTO,
            'filename' => 'photo.jpg',
            'original_filename' => 'test.jpg',
            'path' => 'test/photo.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
        ]);

        $document = Evidence::create([
            'protocol_id' => $this->protocol->id,
            'uploaded_by_user_id' => $this->user->id,
            'type' => EvidenceType::DOCUMENT,
            'filename' => 'doc.pdf',
            'original_filename' => 'test.pdf',
            'path' => 'test/doc.pdf',
            'mime_type' => 'application/pdf',
            'size' => 2048,
        ]);

        $this->assertTrue($photo->isImage());
        $this->assertFalse($photo->isDocument());

        $this->assertTrue($document->isDocument());
        $this->assertFalse($document->isImage());
    }

    /**
     * Evidence scopes work.
     */
    public function test_evidence_scopes(): void
    {
        Evidence::create([
            'protocol_id' => $this->protocol->id,
            'uploaded_by_user_id' => $this->user->id,
            'type' => EvidenceType::PHOTO,
            'filename' => 'photo.jpg',
            'original_filename' => 'test.jpg',
            'path' => 'test/photo.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
        ]);

        Evidence::create([
            'protocol_id' => $this->protocol->id,
            'uploaded_by_user_id' => $this->user->id,
            'type' => EvidenceType::DOCUMENT,
            'filename' => 'doc.pdf',
            'original_filename' => 'test.pdf',
            'path' => 'test/doc.pdf',
            'mime_type' => 'application/pdf',
            'size' => 2048,
        ]);

        $this->assertEquals(1, Evidence::photos()->count());
        $this->assertEquals(1, Evidence::documents()->count());
        $this->assertEquals(2, Evidence::forProtocol($this->protocol)->count());
    }

    /**
     * Evidence can be soft deleted.
     */
    public function test_evidence_can_be_soft_deleted(): void
    {
        $evidence = Evidence::create([
            'protocol_id' => $this->protocol->id,
            'uploaded_by_user_id' => $this->user->id,
            'type' => EvidenceType::PHOTO,
            'filename' => 'photo.jpg',
            'original_filename' => 'test.jpg',
            'path' => 'test/photo.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
        ]);

        $evidenceId = $evidence->id;
        $evidence->delete();

        $this->assertSoftDeleted('evidences', ['id' => $evidenceId]);
    }

    /**
     * Evidence stores metadata.
     */
    public function test_evidence_stores_metadata(): void
    {
        $metadata = [
            'width' => 1920,
            'height' => 1080,
            'exif' => ['camera' => 'iPhone 15'],
        ];

        $evidence = Evidence::create([
            'protocol_id' => $this->protocol->id,
            'uploaded_by_user_id' => $this->user->id,
            'type' => EvidenceType::PHOTO,
            'filename' => 'photo.jpg',
            'original_filename' => 'test.jpg',
            'path' => 'test/photo.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'metadata' => $metadata,
        ]);

        $this->assertEquals($metadata, $evidence->fresh()->metadata);
    }
}
