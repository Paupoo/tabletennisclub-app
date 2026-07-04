<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
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
        $season = Season::factory()->create(['is_active' => true]);
        $admin = User::factory()->isAdmin()->create();
        Subscription::factory()->create(['user_id' => $admin->id, 'season_id' => $season->id, 'status' => 'paid']);
        $user = User::factory()->create();
        Subscription::factory()->create(['user_id' => $user->id, 'season_id' => $season->id, 'status' => 'confirmed']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('cotisation');
    });

    it('shows incomplete profile alert when active members have missing mandatory fields', function (): void {
        $admin = User::factory()->isAdmin()->create();
        User::factory()->create(['phone_number' => null]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('profil');
    });

    it('redirects a member with an incomplete profile to the onboarding wizard', function (): void {
        $user = User::factory()->create([
            'phone_number' => null,
            'street' => null,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.user.onboarding'));
    });

    it('shows personal affiliation alert when user has no subscription for current season', function (): void {
        Season::factory()->create(['is_active' => true]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('affilié');
    });

    it('shows personal payment alert when user has pending payments', function (): void {
        $user = User::factory()->isAdmin()->create();

        $subscription = Subscription::factory()->create(['user_id' => $user->id]);
        Payment::factory()->create([
            'status' => 'pending',
            'payable_type' => Subscription::class,
            'payable_id' => $subscription->id,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('votre nom');
    });

    it('shows member tiles for all users', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();

        expect($response->viewData('memberTiles'))->toBeArray()->not->toBeEmpty();
    });

    it('adds interclub tiles for competitors', function (): void {
        Season::factory()->create(['is_active' => true]);
        $user = User::factory()->isCompetitor()->create();

        $response = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();

        $labels = array_column($response->viewData('memberTiles'), 'label');
        expect($labels)->toContain('Disponibilités')->toContain('Mes matchs');
    });

    it('returns recent activity feed', function (): void {
        $admin = User::factory()->isAdmin()->create();

        $response = $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk();

        expect($response->viewData('recentActivity'))->toBeArray();
    });

});
