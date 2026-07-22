<?php

declare(strict_types=1);

namespace Tests\Feature\Evidence;

use App\Modules\Catalog\Domain\Enums\CatalogItemType;
use App\Modules\Catalog\Infrastructure\Models\CatalogItem;
use App\Modules\Evidence\Application\Services\EvidenceUploadService;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EvidenceUploadServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Protocol $protocol;

    private ProtocolItem $item;

    private EvidenceUploadService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogSeeder::class);

        Storage::fake('minio');

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

        $this->service = new EvidenceUploadService('minio');
    }

    /**
     * Can upload a photo.
     */
    public function test_can_upload_photo(): void
    {
        $file = UploadedFile::fake()->image('test.jpg', 800, 600);

        $evidence = $this->service->upload(
            $file,
            $this->protocol,
            $this->user,
        );

        $this->assertInstanceOf(Evidence::class, $evidence);
        $this->assertEquals(EvidenceType::PHOTO, $evidence->type);
        $this->assertEquals('test.jpg', $evidence->original_filename);
        $this->assertStringEndsWith('.jpg', $evidence->filename);

        Storage::disk('minio')->assertExists($evidence->path);
    }

    /**
     * Can upload with caption.
     */
    public function test_can_upload_with_caption(): void
    {
        $file = UploadedFile::fake()->image('test.jpg');

        $evidence = $this->service->upload(
            $file,
            $this->protocol,
            $this->user,
            caption: 'Zarysowanie na ścianie',
        );

        $this->assertEquals('Zarysowanie na ścianie', $evidence->caption);
    }

    /**
     * Can attach evidence to item.
     */
    public function test_can_attach_to_item(): void
    {
        $file = UploadedFile::fake()->image('test.jpg');

        $evidence = $this->service->upload(
            $file,
            $this->protocol,
            $this->user,
            attachTo: $this->item,
        );

        $this->assertEquals($this->item->id, $evidence->evidenceable_id);
        $this->assertEquals(ProtocolItem::class, $evidence->evidenceable_type);
    }

    /**
     * Can upload PDF document.
     */
    public function test_can_upload_pdf(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $evidence = $this->service->upload(
            $file,
            $this->protocol,
            $this->user,
        );

        $this->assertEquals(EvidenceType::DOCUMENT, $evidence->type);
        $this->assertEquals('application/pdf', $evidence->mime_type);
    }

    /**
     * Can upload multiple files.
     */
    public function test_can_upload_multiple(): void
    {
        $files = [
            UploadedFile::fake()->image('photo1.jpg'),
            UploadedFile::fake()->image('photo2.jpg'),
            UploadedFile::fake()->image('photo3.jpg'),
        ];

        $evidences = $this->service->uploadMultiple(
            $files,
            $this->protocol,
            $this->user,
            attachTo: $this->item,
        );

        $this->assertCount(3, $evidences);

        // Check sort order
        $this->assertEquals(0, $evidences[0]->sort_order);
        $this->assertEquals(1, $evidences[1]->sort_order);
        $this->assertEquals(2, $evidences[2]->sort_order);
    }

    /**
     * Generates unique filename.
     */
    public function test_generates_unique_filename(): void
    {
        $file1 = UploadedFile::fake()->image('same_name.jpg');
        $file2 = UploadedFile::fake()->image('same_name.jpg');

        $evidence1 = $this->service->upload($file1, $this->protocol, $this->user);
        $evidence2 = $this->service->upload($file2, $this->protocol, $this->user);

        $this->assertNotEquals($evidence1->filename, $evidence2->filename);
        $this->assertNotEquals($evidence1->path, $evidence2->path);
    }

    /**
     * Extracts image dimensions.
     */
    public function test_extracts_image_dimensions(): void
    {
        $file = UploadedFile::fake()->image('test.jpg', 1920, 1080);

        $evidence = $this->service->upload(
            $file,
            $this->protocol,
            $this->user,
        );

        $this->assertNotNull($evidence->metadata);
        $this->assertEquals(1920, $evidence->metadata['width']);
        $this->assertEquals(1080, $evidence->metadata['height']);
    }

    /**
     * Can delete evidence and file.
     */
    public function test_can_delete_evidence(): void
    {
        $file = UploadedFile::fake()->image('test.jpg');

        $evidence = $this->service->upload(
            $file,
            $this->protocol,
            $this->user,
        );

        $path = $evidence->path;
        Storage::disk('minio')->assertExists($path);

        // Soft delete
        $this->service->delete($evidence);
        $this->assertSoftDeleted('evidences', ['id' => $evidence->id]);
        Storage::disk('minio')->assertExists($path);

        // Force delete
        $this->service->delete($evidence->fresh(), forceDelete: true);
        $this->assertDatabaseMissing('evidences', ['id' => $evidence->id]);
        Storage::disk('minio')->assertMissing($path);
    }

    /**
     * Rejects oversized files.
     */
    public function test_rejects_oversized_files(): void
    {
        // Create a file that's too large (assuming default 10MB limit)
        $file = UploadedFile::fake()->create('huge.jpg', 15000, 'image/jpeg'); // 15MB

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File size exceeds maximum');

        $this->service->upload($file, $this->protocol, $this->user);
    }

    /**
     * Rejects invalid file types.
     */
    public function test_rejects_invalid_file_types(): void
    {
        $file = UploadedFile::fake()->create('script.exe', 100, 'application/x-msdownload');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File type not allowed');

        $this->service->upload($file, $this->protocol, $this->user);
    }

    /**
     * Custom metadata is preserved.
     */
    public function test_custom_metadata_preserved(): void
    {
        $file = UploadedFile::fake()->image('test.jpg', 800, 600);
        $customMetadata = ['custom_key' => 'custom_value'];

        $evidence = $this->service->upload(
            $file,
            $this->protocol,
            $this->user,
            metadata: $customMetadata,
        );

        $this->assertEquals('custom_value', $evidence->metadata['custom_key']);
        // Image dimensions should also be there
        $this->assertEquals(800, $evidence->metadata['width']);
    }
}
