<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Lifecycle\Application\Services\LifecycleService;
use App\Modules\Protocol\Domain\Enums\ProtocolStatus;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Console\Command;

/**
 * D8: Lifecycle maintenance command.
 *
 * Should be scheduled to run daily:
 * $schedule->command('lifecycle:maintenance')->daily();
 */
class LifecycleMaintenanceCommand extends Command
{
    protected $signature = 'lifecycle:maintenance
        {--archive-only : Only archive expired protocols}
        {--purge-only : Only purge protocols past retention}
        {--dry-run : Show what would be done without doing it}';

    protected $description = 'D8: Archive expired protocols and purge past retention';

    public function handle(LifecycleService $lifecycleService): int
    {
        $dryRun = $this->option('dry-run');
        $archiveOnly = $this->option('archive-only');
        $purgeOnly = $this->option('purge-only');

        if ($dryRun) {
            $this->warn('Dry run mode - no changes will be made');
        }

        $archiveCount = 0;
        $purgeCount = 0;

        // Archive expired protocols
        if (! $purgeOnly) {
            $this->info('Archiving expired protocols...');

            if ($dryRun) {
                $archiveCount = $this->countExpiredProtocols();
                $this->info("Would archive {$archiveCount} protocols");
            } else {
                $archiveCount = $lifecycleService->archiveExpiredProtocols();
                $this->info("Archived {$archiveCount} protocols");
            }
        }

        // Purge protocols past retention
        if (! $archiveOnly) {
            $this->info('Purging protocols past retention...');

            if ($dryRun) {
                $purgeCount = $this->countRetentionExpiredProtocols();
                $this->info("Would purge {$purgeCount} protocols");
            } else {
                $purgeCount = $lifecycleService->purgeExpiredProtocols();
                $this->info("Purged {$purgeCount} protocols");
            }
        }

        $this->newLine();
        $this->table(
            ['Action', 'Count'],
            [
                ['Archived', $archiveCount],
                ['Purged', $purgeCount],
            ]
        );

        return Command::SUCCESS;
    }

    private function countExpiredProtocols(): int
    {
        return Protocol::query()
            ->whereNotNull('access_expires_at')
            ->where('access_expires_at', '<=', now())
            ->where('status', '!=', ProtocolStatus::ARCHIVED)
            ->whereNull('deleted_at')
            ->count();
    }

    private function countRetentionExpiredProtocols(): int
    {
        return Protocol::query()
            ->whereNotNull('retention_until')
            ->where('retention_until', '<=', now())
            ->whereNull('deleted_at')
            ->count();
    }
}
