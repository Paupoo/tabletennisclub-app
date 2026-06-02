<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Notifications\NewTournamentPublishedNotification;
use App\Domains\Competitions\Tournament\Notifications\UserUnregisteredFromTournament as UserUnregisteredFromTournamentNotification;
use App\Domains\Shared\Events\Tournament\NewTournamentPublished;
use App\Domains\Shared\Events\Tournament\UserUnregisteredFromTournament;
use App\Listeners\Tournament\SendPublishedTournamentNotification;
use App\Listeners\Tournament\UserUnregisteredToTournamentToTournament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

// All listeners implement ShouldQueue. We call handle() directly to bypass the
// queue and assert the notification is sent synchronously.

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
