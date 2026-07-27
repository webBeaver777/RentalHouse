<?php

declare(strict_types=1);

namespace App\Modules\Lifecycle\Application\Console\Commands;

use App\Modules\Lifecycle\Application\Services\LifecycleService;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Console\Command;

class PurgeExpiredDataCommand extends Command
{
    protected $signature = 'lifecycle:purge-expired
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Purge protocol data past retention period (soft-delete with audit trail)';

    public function handle(LifecycleService $lifecycleService): int
    {
        $isDryRun = $this->option('dry-run');
        $isForce = $this->option('force');

        // Find protocols past retention_until
        $protocols = Protocol::query()
            ->whereNotNull('retention_until')
            ->where('retention_until', '<=', now())
            ->whereNull('deleted_at')
            ->get();

        if ($protocols->isEmpty()) {
            $this->info('No protocols found past retention period.');

            return self::SUCCESS;
        }

        $count = $protocols->count();
        $this->info("Found {$count} protocols past retention period.");

        if ($isDryRun) {
            $this->warn('DRY RUN - No data will be deleted.');
            $this->table(
                ['Protocol ID', 'Retention Until', 'Status'],
                $protocols->map(fn ($p) => [
                    $p->id,
                    $p->retention_until->toDateTimeString(),
                    $p->status->value,
                ])->toArray()
            );

            return self::SUCCESS;
        }

        if (! $isForce && ! $this->confirm('Are you sure you want to soft-delete these protocols?')) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($count);

        foreach ($protocols as $protocol) {
            $lifecycleService->purge($protocol);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Purged {$count} expired protocols.");

        return self::SUCCESS;
    }
}
