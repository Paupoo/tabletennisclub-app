<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Shared\Events\Interclub\TeamCreated;
use App\Domains\Shared\Events\Meetings\MeetingCreated;
use App\Domains\Shared\Events\Subscriptions\SubscriptionCreated;
use App\Domains\Shared\Events\Subscriptions\SubscriptionPaid;
use App\Domains\Shared\Events\Tournament\NewTournamentPublished;
use App\Domains\Shared\Events\Tournament\UserRegisteredToTournament;
use App\Domains\Shared\Events\Tournament\UserUnregisteredFromTournament;
use App\Domains\Shared\Events\Trainings\TrainingPackEnrolled;
use App\Listeners\NotifyParticipantsOfMeeting;
use App\Listeners\SendSubscriptionConfirmationEmail;
use App\Listeners\SendTeamCreatedNotification;
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
        SubscriptionCreated::class => [
            SendSubscriptionConfirmationEmail::class,
        ],
        SubscriptionPaid::class => [
            // Listener for subscription paid
        ],
        MeetingCreated::class => [
            NotifyParticipantsOfMeeting::class,
        ],
        TeamCreated::class => [
            SendTeamCreatedNotification::class,
        ],
        TrainingPackEnrolled::class => [
            // Listener for training pack enrollment
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
