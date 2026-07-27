<?php

declare(strict_types=1);

namespace Tests\Feature\Lifecycle;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Lifecycle\Application\Services\LifecycleService;
use App\Modules\Participation\Domain\Enums\ParticipantRole;
use App\Modules\Property\Domain\Enums\DeclarationType;
use App\Modules\Property\Infrastructure\Models\Property;
use App\Modules\Protocol\Domain\Enums\InspectionEventType;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Infrastructure\Models\InspectionEvent;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D8: Lifecycle management tests with time travel.
 *
 * Tests:
 * - access_expires_at = paid_at + 12 months
 * - retention_until = access_expires_at + 3 years
 * - Archive transition when access expires
 * - Soft-delete after retention_until
 * - Audit trail for all transitions
 */
class LifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Property $property;

    private LifecycleService $lifecycleService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([LocaleSeeder::class, CatalogSeeder::class]);

        $this->user = User::create([
            'name' => 'Jan Kowalski',
            'email' => 'jan@example.com',
            'password' => 'password123',
        ]);

        $this->property = Property::create([
            'user_id' => $this->user->id,
            'name' => 'Test Property',
            'street' => 'ul. Testowa',
            'building_number' => '1',
            'city' => 'Warszawa',
            'postal_code' => '00-001',
            'declaration_type' => DeclarationType::OWNER,
        ]);

        $this->lifecycleService = app(LifecycleService::class);
    }

    /**
     * Test that onPayment sets correct lifecycle dates.
     *
     * Canon: paid_at + 12 months = access_expires_at
     *        access_expires_at + 3 years = retention_until
     */
    public function test_on_payment_sets_lifecycle_dates(): void
    {
        $protocol = $this->createProtocol();
        $paidAt = now();

        $this->lifecycleService->onPayment($protocol, $paidAt);

        $protocol->refresh();

        $this->assertEquals($paidAt->toDateString(), $protocol->paid_at->toDateString());
        $this->assertEquals(
            $paidAt->copy()->addMonths(12)->toDateString(),
            $protocol->access_expires_at->toDateString()
        );
        $this->assertEquals(
            $paidAt->copy()->addMonths(12)->addYears(3)->toDateString(),
            $protocol->retention_until->toDateString()
        );
    }

    /**
     * Test that onPayment records audit event.
     */
    public function test_on_payment_records_audit_event(): void
    {
        $protocol = $this->createProtocol();

        $this->lifecycleService->onPayment($protocol, now());

        $event = InspectionEvent::where('protocol_id', $protocol->id)
            ->where('event_type', InspectionEventType::PROTOCOL_STATUS_CHANGED)
            ->whereJsonContains('payload->action', 'lifecycle_started')
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals('system', $event->actor_role);
        $this->assertArrayHasKey('paid_at', $event->payload);
        $this->assertArrayHasKey('access_expires_at', $event->payload);
        $this->assertArrayHasKey('retention_until', $event->payload);
    }

    /**
     * Test hasAccess returns true before expiration.
     */
    public function test_has_access_returns_true_before_expiration(): void
    {
        $protocol = $this->createProtocol();
        $this->lifecycleService->onPayment($protocol, now());

        $protocol->refresh();

        $this->assertTrue($this->lifecycleService->hasAccess($protocol));
    }

    /**
     * Test hasAccess returns false after expiration using time travel.
     */
    public function test_has_access_returns_false_after_expiration(): void
    {
        $protocol = $this->createProtocol();
        $this->lifecycleService->onPayment($protocol, now());

        $protocol->refresh();

        // Travel 13 months into the future
        $this->travel(13)->months();

        $this->assertFalse($this->lifecycleService->hasAccess($protocol));
    }

    /**
     * Test archiveExpiredProtocols archives protocols past access_expires_at.
     */
    public function test_archive_expired_protocols_with_time_travel(): void
    {
        $protocol = $this->createProtocol(ProtocolStatus::COMPLETED);
        $this->lifecycleService->onPayment($protocol, now());

        // Protocol should not be archived yet
        $archiveCount = $this->lifecycleService->archiveExpiredProtocols();
        $this->assertEquals(0, $archiveCount);

        // Travel 13 months into the future
        $this->travel(13)->months();

        $archiveCount = $this->lifecycleService->archiveExpiredProtocols();
        $this->assertEquals(1, $archiveCount);

        $protocol->refresh();
        $this->assertEquals(ProtocolStatus::ARCHIVED, $protocol->status);
    }

    /**
     * Test archive records audit event.
     */
    public function test_archive_records_audit_event(): void
    {
        $protocol = $this->createProtocol(ProtocolStatus::COMPLETED);
        $this->lifecycleService->onPayment($protocol, now());

        // Travel past expiration
        $this->travel(13)->months();

        $this->lifecycleService->archiveExpiredProtocols();

        $event = InspectionEvent::where('protocol_id', $protocol->id)
            ->where('event_type', InspectionEventType::PROTOCOL_STATUS_CHANGED)
            ->whereJsonContains('payload->action', 'archived')
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals('access_expired', $event->payload['reason']);
        $this->assertEquals('completed', $event->payload['previous_status']);
    }

    /**
     * Test purgeExpiredProtocols soft-deletes protocols past retention_until.
     */
    public function test_purge_expired_protocols_with_time_travel(): void
    {
        $protocol = $this->createProtocol(ProtocolStatus::COMPLETED);
        $this->lifecycleService->onPayment($protocol, now());

        // Protocol should not be purged yet
        $purgeCount = $this->lifecycleService->purgeExpiredProtocols();
        $this->assertEquals(0, $purgeCount);

        // Travel 12 months + 3 years + 1 month into the future
        $this->travel(12 + 36 + 1)->months();

        $purgeCount = $this->lifecycleService->purgeExpiredProtocols();
        $this->assertEquals(1, $purgeCount);

        // Protocol should be soft-deleted
        $this->assertSoftDeleted('protocols', ['id' => $protocol->id]);
    }

    /**
     * Test purge records audit event before deletion.
     */
    public function test_purge_records_audit_event(): void
    {
        $protocol = $this->createProtocol(ProtocolStatus::COMPLETED);
        $this->lifecycleService->onPayment($protocol, now());

        // Travel past retention
        $this->travel(50)->months();

        $this->lifecycleService->purgeExpiredProtocols();

        $event = InspectionEvent::where('protocol_id', $protocol->id)
            ->where('event_type', InspectionEventType::PROTOCOL_STATUS_CHANGED)
            ->whereJsonContains('payload->action', 'purged')
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals('retention_expired', $event->payload['reason']);
    }

    /**
     * Test full lifecycle: payment -> archive -> purge.
     */
    public function test_full_lifecycle_with_time_travel(): void
    {
        $protocol = $this->createProtocol(ProtocolStatus::COMPLETED);

        // Step 1: Payment
        $this->lifecycleService->onPayment($protocol, now());
        $protocol->refresh();
        $this->assertTrue($this->lifecycleService->hasAccess($protocol));

        // Step 2: Travel to after access expires (13 months)
        $this->travel(13)->months();
        $this->assertFalse($this->lifecycleService->hasAccess($protocol));

        // Step 3: Archive
        $archived = $this->lifecycleService->archiveExpiredProtocols();
        $this->assertEquals(1, $archived);
        $protocol->refresh();
        $this->assertEquals(ProtocolStatus::ARCHIVED, $protocol->status);

        // Step 4: Travel to after retention (3 more years + 1 month)
        $this->travel(37)->months();

        // Step 5: Purge
        $purged = $this->lifecycleService->purgeExpiredProtocols();
        $this->assertEquals(1, $purged);
        $this->assertSoftDeleted('protocols', ['id' => $protocol->id]);
    }

    /**
     * Test extendAccess extends the access period.
     */
    public function test_extend_access_extends_period(): void
    {
        $protocol = $this->createProtocol(ProtocolStatus::COMPLETED);
        $this->lifecycleService->onPayment($protocol, now());

        $originalExpires = $protocol->fresh()->access_expires_at;

        $this->lifecycleService->extendAccess($protocol, 6);

        $protocol->refresh();

        // Should be extended by 6 months from original expiration
        $this->assertEquals(
            $originalExpires->copy()->addMonths(6)->toDateString(),
            $protocol->access_expires_at->toDateString()
        );
    }

    /**
     * Test extendAccess records audit event.
     */
    public function test_extend_access_records_audit_event(): void
    {
        $protocol = $this->createProtocol(ProtocolStatus::COMPLETED);
        $this->lifecycleService->onPayment($protocol, now());

        $this->lifecycleService->extendAccess($protocol, 6);

        $event = InspectionEvent::where('protocol_id', $protocol->id)
            ->where('event_type', InspectionEventType::PROTOCOL_STATUS_CHANGED)
            ->whereJsonContains('payload->action', 'access_extended')
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals(6, $event->payload['months_extended']);
    }

    /**
     * Test getExpiringProtocols returns protocols expiring within given days.
     */
    public function test_get_expiring_protocols(): void
    {
        // Create protocol expiring in 20 days
        $protocol = $this->createProtocol(ProtocolStatus::COMPLETED);
        $protocol->paid_at = now();
        $protocol->access_expires_at = now()->addDays(20);
        $protocol->retention_until = now()->addDays(20)->addYears(3);
        $protocol->save();

        // Should be found with 30 day window
        $expiring = $this->lifecycleService->getExpiringProtocols(30);
        $this->assertCount(1, $expiring);
        $this->assertEquals($protocol->id, $expiring->first()->id);

        // Should not be found with 10 day window
        $expiring = $this->lifecycleService->getExpiringProtocols(10);
        $this->assertCount(0, $expiring);
    }

    /**
     * Test lifecycle uses config values.
     */
    public function test_lifecycle_uses_config_values(): void
    {
        // Temporarily change config
        config(['lifecycle.access_months' => 6]);
        config(['lifecycle.retention_years' => 2]);

        $protocol = $this->createProtocol();
        $paidAt = now();

        $this->lifecycleService->onPayment($protocol, $paidAt);

        $protocol->refresh();

        $this->assertEquals(
            $paidAt->copy()->addMonths(6)->toDateString(),
            $protocol->access_expires_at->toDateString()
        );
        $this->assertEquals(
            $paidAt->copy()->addMonths(6)->addYears(2)->toDateString(),
            $protocol->retention_until->toDateString()
        );
    }

    /**
     * Helper to create a protocol.
     */
    private function createProtocol(ProtocolStatus $status = ProtocolStatus::DRAFT): Protocol
    {
        return Protocol::create([
            'property_id' => $this->property->id,
            'created_by_user_id' => $this->user->id,
            'type' => ProtocolType::CHECK_IN,
            'initiator_role' => ParticipantRole::LANDLORD,
            'counterparty_role' => ParticipantRole::TENANT,
            'status' => $status,
            'title' => 'Test Protocol',
            'locale' => 'pl',
        ]);
    }
}
