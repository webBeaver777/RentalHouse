<?php

declare(strict_types=1);

namespace App\Modules\Lifecycle\Application\Console\Commands;

use App\Modules\Lifecycle\Application\Jobs\CompleteExpiredObjectionWindows;
use App\Modules\Lifecycle\Application\Jobs\SendObjectionWindowReminders;
use App\Modules\Lifecycle\Application\Services\LifecycleService;
use Illuminate\Console\Command;

class ProcessLifecycleCommand extends Command
{
    protected $signature = 'lifecycle:process';

    protected $description = 'Process protocol lifecycle tasks (archive, auto-complete, reminders)';

    public function handle(LifecycleService $lifecycleService): int
    {
        $this->info('Processing lifecycle tasks...');

        // Archive protocols past access_expires_at
        $this->info('Checking for protocols to archive...');
        $archivedCount = $lifecycleService->archiveExpiredProtocols();
        $this->info("Archived {$archivedCount} expired protocols.");

        // Complete expired objection windows
        $this->info('Checking for expired objection windows...');
        CompleteExpiredObjectionWindows::dispatch();

        // Send reminders for closing objection windows
        $this->info('Checking for objection window reminders...');
        SendObjectionWindowReminders::dispatch();

        $this->info('Lifecycle tasks completed successfully.');

        return self::SUCCESS;
    }
}
