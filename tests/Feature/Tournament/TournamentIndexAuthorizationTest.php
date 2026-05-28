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

        indexAs($member)
            ->assertDontSee($draft->name)
            ->assertSee($published->name);
    });

    it('shows draft tournaments to admins', function () {
        $admin = User::factory()->isAdmin()->create();
        $draft = Tournament::factory()->create(['status' => TournamentStatusEnum::DRAFT]);

        indexAs($admin)->assertSee($draft->name);
    });

    it('shows draft tournaments to committee members', function () {
        $committee = User::factory()->isCommitteeMember()->create();
        $draft = Tournament::factory()->create(['status' => TournamentStatusEnum::DRAFT]);

        indexAs($committee)->assertSee($draft->name);
    });

})->group('Tournament', 'Authorization');

// ── Draft filter isolation ────────────────────────────────────────────────────

describe('draft status filter isolation', function () {

    it('hides draft tournaments from regular members even when filtering by draft status', function () {
        $member = User::factory()->create(['is_admin' => false, 'is_committee_member' => false]);
        $draft = Tournament::factory()->create(['status' => TournamentStatusEnum::DRAFT]);

        indexAs($member)
            ->set('status', TournamentStatusEnum::DRAFT->value)
            ->assertDontSee($draft->name);
    });

    it('shows draft tournaments to admins when filtering by draft status', function () {
        $admin = User::factory()->isAdmin()->create();
        $draft = Tournament::factory()->create(['status' => TournamentStatusEnum::DRAFT]);

        indexAs($admin)
            ->set('status', TournamentStatusEnum::DRAFT->value)
            ->assertSee($draft->name);
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
