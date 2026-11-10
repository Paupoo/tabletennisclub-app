<?php

declare(strict_types=1);

use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\TournamentPhaseEnum;
use App\Domains\Shared\Enums\TournamentStatusEnum;

/*
 * La fiche d'un tournoi range ses écrans en quatre phases. Le statut en choisit
 * une, sauf « inscriptions closes » : avant le jour du tournoi on compose les
 * paires, le jour même on fait l'appel.
 */

beforeEach(function (): void {
    $this->travelTo('2026-11-20 09:00');
});

it('lands on the phase the status calls for', function (TournamentStatusEnum $status, ?TournamentPhaseEnum $phase): void {
    $tournament = Tournament::factory()->make(['status' => $status, 'start_date' => '2026-11-29 13:30']);

    expect(TournamentPhaseEnum::activeFor($tournament))->toBe($phase);
})->with([
    [TournamentStatusEnum::DRAFT, TournamentPhaseEnum::PREPARATION],
    [TournamentStatusEnum::LOCKED, TournamentPhaseEnum::PREPARATION],
    [TournamentStatusEnum::PUBLISHED, TournamentPhaseEnum::REGISTRATIONS],
    [TournamentStatusEnum::SETUP, TournamentPhaseEnum::REGISTRATIONS],
    [TournamentStatusEnum::PENDING, TournamentPhaseEnum::MATCH_DAY],
    [TournamentStatusEnum::CLOSED, TournamentPhaseEnum::REPORT],
    [TournamentStatusEnum::CANCELLED, null],
]);

it('moves a closed registration to the match day on the day itself', function (): void {
    $tournament = Tournament::factory()->make(['status' => TournamentStatusEnum::SETUP, 'start_date' => '2026-11-20 13:30']);

    expect(TournamentPhaseEnum::activeFor($tournament))->toBe(TournamentPhaseEnum::MATCH_DAY);
});

it('opens a phase once the tournament has reached it', function (TournamentStatusEnum $status, array $open): void {
    $tournament = Tournament::factory()->make(['status' => $status]);

    $opened = array_values(array_filter(
        TournamentPhaseEnum::inOrder(),
        fn (TournamentPhaseEnum $phase): bool => $phase->isOpenFor($tournament),
    ));

    expect($opened)->toBe($open);
})->with([
    'draft' => [TournamentStatusEnum::DRAFT, [TournamentPhaseEnum::PREPARATION]],
    'locked' => [TournamentStatusEnum::LOCKED, [TournamentPhaseEnum::PREPARATION]],
    'published' => [TournamentStatusEnum::PUBLISHED, [TournamentPhaseEnum::PREPARATION, TournamentPhaseEnum::REGISTRATIONS]],
    'setup' => [TournamentStatusEnum::SETUP, [TournamentPhaseEnum::PREPARATION, TournamentPhaseEnum::REGISTRATIONS, TournamentPhaseEnum::MATCH_DAY]],
    'pending' => [TournamentStatusEnum::PENDING, TournamentPhaseEnum::inOrder()],
    'closed' => [TournamentStatusEnum::CLOSED, TournamentPhaseEnum::inOrder()],
]);

it('says when a phase not reached yet opens', function (TournamentPhaseEnum $phase, string $sentence): void {
    expect($phase->opensWhen())->toBe(__($sentence));
})->with([
    [TournamentPhaseEnum::REGISTRATIONS, 'Registrations are not open yet.'],
    [TournamentPhaseEnum::MATCH_DAY, 'The match day opens once registrations are closed.'],
    [TournamentPhaseEnum::REPORT, 'The report opens once the tournament is launched.'],
]);

it('sends the match day and the report to the control room once launched', function (): void {
    $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::PENDING]);

    expect(TournamentPhaseEnum::MATCH_DAY->urlFor($tournament))->toBe(route('admin.tournaments.live-center', $tournament))
        ->and(TournamentPhaseEnum::REPORT->urlFor($tournament))->toBe(route('admin.tournaments.live-center', [$tournament, 'activeTab' => 'closure']))
        ->and(TournamentPhaseEnum::REGISTRATIONS->urlFor($tournament))->toBe(route('admin.tournaments.wizard.edit', [$tournament, 'step' => '5']));
});

it('keeps the match day on the preparation page before the launch', function (): void {
    $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::SETUP]);

    expect(TournamentPhaseEnum::MATCH_DAY->urlFor($tournament))->toBe(route('admin.tournaments.wizard.edit', [$tournament, 'step' => '6']));
});

it('lists the phases in the order a tournament goes through them', function (): void {
    expect(array_map(fn (TournamentPhaseEnum $phase): string => $phase->label(), TournamentPhaseEnum::inOrder()))
        ->toBe([__('Preparation'), __('Registrations'), __('Tournament day'), __('Wrap-up')]);
});
