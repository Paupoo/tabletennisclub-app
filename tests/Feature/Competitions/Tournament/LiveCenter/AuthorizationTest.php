<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Pool;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentMatch;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use Livewire\Livewire;

// ── Helpers ───────────────────────────────────────────────────────────────────

function authTournament(): Tournament
{
    return Tournament::factory()->create([
        'status' => TournamentStatusEnum::PENDING,
        'sets_to_win' => 3,
        'has_handicap_points' => false,
        'deuce_enabled' => false,
    ]);
}

function authMatch(Tournament $tournament): TournamentMatch
{
    $p1 = User::factory()->create();
    $p2 = User::factory()->create();
    $pool = Pool::factory()->for($tournament)->create(['name' => 'A']);
    $pool->users()->attach([$p1->id, $p2->id]);

    return TournamentMatch::create([
        'tournament_id' => $tournament->id,
        'pool_id' => $pool->id,
        'player1_id' => $p1->id,
        'player2_id' => $p2->id,
        'player1_handicap_points' => 0,
        'player2_handicap_points' => 0,
        'status' => 'in_progress',
        'match_order' => 1,
    ]);
}

function liveCenterAs(User $user, Tournament $tournament)
{
    return Livewire::actingAs($user)
        ->test('pages::club-events.tournaments.live-center', ['tournament' => $tournament]);
}

// ── canManageTournament ───────────────────────────────────────────────────────

describe('canManageTournament', function (): void {

    it('is true for admins', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $tournament = authTournament();

        liveCenterAs($admin, $tournament)
            ->assertSet('canManageTournament', true);
    });

    it('is true for committee members', function (): void {
        $committee = User::factory()->isCommitteeMember()->create();
        $tournament = authTournament();

        liveCenterAs($committee, $tournament)
            ->assertSet('canManageTournament', true);
    });

    it('is false for regular members', function (): void {
        $member = User::factory()->create(['is_admin' => false, 'is_committee_member' => false]);
        $tournament = authTournament();

        liveCenterAs($member, $tournament)
            ->assertSet('canManageTournament', false);
    });

})->group('Tournament', 'Authorization');

// ── Action guards: openScoreEntry ─────────────────────────────────────────────

describe('openScoreEntry authorization', function (): void {

    it('allows committee members to open the score drawer', function (): void {
        $committee = User::factory()->isCommitteeMember()->create();
        $tournament = authTournament();
        $match = authMatch($tournament);

        liveCenterAs($committee, $tournament)
            ->call('openScoreEntry', $match->id)
            ->assertSet('scoreDrawer', true);
    });

    it('forbids regular members from opening the score drawer', function (): void {
        $member = User::factory()->create(['is_admin' => false, 'is_committee_member' => false]);
        $tournament = authTournament();
        $match = authMatch($tournament);

        liveCenterAs($member, $tournament)
            ->call('openScoreEntry', $match->id)
            ->assertForbidden();
    });

})->group('Tournament', 'Authorization');

// ── Action guards: openLaunchDrawer ──────────────────────────────────────────

describe('openLaunchDrawer authorization', function (): void {

    it('allows admins to open the launch drawer', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $tournament = authTournament();

        liveCenterAs($admin, $tournament)
            ->call('openLaunchDrawer', 1)
            ->assertSet('launchDrawer', true);
    });

    it('forbids regular members from opening the launch drawer', function (): void {
        $member = User::factory()->create(['is_admin' => false, 'is_committee_member' => false]);
        $tournament = authTournament();

        liveCenterAs($member, $tournament)
            ->call('openLaunchDrawer', 1)
            ->assertForbidden();
    });

})->group('Tournament', 'Authorization');

// ── Action guards: submitScore ────────────────────────────────────────────────

describe('submitScore authorization', function (): void {

    it('forbids regular members from submitting a score', function (): void {
        $member = User::factory()->create(['is_admin' => false, 'is_committee_member' => false]);
        $tournament = authTournament();

        liveCenterAs($member, $tournament)
            ->call('submitScore')
            ->assertForbidden();
    });

})->group('Tournament', 'Authorization');

// ── Action guards: saveDraft ──────────────────────────────────────────────────

describe('saveDraft authorization', function (): void {

    it('forbids regular members from saving a draft', function (): void {
        $member = User::factory()->create(['is_admin' => false, 'is_committee_member' => false]);
        $tournament = authTournament();

        liveCenterAs($member, $tournament)
            ->call('saveDraft')
            ->assertForbidden();
    });

})->group('Tournament', 'Authorization');

// ── Action guards: startMatch ─────────────────────────────────────────────────

describe('startMatch authorization', function (): void {

    it('forbids regular members from starting a match', function (): void {
        $member = User::factory()->create(['is_admin' => false, 'is_committee_member' => false]);
        $tournament = authTournament();
        $match = authMatch($tournament);

        liveCenterAs($member, $tournament)
            ->call('startMatch', $match->id)
            ->assertForbidden();
    });

})->group('Tournament', 'Authorization');

// ── Action guards: generateBracket ───────────────────────────────────────────

describe('generateBracket authorization', function (): void {

    it('forbids regular members from generating the bracket', function (): void {
        $member = User::factory()->create(['is_admin' => false, 'is_committee_member' => false]);
        $tournament = authTournament();

        liveCenterAs($member, $tournament)
            ->call('generateBracket')
            ->assertForbidden();
    });

})->group('Tournament', 'Authorization');

// ── Action guards: closeTournament ────────────────────────────────────────────

describe('closeTournament authorization', function (): void {

    it('forbids regular members from closing the tournament', function (): void {
        $member = User::factory()->create(['is_admin' => false, 'is_committee_member' => false]);
        $tournament = authTournament();

        liveCenterAs($member, $tournament)
            ->call('closeTournament')
            ->assertForbidden();
    });

})->group('Tournament', 'Authorization');
