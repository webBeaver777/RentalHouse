<?php

declare(strict_types=1);

namespace App\Modules\Lifecycle\Application\Console\Commands;

use App\Modules\Lifecycle\Application\Jobs\CompleteExpiredObjectionWindows;
use App\Modules\Lifecycle\Application\Jobs\SendObjectionWindowReminders;
use Illuminate\Console\Command;

class ProcessLifecycleCommand extends Command
{
    protected $signature = 'lifecycle:process';

    protected $description = 'Process protocol lifecycle tasks (auto-complete, reminders)';

    public function handle(): int
    {
        $this->info('Processing lifecycle tasks...');

        // Complete expired objection windows
        $this->info('Checking for expired objection windows...');
        CompleteExpiredObjectionWindows::dispatch();

        // Send reminders for closing objection windows
        $this->info('Checking for objection window reminders...');
        SendObjectionWindowReminders::dispatch();

        $this->info('Lifecycle tasks dispatched successfully.');

        return self::SUCCESS;
    }
}
