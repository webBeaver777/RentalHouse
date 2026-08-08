<?php

declare(strict_types=1);

namespace Tests\Feature\Protocol;

use App\Modules\Billing\Domain\Enums\AllowedAction;
use App\Modules\Billing\Domain\Enums\ProductCode;
use App\Modules\Billing\Infrastructure\Models\Entitlement;
use App\Modules\Billing\Infrastructure\Models\EntitlementUsage;
use App\Modules\Catalog\Domain\Enums\CatalogItemType;
use App\Modules\Catalog\Infrastructure\Models\CatalogItem;
use App\Modules\Document\Domain\Enums\DocumentType;
use App\Modules\Document\Infrastructure\Models\GeneratedDocument;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Participation\Application\Services\InvitationService;
use App\Modules\Participation\Domain\Enums\ParticipantRole;
use App\Modules\Property\Domain\Enums\DeclarationType;
use App\Modules\Property\Infrastructure\Models\Property;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use App\Modules\Protocol\Infrastructure\Models\ProtocolRoom;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * M10.6, Scenario A slice: finalize a signed check-in protocol -> completed
 * + frozen PDF + document_hash. Wires the frozen FinalizeCheckInAction /
 * PdfGenerationService (see ProtocolFinalizeController / ProtocolDocumentController).
 *
 * Every test starts from a protocol that is already SIGNED (both sides
 * accepted via the M10.5 flow) — finalize is the thing under test here.
 */
class ProtocolFinalizeHttpTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private Protocol $protocol;

    private InvitationService $invitationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->seed(CatalogSeeder::class);
        // Real PDF rendering (DomPDF) runs for real in these tests — only the
        // MinIO write is faked, same convention as ProtocolItemPhotoHttpTest.
        Storage::fake('minio');

        $this->landlord = User::create([
            'name' => 'Jan Kowalski',
            'email' => 'jan@example.com',
            'password' => 'password123',
        ]);

        $property = Property::create([
            'user_id' => $this->landlord->id,
            'name' => 'Mieszkanie Testowe',
            'street' => 'ul. Testowa',
            'building_number' => '1',
            'city' => 'Warszawa',
            'postal_code' => '00-001',
            'declaration_type' => DeclarationType::OWNER,
        ]);

        $this->protocol = Protocol::create([
            'property_id' => $property->id,
            'created_by_user_id' => $this->landlord->id,
            'type' => ProtocolType::CHECK_IN,
            'title' => 'Protokół wjazdu',
        ]);

        $this->invitationService = app(InvitationService::class);
        $this->invitationService->addInitiator($this->protocol, $this->landlord, ParticipantRole::LANDLORD);

        Entitlement::create([
            'user_id' => $this->landlord->id,
            'product_code' => ProductCode::WJAZD,
            'allowed_action' => AllowedAction::CREATE_CHECK_IN,
            'quantity_total' => 1,
            'quantity_used' => 0,
            'valid_until' => now()->addMonth(),
            'activated_at' => now(),
        ]);

        $token = $this->invitationService->inviteByEmail($this->protocol, 'najemca@example.com', ParticipantRole::TENANT, false, 72);
        $this->protocol->submitToCounterparty();

        // Dual sign -> protocol is SIGNED before each test body runs.
        $this->post(route('invitation.sign', $token->raw_token), ['consent' => true]);
        $this->actingAs($this->landlord)->post(route('protocols.sign', $this->protocol));

        // actingAs() sets the acting user on the guard for the rest of the
        // test, not just the chained call above — reset it so a bare
        // $this->post(...) in a test body (e.g. test_guest_cannot_finalize)
        // is genuinely unauthenticated instead of silently still landlord.
        $this->app['auth']->forgetGuards();

        $this->protocol->refresh();
    }

    private function otherUser(): User
    {
        return User::create([
            'name' => 'Anna Nowak',
            'email' => 'anna@example.com',
            'password' => 'password123',
        ]);
    }

    /* --- finalize: hard-gate + transition --- */

    public function test_finalize_transitions_signed_to_completed(): void
    {
        $this->assertEquals(ProtocolStatus::SIGNED, $this->protocol->status);

        $response = $this->actingAs($this->landlord)->post(route('protocols.finalize', $this->protocol));

        $response->assertRedirect(route('protocols.show', $this->protocol));

        $this->protocol->refresh();
        $this->assertEquals(ProtocolStatus::COMPLETED, $this->protocol->status);
        $this->assertNotNull($this->protocol->completed_at);
    }

    public function test_finalize_twice_is_rejected(): void
    {
        $this->actingAs($this->landlord)->post(route('protocols.finalize', $this->protocol));

        $response = $this->actingAs($this->landlord)->post(route('protocols.finalize', $this->protocol));

        $response->assertSessionHasErrors(['finalize']);
        $this->assertEquals(1, GeneratedDocument::where('protocol_id', $this->protocol->id)->count());
    }

    public function test_non_initiator_cannot_finalize(): void
    {
        $response = $this->actingAs($this->otherUser())->post(route('protocols.finalize', $this->protocol));

        $response->assertForbidden();
        $this->protocol->refresh();
        $this->assertEquals(ProtocolStatus::SIGNED, $this->protocol->status);
    }

    public function test_guest_cannot_finalize(): void
    {
        $response = $this->post(route('protocols.finalize', $this->protocol));

        $response->assertRedirect(route('login'));
    }

    /* --- entitlement: consumed once at submit, must NOT be consumed again --- */

    public function test_finalize_does_not_double_consume_entitlement(): void
    {
        $entitlement = Entitlement::where('user_id', $this->landlord->id)->firstOrFail();
        $this->assertEquals(1, $entitlement->quantity_used);
        $this->assertEquals(1, EntitlementUsage::where('protocol_id', $this->protocol->id)->count());

        $this->actingAs($this->landlord)->post(route('protocols.finalize', $this->protocol));

        $entitlement->refresh();
        $this->assertEquals(1, $entitlement->quantity_used);
        $this->assertEquals(1, EntitlementUsage::where('protocol_id', $this->protocol->id)->count());
    }

    /* --- PDF + document_hash freeze --- */

    public function test_finalize_generates_pdf_and_freezes_document_hash(): void
    {
        $this->actingAs($this->landlord)->post(route('protocols.finalize', $this->protocol));

        $this->protocol->refresh();
        $this->assertNotNull($this->protocol->document_hash);

        $document = GeneratedDocument::where('protocol_id', $this->protocol->id)
            ->where('type', DocumentType::PROTOCOL_PDF)
            ->firstOrFail();

        $this->assertEquals($this->protocol->document_hash, $document->hash);
        $this->assertGreaterThan(0, $document->size);
    }

    /* --- document download --- */

    public function test_initiator_can_download_document_after_finalize(): void
    {
        $this->actingAs($this->landlord)->post(route('protocols.finalize', $this->protocol));

        $response = $this->actingAs($this->landlord)->get(route('protocols.document', $this->protocol));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_document_download_before_finalize_is_not_found(): void
    {
        $response = $this->actingAs($this->landlord)->get(route('protocols.document', $this->protocol));

        $response->assertNotFound();
    }

    public function test_non_initiator_cannot_download_document(): void
    {
        $this->actingAs($this->landlord)->post(route('protocols.finalize', $this->protocol));

        $response = $this->actingAs($this->otherUser())->get(route('protocols.document', $this->protocol));

        $response->assertForbidden();
    }

    /* --- QR verification, end-to-end for a real finalized protocol (guard 9 stays true) --- */

    public function test_qr_verification_confirms_authenticity_without_pii(): void
    {
        $this->actingAs($this->landlord)->post(route('protocols.finalize', $this->protocol));
        $this->protocol->refresh();

        $response = $this->get(route('qr.verify', ['hash' => $this->protocol->document_hash]));

        $response->assertOk();
        $response->assertSee('Protokół wjazdowy');
        $response->assertDontSee('Jan Kowalski');
        $response->assertDontSee('najemca@example.com');
    }

    /* --- immutability after completed (M10.6 step 4) --- */

    public function test_room_mutation_is_rejected_on_completed_protocol(): void
    {
        $this->actingAs($this->landlord)->post(route('protocols.finalize', $this->protocol));

        $response = $this->actingAs($this->landlord)->post(route('protocols.rooms.store', $this->protocol), [
            'catalog_item_id' => 1,
        ]);

        $response->assertStatus(422);
    }

    public function test_room_delete_is_rejected_on_completed_protocol(): void
    {
        // Room created directly via the model — the draft-only guard lives in
        // the controller (AuthorizesDraftProtocolMutation), not the model, so
        // this bypass is only to get a real room to attempt to delete.
        $room = ProtocolRoom::create([
            'protocol_id' => $this->protocol->id,
            'catalog_item_id' => CatalogItem::ofType(CatalogItemType::ROOM)->first()->id,
        ]);

        $this->actingAs($this->landlord)->post(route('protocols.finalize', $this->protocol));

        $response = $this->actingAs($this->landlord)->delete(route('protocols.rooms.destroy', [$this->protocol, $room]));

        $response->assertStatus(422);
        $this->assertDatabaseHas('protocol_rooms', ['id' => $room->id, 'deleted_at' => null]);
    }
}
