<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\EntitlementResource;
use App\Filament\Resources\GeneratedDocumentResource;
use App\Filament\Resources\InspectionEventResource;
use App\Filament\Resources\LocaleResource;
use App\Filament\Resources\PaymentResource;
use App\Filament\Resources\ProtocolResource;
use App\Filament\Resources\UserResource;
use App\Modules\Billing\Domain\Enums\AllowedAction;
use App\Modules\Billing\Domain\Enums\PaymentStatus;
use App\Modules\Billing\Domain\Enums\ProductCode;
use App\Modules\Billing\Infrastructure\Models\Entitlement;
use App\Modules\Billing\Infrastructure\Models\Payment;
use App\Modules\Document\Domain\Enums\DocumentType;
use App\Modules\Document\Infrastructure\Models\GeneratedDocument;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Participation\Domain\Enums\ParticipantRole;
use App\Modules\Participation\Infrastructure\Models\Participant;
use App\Modules\Property\Domain\Enums\DeclarationType;
use App\Modules\Property\Infrastructure\Models\Property;
use App\Modules\Protocol\Domain\Enums\InspectionEventType;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Infrastructure\Models\InspectionEvent;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M8-VERIFY: regression test for the Filament 5 migration bug found in
 * PropertyResource/CatalogItemResource — form field components imported
 * from Filament\Schemas\Components instead of Filament\Forms\Components,
 * and row actions (EditAction/ViewAction/BulkActionGroup/...) imported
 * from Filament\Tables\Actions instead of Filament\Actions. None of
 * these six resources had ever been hit by an HTTP-level test, so all
 * six were silently broken (500 on first real page load) until fixed here.
 */
class AdminResourcesSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);
    }

    public function test_locale_resource_list_and_create_are_accessible(): void
    {
        $this->actingAs($this->admin)->get(LocaleResource::getUrl('index'))->assertOk();
        $this->actingAs($this->admin)->get(LocaleResource::getUrl('create'))->assertOk();
    }

    public function test_user_resource_list_and_create_are_accessible(): void
    {
        $this->actingAs($this->admin)->get(UserResource::getUrl('index'))->assertOk();
        $this->actingAs($this->admin)->get(UserResource::getUrl('create'))->assertOk();
    }

    public function test_inspection_event_resource_list_is_accessible(): void
    {
        $this->actingAs($this->admin)->get(InspectionEventResource::getUrl('index'))->assertOk();
    }

    public function test_payment_resource_list_is_accessible(): void
    {
        $this->actingAs($this->admin)->get(PaymentResource::getUrl('index'))->assertOk();
    }

    public function test_entitlement_resource_list_is_accessible(): void
    {
        $this->actingAs($this->admin)->get(EntitlementResource::getUrl('index'))->assertOk();
    }

    public function test_generated_document_resource_list_is_accessible(): void
    {
        $this->actingAs($this->admin)->get(GeneratedDocumentResource::getUrl('index'))->assertOk();
    }

    public function test_protocol_resource_list_is_accessible(): void
    {
        $this->actingAs($this->admin)->get(ProtocolResource::getUrl('index'))->assertOk();
    }

    /**
     * M8-VERIFY fix: ViewProtocol's infolist used 'initiator.xxx' /
     * 'counterparty.xxx' dot paths against Protocol::initiator()/
     * counterparty() helper methods (not real Eloquent relations) —
     * same "getRelated() on null" crash as the table column bug, but
     * on the view page instead of the list. Needs an actual participant
     * present to also catch the non-null variant (a real Relation would
     * be introspectable; a plain model instance is not).
     */
    public function test_protocol_resource_view_with_participants_is_accessible(): void
    {
        $landlord = User::create([
            'name' => 'Jan Kowalski',
            'email' => 'jan@example.com',
            'password' => bcrypt('password'),
        ]);

        $property = Property::create([
            'user_id' => $landlord->id,
            'name' => 'Test Property',
            'street' => 'ul. Testowa',
            'building_number' => '1',
            'city' => 'Warszawa',
            'postal_code' => '00-001',
            'declaration_type' => DeclarationType::OWNER,
        ]);

        $protocol = Protocol::create([
            'property_id' => $property->id,
            'created_by_user_id' => $landlord->id,
            'type' => ProtocolType::CHECK_IN,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'title' => 'Test Protocol',
        ]);

        Participant::create([
            'protocol_id' => $protocol->id,
            'user_id' => $landlord->id,
            'role' => ParticipantRole::LANDLORD,
            'is_initiator' => true,
        ]);

        Participant::create([
            'protocol_id' => $protocol->id,
            'role' => ParticipantRole::TENANT,
            'is_initiator' => false,
            'invited_email' => 'tenant@example.com',
        ]);

        $this->actingAs($this->admin)
            ->get(ProtocolResource::getUrl('view', ['record' => $protocol]))
            ->assertOk()
            ->assertSee('tenant@example.com');
    }

    /**
     * M8-VERIFY fix: ViewPayment/ViewEntitlement/ViewGeneratedDocument/
     * ViewInspectionEvent infolists imported TextEntry from
     * Filament\Schemas\Components instead of Filament\Infolists\Components
     * — same class of bug as the Forms/Actions namespace mixups, just a
     * third package. Confirmed via Payment::user(), Entitlement::user()/
     * payment(), GeneratedDocument::protocol()/generatedBy() that these
     * dot-paths are real Eloquent relations, not pseudo-relation helpers
     * like Protocol::counterparty() — so only the import was broken.
     */
    public function test_payment_resource_view_is_accessible(): void
    {
        $user = User::create([
            'name' => 'Jan Kowalski',
            'email' => 'jan-payment@example.com',
            'password' => bcrypt('password'),
        ]);

        $payment = Payment::create([
            'user_id' => $user->id,
            'p24_session_id' => 'test-session-'.uniqid(),
            'product_code' => ProductCode::WJAZD,
            'amount' => 4900,
            'currency' => 'PLN',
            'description' => 'Wjazd — protokół zdawczo-odbiorczy',
            'status' => PaymentStatus::PENDING,
            'buyer_email' => 'jan-payment@example.com',
            'is_sandbox' => true,
        ]);

        $this->actingAs($this->admin)
            ->get(PaymentResource::getUrl('view', ['record' => $payment]))
            ->assertOk()
            ->assertSee('jan-payment@example.com');
    }

    public function test_entitlement_resource_view_is_accessible(): void
    {
        $user = User::create([
            'name' => 'Jan Kowalski',
            'email' => 'jan-entitlement@example.com',
            'password' => bcrypt('password'),
        ]);

        $entitlement = Entitlement::create([
            'user_id' => $user->id,
            'product_code' => ProductCode::WJAZD,
            'allowed_action' => AllowedAction::CREATE_CHECK_IN,
            'quantity_total' => 1,
            'quantity_used' => 0,
        ]);

        $this->actingAs($this->admin)
            ->get(EntitlementResource::getUrl('view', ['record' => $entitlement]))
            ->assertOk()
            ->assertSee('jan-entitlement@example.com');
    }

    public function test_generated_document_resource_view_is_accessible(): void
    {
        $protocol = $this->createMinimalProtocol();

        $document = GeneratedDocument::create([
            'protocol_id' => $protocol->id,
            'type' => DocumentType::PROTOCOL_PDF,
            'filename' => 'protocol.pdf',
            'path' => 'documents/protocol.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'hash' => hash('sha256', 'test-content'),
            'generated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get(GeneratedDocumentResource::getUrl('view', ['record' => $document]))
            ->assertOk();
    }

    public function test_inspection_event_resource_view_is_accessible(): void
    {
        $protocol = $this->createMinimalProtocol();

        $event = InspectionEvent::record(
            $protocol,
            InspectionEventType::PROTOCOL_STATUS_CHANGED,
            'system',
        );

        $this->actingAs($this->admin)
            ->get(InspectionEventResource::getUrl('view', ['record' => $event]))
            ->assertOk();
    }

    private function createMinimalProtocol(): Protocol
    {
        $landlord = User::create([
            'name' => 'Jan Kowalski',
            'email' => 'landlord-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]);

        $property = Property::create([
            'user_id' => $landlord->id,
            'name' => 'Test Property',
            'street' => 'ul. Testowa',
            'building_number' => '1',
            'city' => 'Warszawa',
            'postal_code' => '00-001',
            'declaration_type' => DeclarationType::OWNER,
        ]);

        return Protocol::create([
            'property_id' => $property->id,
            'created_by_user_id' => $landlord->id,
            'type' => ProtocolType::CHECK_IN,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'title' => 'Test Protocol',
        ]);
    }
}
