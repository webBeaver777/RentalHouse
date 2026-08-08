<?php

declare(strict_types=1);

namespace Tests\Feature\Evidence;

use App\Modules\Catalog\Domain\Enums\CatalogItemType;
use App\Modules\Catalog\Infrastructure\Models\CatalogItem;
use App\Modules\Evidence\Infrastructure\Models\Evidence;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Property\Domain\Enums\DeclarationType;
use App\Modules\Property\Infrastructure\Models\Property;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use App\Modules\Protocol\Infrastructure\Models\ProtocolItem;
use App\Modules\Protocol\Infrastructure\Models\ProtocolRoom;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * M10.3, Scenario A slice: photo evidence HTTP layer on a draft check-in
 * protocol item — upload/delete through EvidenceUploadService only
 * (SHA-256 hash), draft-only + initiator guard, and the proxying serve
 * route (see CLAUDE.md's "expected failure point": internal MinIO
 * endpoint vs browser-reachable URL).
 *
 * Same convention as ProtocolRoomItemHttpTest: CSRF disabled here only,
 * Storage::fake('minio') so no real MinIO write happens in tests.
 */
class ProtocolItemPhotoHttpTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Protocol $draftProtocol;

    private ProtocolRoom $room;

    private ProtocolItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->seed(CatalogSeeder::class);
        Storage::fake('minio');

        $this->user = User::create([
            'name' => 'Jan Kowalski',
            'email' => 'jan@example.com',
            'password' => 'password123',
        ]);

        $property = Property::create([
            'user_id' => $this->user->id,
            'name' => 'Mieszkanie Testowe',
            'street' => 'ul. Testowa',
            'building_number' => '1',
            'city' => 'Warszawa',
            'postal_code' => '00-001',
            'declaration_type' => DeclarationType::OWNER,
        ]);

        $this->draftProtocol = Protocol::create([
            'property_id' => $property->id,
            'created_by_user_id' => $this->user->id,
            'type' => ProtocolType::CHECK_IN,
            'title' => 'Protokół wjazdu',
        ]);

        $roomType = CatalogItem::ofType(CatalogItemType::ROOM)->first();
        $itemTemplate = CatalogItem::ofType(CatalogItemType::ITEM)->first();

        $this->room = ProtocolRoom::create([
            'protocol_id' => $this->draftProtocol->id,
            'catalog_item_id' => $roomType->id,
        ]);

        $this->item = ProtocolItem::create([
            'protocol_room_id' => $this->room->id,
            'catalog_item_id' => $itemTemplate->id,
        ]);
    }

    private function otherUser(): User
    {
        return User::create([
            'name' => 'Anna Nowak',
            'email' => 'anna@example.com',
            'password' => 'password123',
        ]);
    }

    private function uploadRoute(): string
    {
        return route('protocols.rooms.items.photos.store', [$this->draftProtocol, $this->room, $this->item]);
    }

    /* --- upload through the service: SHA-256 hash proof --- */

    public function test_initiator_can_upload_photo_and_hash_is_set(): void
    {
        $file = UploadedFile::fake()->image('test.jpg', 800, 600);

        $response = $this->actingAs($this->user)->post($this->uploadRoute(), ['photo' => $file]);

        $response->assertRedirect(route('protocols.show', $this->draftProtocol));

        $evidence = Evidence::where('evidenceable_type', ProtocolItem::class)
            ->where('evidenceable_id', $this->item->id)
            ->firstOrFail();

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $evidence->hash);
        $this->assertEquals($this->draftProtocol->id, $evidence->protocol_id);
        Storage::disk('minio')->assertExists($evidence->path);
    }

    /* --- rejects: oversized / invalid type --- */

    public function test_oversized_photo_is_rejected(): void
    {
        $file = UploadedFile::fake()->create('huge.jpg', 15000, 'image/jpeg'); // 15MB

        $response = $this->actingAs($this->user)->post($this->uploadRoute(), ['photo' => $file]);

        $response->assertSessionHasErrors(['photo']);
        $this->assertDatabaseCount('evidences', 0);
    }

    public function test_invalid_file_type_is_rejected(): void
    {
        $file = UploadedFile::fake()->create('script.exe', 100, 'application/x-msdownload');

        $response = $this->actingAs($this->user)->post($this->uploadRoute(), ['photo' => $file]);

        $response->assertSessionHasErrors(['photo']);
        $this->assertDatabaseCount('evidences', 0);
    }

    /* --- KRYTYCZNY GUARD: draft-only + initiator --- */

    public function test_upload_is_rejected_on_non_draft_protocol(): void
    {
        $protocol = Protocol::create([
            'property_id' => $this->draftProtocol->property_id,
            'created_by_user_id' => $this->user->id,
            'type' => ProtocolType::CHECK_IN,
            'title' => 'Protokół podpisany',
            'status' => ProtocolStatus::SIGNED,
        ]);
        $room = ProtocolRoom::create([
            'protocol_id' => $protocol->id,
            'catalog_item_id' => CatalogItem::ofType(CatalogItemType::ROOM)->first()->id,
        ]);
        $item = ProtocolItem::create([
            'protocol_room_id' => $room->id,
            'catalog_item_id' => CatalogItem::ofType(CatalogItemType::ITEM)->first()->id,
        ]);

        $file = UploadedFile::fake()->image('test.jpg');

        $response = $this->actingAs($this->user)->post(
            route('protocols.rooms.items.photos.store', [$protocol, $room, $item]),
            ['photo' => $file]
        );

        $response->assertStatus(422);
        $this->assertDatabaseCount('evidences', 0);
    }

    public function test_non_initiator_cannot_upload_photo(): void
    {
        $file = UploadedFile::fake()->image('test.jpg');

        $response = $this->actingAs($this->otherUser())->post($this->uploadRoute(), ['photo' => $file]);

        $response->assertForbidden();
        $this->assertDatabaseCount('evidences', 0);
    }

    public function test_guest_cannot_upload_photo(): void
    {
        $file = UploadedFile::fake()->image('test.jpg');

        $response = $this->post($this->uploadRoute(), ['photo' => $file]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('evidences', 0);
    }

    /* --- delete --- */

    public function test_initiator_can_delete_photo_in_draft(): void
    {
        $file = UploadedFile::fake()->image('test.jpg');
        $this->actingAs($this->user)->post($this->uploadRoute(), ['photo' => $file]);
        $evidence = Evidence::firstOrFail();

        $response = $this->actingAs($this->user)->delete(
            route('protocols.rooms.items.photos.destroy', [$this->draftProtocol, $this->room, $this->item, $evidence])
        );

        $response->assertRedirect(route('protocols.show', $this->draftProtocol));
        $this->assertSoftDeleted('evidences', ['id' => $evidence->id]);
    }

    public function test_non_initiator_cannot_delete_photo(): void
    {
        $file = UploadedFile::fake()->image('test.jpg');
        $this->actingAs($this->user)->post($this->uploadRoute(), ['photo' => $file]);
        $evidence = Evidence::firstOrFail();

        $response = $this->actingAs($this->otherUser())->delete(
            route('protocols.rooms.items.photos.destroy', [$this->draftProtocol, $this->room, $this->item, $evidence])
        );

        $response->assertForbidden();
        $this->assertDatabaseHas('evidences', ['id' => $evidence->id, 'deleted_at' => null]);
    }

    /* --- serving route (proxy, avoids the internal-endpoint browser mismatch) --- */

    public function test_initiator_can_fetch_photo_bytes(): void
    {
        $file = UploadedFile::fake()->image('test.jpg');
        $this->actingAs($this->user)->post($this->uploadRoute(), ['photo' => $file]);
        $evidence = Evidence::firstOrFail();

        $response = $this->actingAs($this->user)->get(
            route('protocols.evidence.show', [$this->draftProtocol, $evidence])
        );

        $response->assertOk();
        $response->assertHeader('Content-Type', $evidence->mime_type);
    }

    public function test_non_initiator_cannot_fetch_photo_bytes(): void
    {
        $file = UploadedFile::fake()->image('test.jpg');
        $this->actingAs($this->user)->post($this->uploadRoute(), ['photo' => $file]);
        $evidence = Evidence::firstOrFail();

        $response = $this->actingAs($this->otherUser())->get(
            route('protocols.evidence.show', [$this->draftProtocol, $evidence])
        );

        $response->assertForbidden();
    }
}
