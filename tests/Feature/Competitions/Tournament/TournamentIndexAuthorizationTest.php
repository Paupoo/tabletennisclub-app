<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use Livewire\Livewire;

// ── Helpers ───────────────────────────────────────────────────────────────────

function indexAs(User $user)
{
    return Livewire::actingAs($user)
        ->test('pages::club-events.tournaments.index');
}

// ── Draft visibility ──────────────────────────────────────────────────────────

describe('draft tournament visibility', function (): void {

    it('hides draft tournaments from regular members', function (): void {
        $member = User::factory()->create();
        $draft = Tournament::factory()->create(['status' => TournamentStatusEnum::DRAFT]);
        $published = Tournament::factory()->create(['status' => TournamentStatusEnum::PUBLISHED]);

        indexAs($member)
            ->assertDontSee($draft->name)
            ->assertSee($published->name);
    });

    it('shows draft tournaments to admins', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $draft = Tournament::factory()->create(['status' => TournamentStatusEnum::DRAFT]);

        indexAs($admin)->assertSee($draft->name);
    });

    it('shows draft tournaments to committee members', function (): void {
        $committee = User::factory()->isCommitteeMember()->create();
        $draft = Tournament::factory()->create(['status' => TournamentStatusEnum::DRAFT]);

        indexAs($committee)->assertSee($draft->name);
    });

})->group('Tournament', 'Authorization');

// ── Draft filter isolation ────────────────────────────────────────────────────

describe('draft status filter isolation', function (): void {

    it('hides draft tournaments from regular members even when filtering by draft status', function (): void {
        $member = User::factory()->create();
        $draft = Tournament::factory()->create(['status' => TournamentStatusEnum::DRAFT]);

        indexAs($member)
            ->set('status', TournamentStatusEnum::DRAFT->value)
            ->assertDontSee($draft->name);
    });

    it('shows draft tournaments to admins when filtering by draft status', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $draft = Tournament::factory()->create(['status' => TournamentStatusEnum::DRAFT]);

        indexAs($admin)
            ->set('status', TournamentStatusEnum::DRAFT->value)
            ->assertSee($draft->name);
    });

})->group('Tournament', 'Authorization');

// ── canManage flag ────────────────────────────────────────────────────────────

describe('canManage computed', function (): void {

    it('is false for regular members', function (): void {
        $member = User::factory()->create();

        indexAs($member)->assertSet('canManage', false);
    });

    it('is true for admins', function (): void {
        $admin = User::factory()->isAdmin()->create();

        indexAs($admin)->assertSet('canManage', true);
    });

    it('is true for committee members', function (): void {
        $committee = User::factory()->isCommitteeMember()->create();

        indexAs($committee)->assertSet('canManage', true);
    });

})->group('Tournament', 'Authorization');
