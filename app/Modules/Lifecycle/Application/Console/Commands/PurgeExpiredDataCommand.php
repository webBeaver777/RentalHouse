<?php

declare(strict_types=1);

namespace App\Modules\Lifecycle\Application\Console\Commands;

use App\Modules\Lifecycle\Application\Services\RodoComplianceService;
use App\Modules\Protocol\Infrastructure\Models\Protocol;
use Illuminate\Console\Command;

class PurgeExpiredDataCommand extends Command
{
    protected $signature = 'lifecycle:purge-expired
                            {--dry-run : Show what would be deleted without actually deleting}';

    protected $description = 'Purge protocol data past retention period (RODO compliance)';

    public function handle(RodoComplianceService $rodoService): int
    {
        $isDryRun = $this->option('dry-run');

        $protocolIds = $rodoService->getProtocolsForDeletion();

        if (empty($protocolIds)) {
            $this->info('No protocols found past retention period.');

            return self::SUCCESS;
        }

        $count = count($protocolIds);
        $this->info("Found {$count} protocols past retention period.");

        if ($isDryRun) {
            $this->warn('DRY RUN - No data will be deleted.');
            $this->table(
                ['Protocol ID'],
                array_map(fn ($id) => [$id], $protocolIds)
            );

            return self::SUCCESS;
        }

        if (! $this->confirm('Are you sure you want to permanently delete these protocols?')) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $count = count($protocolIds);
        $bar = $this->output->createProgressBar($count);

        foreach ($protocolIds as $id) {
            $protocol = Protocol::find($id);
            if ($protocol) {
                $rodoService->purgeExpiredProtocol($protocol);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Purged {$count} expired protocols.");

        return self::SUCCESS;
    }
}
