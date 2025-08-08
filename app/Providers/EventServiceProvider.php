<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\Tournament\NewTournamentPublished;
use App\Events\Tournament\UserRegisteredToTournament;
use App\Events\Tournament\UserUnregisteredFromTournament;
use App\Listeners\SendWelcomeEmail;
use App\Listeners\Tournament\SendPublishedTournamentNotification;
use App\Listeners\Tournament\UserRegisteredToTournamentToTournament;
use App\Listeners\Tournament\UserUnregisteredToTournamentToTournament;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
            SendWelcomeEmail::class,
        ],
        NewTournamentPublished::class => [
            SendPublishedTournamentNotification::class,
        ],
        UserRegisteredToTournament::class => [
            UserRegisteredToTournamentToTournament::class,
        ],
        UserUnregisteredFromTournament::class => [
            UserUnregisteredToTournamentToTournament::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
