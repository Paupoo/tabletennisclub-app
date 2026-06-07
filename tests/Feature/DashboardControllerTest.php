<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\CommitteeRolesEnum;

describe('DashboardController', function (): void {

    it('redirects guests to login', function (): void {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    });

    it('shows all groups to an admin user', function (): void {
        $admin = User::factory()->isAdmin()->create();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('showSecretary', true)
            ->assertViewHas('showTreasurer', true)
            ->assertViewHas('showCaptain', true)
            ->assertViewHas('showCommittee', true);
    });

    it('shows only secretary group to a secretary user', function (): void {
        $secretary = User::factory()->isCommitteeMember()->create([
            'committee_role' => CommitteeRolesEnum::SECRETARY,
        ]);

        $response = $this->actingAs($secretary)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('showSecretary', true)
            ->assertViewHas('showTreasurer', false)
            ->assertViewHas('showCaptain', false);

        $response->assertSee(__('Secretary'));
        $response->assertDontSee(__('Treasurer'));
    });

    it('shows only treasurer group to a treasurer user', function (): void {
        $treasurer = User::factory()->isCommitteeMember()->create([
            'committee_role' => CommitteeRolesEnum::TREASURER,
        ]);

        $this->actingAs($treasurer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('showTreasurer', true)
            ->assertViewHas('showSecretary', false)
            ->assertViewHas('showCaptain', false)
            ->assertViewHas('showCommittee', false);
    });

    it('shows secretary and committee groups to a president', function (): void {
        $president = User::factory()->isCommitteeMember()->create([
            'committee_role' => CommitteeRolesEnum::PRESIDENT,
        ]);

        $this->actingAs($president)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('showSecretary', true)
            ->assertViewHas('showCommittee', true)
            ->assertViewHas('showTreasurer', false);
    });

    it('shows unpaid members alert when there are unpaid active members', function (): void {
        $admin = User::factory()->isAdmin()->create();
        User::factory()->create(['has_paid' => false, 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('cotisation');
    });

    it('shows no alerts when everything is fine', function (): void {
        $admin = User::factory()->isAdmin()->create(['has_paid' => true]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('alerts', []);
    });

    it('returns recent activity feed', function (): void {
        $admin = User::factory()->isAdmin()->create();

        $response = $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk();

        expect($response->viewData('recentActivity'))->toBeArray();
    });

});
