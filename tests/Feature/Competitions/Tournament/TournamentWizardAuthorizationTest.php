<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\Role;

// ── Wizard route access ───────────────────────────────────────────────────────

describe('wizard route authorization', function (): void {

    it('returns 403 for regular members on the create wizard', function (): void {
        $member = User::factory()->create();

        $this->actingAs($member)
            ->get(route('admin.tournaments.wizard'))
            ->assertForbidden();
    });

    it('returns 403 for regular members on the edit wizard', function (): void {
        $member = User::factory()->create();
        $tournament = Tournament::factory()->create();

        $this->actingAs($member)
            ->get(route('admin.tournaments.wizard.edit', $tournament))
            ->assertForbidden();
    });

    it('allows admins to access the create wizard', function (): void {
        $admin = User::factory()->isAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.tournaments.wizard'))
            ->assertSuccessful();
    });

    it('allows admins to access the edit wizard', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $tournament = Tournament::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.tournaments.wizard.edit', $tournament))
            ->assertSuccessful();
    });

    it('allows committee members to access the create wizard', function (): void {
        $committee = User::factory()->isCommitteeMember()->withRole(Role::TOURNAMENTS)->create();

        $this->actingAs($committee)
            ->get(route('admin.tournaments.wizard'))
            ->assertSuccessful();
    });

    it('redirects unauthenticated users to login', function (): void {
        $this->get(route('admin.tournaments.wizard'))
            ->assertRedirect(route('login'));
    });

})->group('Tournament', 'Authorization');
