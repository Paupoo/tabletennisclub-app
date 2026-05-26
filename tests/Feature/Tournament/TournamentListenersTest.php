<?php

declare(strict_types=1);

use App\Events\Tournament\NewTournamentPublished;
use App\Events\Tournament\UserRegisteredToTournament;
use App\Events\Tournament\UserUnregisteredFromTournament;
use App\Listeners\Tournament\SendPublishedTournamentNotification;
use App\Listeners\Tournament\UserRegisteredToTournamentToTournament;
use App\Listeners\Tournament\UserUnregisteredToTournamentToTournament;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Tournament\Tournament;
use App\Notifications\Tournament\NewTournamentPublishedNotification;
use App\Notifications\Tournament\UserRegisteredToTournament as UserRegisteredToTournamentNotification;
use App\Notifications\Tournament\UserUnregisteredFromTournament as UserUnregisteredFromTournamentNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

// All listeners implement ShouldQueue. We call handle() directly to bypass the
// queue and assert the notification is sent synchronously.

describe('UserRegisteredToTournamentToTournament listener', function (): void {
    it('sends a registration notification to the user', function (): void {
        Notification::fake();

        $user = User::factory()->create();
        $tournament = Tournament::factory()->create();
        $event = new UserRegisteredToTournament($tournament, $user);

        $listener = new UserRegisteredToTournamentToTournament($tournament, $user);
        $listener->handle($event);

        Notification::assertSentTo($user, UserRegisteredToTournamentNotification::class);
    });
});

describe('UserUnregisteredToTournamentToTournament listener', function (): void {
    it('sends an unregistration notification to the user', function (): void {
        Notification::fake();

        $user = User::factory()->create();
        $tournament = Tournament::factory()->create();
        $event = new UserUnregisteredFromTournament($tournament, $user);

        $listener = new UserUnregisteredToTournamentToTournament($tournament, $user);
        $listener->handle($event);

        Notification::assertSentTo($user, UserUnregisteredFromTournamentNotification::class);
    });
});

describe('SendPublishedTournamentNotification listener', function (): void {
    it('sends a NewTournamentPublished notification to all users', function (): void {
        Notification::fake();

        $users = User::factory()->count(3)->create();
        $tournament = Tournament::factory()->create();
        $event = new NewTournamentPublished($tournament);

        $listener = new SendPublishedTournamentNotification;
        $listener->handle($event);

        foreach ($users as $user) {
            Notification::assertSentTo($user, NewTournamentPublishedNotification::class, function (NewTournamentPublishedNotification $notification) use ($user, $tournament): bool {
                $mail = $notification->toMail($user);
                $actionUrl = $mail->actionUrl;

                return str_contains($actionUrl, "/tournament/{$tournament->id}/join/{$user->id}")
                    && str_contains($actionUrl, 'signature=')
                    && ! str_contains($actionUrl, 'clubAdmin');
            });
        }
    });
});
