<?php

declare(strict_types=1);

use App\Enums\TournamentStatusEnum;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Tournament\Tournament;
use Livewire\Livewire;

// ── Helpers ───────────────────────────────────────────────────────────────────

function indexAs(User $user)
{
    return Livewire::actingAs($user)
        ->test('pages::club-events.tournaments.index');
}

// ── Draft visibility ──────────────────────────────────────────────────────────

describe('draft tournament visibility', function () {

    it('hides draft tournaments from regular members', function () {
        $member = User::factory()->create(['is_admin' => false, 'is_committee_member' => false]);
        $draft = Tournament::factory()->create(['status' => TournamentStatusEnum::DRAFT]);
        $published = Tournament::factory()->create(['status' => TournamentStatusEnum::PUBLISHED]);

        $component = indexAs($member);

        expect($component->get('tournaments')->pluck('id'))
            ->not->toContain($draft->id)
            ->toContain($published->id);
    });

    it('shows draft tournaments to admins', function () {
        $admin = User::factory()->isAdmin()->create();
        $draft = Tournament::factory()->create(['status' => TournamentStatusEnum::DRAFT]);

        expect(indexAs($admin)->get('tournaments')->pluck('id'))
            ->toContain($draft->id);
    });

    it('shows draft tournaments to committee members', function () {
        $committee = User::factory()->isCommitteeMember()->create();
        $draft = Tournament::factory()->create(['status' => TournamentStatusEnum::DRAFT]);

        expect(indexAs($committee)->get('tournaments')->pluck('id'))
            ->toContain($draft->id);
    });

})->group('Tournament', 'Authorization');

// ── Draft count in tabs ───────────────────────────────────────────────────────

describe('draft tab count', function () {

    it('does not expose draft count to regular members', function () {
        $member = User::factory()->create(['is_admin' => false, 'is_committee_member' => false]);
        Tournament::factory()->create(['status' => TournamentStatusEnum::DRAFT]);

        $counts = indexAs($member)->get('counts');

        expect($counts)->not->toHaveKey('draft');
    });

    it('exposes draft count to admins', function () {
        $admin = User::factory()->isAdmin()->create();
        Tournament::factory()->create(['status' => TournamentStatusEnum::DRAFT]);

        $counts = indexAs($admin)->get('counts');

        expect($counts)->toHaveKey('draft')
            ->and($counts['draft'])->toBeGreaterThanOrEqual(1);
    });

})->group('Tournament', 'Authorization');

// ── canManage flag ────────────────────────────────────────────────────────────

describe('canManage computed', function () {

    it('is false for regular members', function () {
        $member = User::factory()->create(['is_admin' => false, 'is_committee_member' => false]);

        indexAs($member)->assertSet('canManage', false);
    });

    it('is true for admins', function () {
        $admin = User::factory()->isAdmin()->create();

        indexAs($admin)->assertSet('canManage', true);
    });

    it('is true for committee members', function () {
        $committee = User::factory()->isCommitteeMember()->create();

        indexAs($committee)->assertSet('canManage', true);
    });

})->group('Tournament', 'Authorization');
