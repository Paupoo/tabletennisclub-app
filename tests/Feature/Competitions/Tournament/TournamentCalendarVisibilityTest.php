<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubAdmin\Users\Services\UserCalendarService;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\TournamentStatusEnum;

pest()->group('tournaments');

/*
 * Le calendrier listait les tournois avec registrationsOpen(), c'est-à-dire le
 * seul statut « inscriptions ouvertes ». Fermer les inscriptions faisait donc
 * disparaître le tournoi de l'agenda du club — et le lancer aussi, alors que
 * c'est l'événement le plus présent que le club ait ce jour-là.
 */
it('keeps a tournament on the calendar once its registrations are closed', function (TournamentStatusEnum $status, bool $listed): void {
    $member = User::factory()->create();
    $tournament = Tournament::factory()->create([
        'name' => 'Tournoi des crêpes',
        'status' => $status,
        'start_date' => now()->addWeek()->startOfDay(),
        'start_time' => '10:00:00',
        'end_date' => null,
    ]);
    $tournament->users()->attach($member->id, ['registration_status' => 'confirmed']);

    $events = app(UserCalendarService::class)->eventsFor(
        $member,
        showAllEvents: true,
        from: now()->startOfDay(),
        to: now()->addMonth(),
    );

    expect($events->contains('title', 'Tournoi des crêpes'))->toBe($listed);
})->with([
    'open for registrations' => [TournamentStatusEnum::PUBLISHED, true],
    'registrations closed' => [TournamentStatusEnum::SETUP, true],
    'being played' => [TournamentStatusEnum::PENDING, true],
    'over' => [TournamentStatusEnum::CLOSED, true],
    // Rien n'a été annoncé, ou plus rien n'aura lieu.
    'still a draft' => [TournamentStatusEnum::DRAFT, false],
    'ready to open' => [TournamentStatusEnum::LOCKED, false],
    'cancelled' => [TournamentStatusEnum::CANCELLED, false],
]);
