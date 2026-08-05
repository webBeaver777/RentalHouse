<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| M8-VERIFY correction: lifecycle scheduling already exists — do NOT
| register it here too.
|--------------------------------------------------------------------------
| An earlier session added Schedule::command('lifecycle:process')/
| ('lifecycle:purge-expired')/('protocol:close-objection-windows') here,
| duplicating a schedule that already lives in
| app/Modules/Lifecycle/Providers/LifecycleServiceProvider.php::boot()
| (lifecycle:process every 15 min, lifecycle:purge-expired monthly), and
| additionally referencing 'protocol:close-objection-windows', which
| never existed — that caused a real hourly
| Symfony\Component\Console\Exception\NamespaceNotFoundException in the
| scheduler log. Objection-window closure + 24h-before reminders are
| already dispatched from inside lifecycle:process via
| App\Modules\Lifecycle\Application\Jobs\CompleteExpiredObjectionWindows
| and SendObjectionWindowReminders. Removed both duplicate entries here.
|
| Known open item (surfaced, not silently fixed): CompleteExpiredObjectionWindows
| skips auto-completion when hasUnresolvedObjections() is true — worth
| checking against the §21 "objection never blocks the act" guard, since
| the protocol is already SIGNED/act_issued_at by this point and arguably
| shouldn't wait on objection resolution to reach `completed`.
*/
