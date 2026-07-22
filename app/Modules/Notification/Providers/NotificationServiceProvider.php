<?php

declare(strict_types=1);

namespace App\Modules\Notification\Providers;

use App\Modules\Notification\Application\Listeners\SendInvitationNotification;
use App\Modules\Notification\Application\Listeners\SendObjectionRaisedNotification;
use App\Modules\Notification\Application\Listeners\SendObjectionWindowNotification;
use App\Modules\Notification\Application\Listeners\SendProtocolCompletedNotification;
use App\Modules\Notification\Application\Listeners\SendProtocolSignedNotification;
use App\Modules\Notification\Domain\Events\ObjectionRaised;
use App\Modules\Notification\Domain\Events\ObjectionWindowClosing;
use App\Modules\Notification\Domain\Events\ObjectionWindowOpened;
use App\Modules\Notification\Domain\Events\ParticipantInvited;
use App\Modules\Notification\Domain\Events\ProtocolCompleted;
use App\Modules\Notification\Domain\Events\ProtocolSigned;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Infrastructure/Migrations');
        $this->registerEventListeners();
    }

    private function registerEventListeners(): void
    {
        Event::listen(ParticipantInvited::class, SendInvitationNotification::class);
        Event::listen(ProtocolSigned::class, SendProtocolSignedNotification::class);
        Event::listen(ProtocolCompleted::class, SendProtocolCompletedNotification::class);
        Event::listen(ObjectionRaised::class, SendObjectionRaisedNotification::class);

        Event::listen(
            ObjectionWindowOpened::class,
            [SendObjectionWindowNotification::class, 'handleOpened']
        );
        Event::listen(
            ObjectionWindowClosing::class,
            [SendObjectionWindowNotification::class, 'handleClosing']
        );
    }
}
