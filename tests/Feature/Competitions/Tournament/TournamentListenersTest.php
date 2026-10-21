<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Notifications\UserUnregisteredFromTournament as UserUnregisteredFromTournamentNotification;
use App\Domains\Shared\Events\Tournament\UserUnregisteredFromTournament;
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
