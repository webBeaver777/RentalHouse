<?php

declare(strict_types=1);

namespace Tests\Feature\Lifecycle;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Lifecycle\Application\Services\LifecycleService;
use App\Modules\Participation\Domain\Enums\ParticipantRole;
use App\Modules\Property\Domain\Enums\DeclarationType;
use App\Modules\Property\Infrastructure\Models\Property;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Domain\Enums\ProtocolType;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for lifecycle:process command.
 */
class ProcessLifecycleCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Property $property;

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
    }

    /**
     * Test lifecycle:process command runs successfully.
     */
    public function test_lifecycle_process_command_runs_successfully(): void
    {
        $this->artisan('lifecycle:process')
            ->assertSuccessful()
            ->expectsOutputToContain('Processing lifecycle tasks')
            ->expectsOutputToContain('Archived 0 expired protocols');
    }

    /**
     * Test lifecycle:process archives expired protocols.
     */
    public function test_lifecycle_process_archives_expired_protocols(): void
    {
        // Create protocol with expired access
        $protocol = $this->createProtocol(ProtocolStatus::COMPLETED);
        app(LifecycleService::class)->onPayment($protocol, now());

        // Travel past access expiration
        $this->travel(13)->months();

        $this->artisan('lifecycle:process')
            ->assertSuccessful()
            ->expectsOutputToContain('Archived 1 expired protocols');

        $protocol->refresh();
        $this->assertEquals(ProtocolStatus::ARCHIVED, $protocol->status);
    }

    /**
     * Test lifecycle:maintenance command with dry-run.
     */
    public function test_lifecycle_maintenance_dry_run(): void
    {
        // Create protocol with expired access
        $protocol = $this->createProtocol(ProtocolStatus::COMPLETED);
        app(LifecycleService::class)->onPayment($protocol, now());

        // Travel past access expiration
        $this->travel(13)->months();

        $this->artisan('lifecycle:maintenance --dry-run')
            ->assertSuccessful()
            ->expectsOutputToContain('Dry run mode')
            ->expectsOutputToContain('Would archive 1 protocols');

        // Protocol should NOT be archived (dry run)
        $protocol->refresh();
        $this->assertEquals(ProtocolStatus::COMPLETED, $protocol->status);
    }

    /**
     * Test lifecycle:maintenance command archives and purges.
     */
    public function test_lifecycle_maintenance_archives_and_purges(): void
    {
        // Create 2 protocols: one for archive, one for purge
        $archiveProtocol = $this->createProtocol(ProtocolStatus::COMPLETED);
        app(LifecycleService::class)->onPayment($archiveProtocol, now()->subMonths(13));

        $purgeProtocol = $this->createProtocol(ProtocolStatus::COMPLETED);
        $purgeProtocol->paid_at = now()->subMonths(50);
        $purgeProtocol->access_expires_at = now()->subMonths(38);
        $purgeProtocol->retention_until = now()->subMonths(2);
        $purgeProtocol->save();

        $this->artisan('lifecycle:maintenance')
            ->assertSuccessful();

        // First should be archived
        $archiveProtocol->refresh();
        $this->assertEquals(ProtocolStatus::ARCHIVED, $archiveProtocol->status);

        // Second should be soft-deleted
        $this->assertSoftDeleted('protocols', ['id' => $purgeProtocol->id]);
    }

    /**
     * Test lifecycle:purge-expired with dry-run.
     */
    public function test_lifecycle_purge_expired_dry_run(): void
    {
        // Create protocol past retention
        $protocol = $this->createProtocol(ProtocolStatus::COMPLETED);
        $protocol->paid_at = now()->subMonths(50);
        $protocol->access_expires_at = now()->subMonths(38);
        $protocol->retention_until = now()->subMonths(2);
        $protocol->save();

        $this->artisan('lifecycle:purge-expired --dry-run')
            ->assertSuccessful()
            ->expectsOutputToContain('DRY RUN')
            ->expectsOutputToContain('Found 1 protocols');

        // Protocol should NOT be deleted (dry run)
        $this->assertDatabaseHas('protocols', ['id' => $protocol->id]);
    }

    /**
     * Test lifecycle:purge-expired with force flag.
     */
    public function test_lifecycle_purge_expired_force(): void
    {
        // Create protocol past retention
        $protocol = $this->createProtocol(ProtocolStatus::COMPLETED);
        $protocol->paid_at = now()->subMonths(50);
        $protocol->access_expires_at = now()->subMonths(38);
        $protocol->retention_until = now()->subMonths(2);
        $protocol->save();

        $this->artisan('lifecycle:purge-expired --force')
            ->assertSuccessful()
            ->expectsOutputToContain('Purged 1 expired protocols');

        $this->assertSoftDeleted('protocols', ['id' => $protocol->id]);
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
