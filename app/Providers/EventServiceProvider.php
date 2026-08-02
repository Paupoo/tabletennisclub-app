<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Shared\Events\Interclub\TeamCreated;
use App\Domains\Shared\Events\Meetings\MeetingCreated;
use App\Domains\Shared\Events\Subscriptions\SubscriptionCreated;
use App\Domains\Shared\Events\Tournament\NewTournamentPublished;
use App\Domains\Shared\Events\Tournament\UserUnregisteredFromTournament;
use App\Listeners\NotifyParticipantsOfMeeting;
use App\Listeners\SendSubscriptionConfirmationEmail;
use App\Listeners\SendTeamCreatedNotification;
use App\Listeners\SendWelcomeEmail;
use App\Listeners\Tournament\SendPublishedTournamentNotification;
use App\Listeners\Tournament\UserUnregisteredToTournamentToTournament;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * Declared by hand rather than discovered. Discovery is switched off in
     * bootstrap/app.php: it scans app/Listeners and would register a second
     * copy of everything listed here, so a member would get two welcome mails.
     *
     * The events that had no listener — SubscriptionPaid, TrainingPackEnrolled —
     * are gone from this map. An empty array registered nothing; it only made
     * the list look like it covered more than it did.
     *
     * @var array<class-string, array<int, class-string>>
     */
    private const array LISTEN = [
        Registered::class => [
            SendEmailVerificationNotification::class,
            SendWelcomeEmail::class,
        ],
        NewTournamentPublished::class => [
            SendPublishedTournamentNotification::class,
        ],
        UserUnregisteredFromTournament::class => [
            UserUnregisteredToTournamentToTournament::class,
        ],
        SubscriptionCreated::class => [
            SendSubscriptionConfirmationEmail::class,
        ],
        MeetingCreated::class => [
            NotifyParticipantsOfMeeting::class,
        ],
        TeamCreated::class => [
            SendTeamCreatedNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        foreach (self::LISTEN as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }
    }
}
