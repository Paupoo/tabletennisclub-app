<?php

declare(strict_types=1);

use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Tournament\Tournament;

// ── Wizard route access ───────────────────────────────────────────────────────

describe('wizard route authorization', function () {

    it('returns 403 for regular members on the create wizard', function () {
        $member = User::factory()->create(['is_admin' => false, 'is_committee_member' => false]);

        $this->actingAs($member)
            ->get(route('admin.tournaments.wizard'))
            ->assertForbidden();
    });

    it('returns 403 for regular members on the edit wizard', function () {
        $member = User::factory()->create(['is_admin' => false, 'is_committee_member' => false]);
        $tournament = Tournament::factory()->create();

        $this->actingAs($member)
            ->get(route('admin.tournaments.wizard.edit', $tournament))
            ->assertForbidden();
    });

    it('allows admins to access the create wizard', function () {
        $admin = User::factory()->isAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.tournaments.wizard'))
            ->assertSuccessful();
    });

    it('allows admins to access the edit wizard', function () {
        $admin = User::factory()->isAdmin()->create();
        $tournament = Tournament::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.tournaments.wizard.edit', $tournament))
            ->assertSuccessful();
    });

    it('allows committee members to access the create wizard', function () {
        $committee = User::factory()->isCommitteeMember()->create();

        $this->actingAs($committee)
            ->get(route('admin.tournaments.wizard'))
            ->assertSuccessful();
    });

    it('redirects unauthenticated users to login', function () {
        $this->get(route('admin.tournaments.wizard'))
            ->assertRedirect(route('login'));
    });

})->group('Tournament', 'Authorization');
