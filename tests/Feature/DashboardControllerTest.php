<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Role;
use App\Domains\Trainings\Models\Training;

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

    it('shows only treasurer group to whoever holds the treasury duty', function (): void {
        // The title alone no longer opens the section — the délégation does.
        $treasurer = User::factory()->isCommitteeMember()->withRole(Role::TREASURY)->create([
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

    it('shows a new messages alert for the contacts sitting in the inbox', function (): void {
        $admin = User::factory()->isAdmin()->create();
        Contact::factory()->count(2)->create(['status' => 'new']);
        Contact::factory()->create(['status' => 'processed']);
        Contact::factory()->create(['status' => 'rejected']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('2 nouveaux messages');
    });

    it('singularises the new messages alert for a lone contact', function (): void {
        $admin = User::factory()->isAdmin()->create();
        Contact::factory()->create(['status' => 'new']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('1 nouveau message');
    });

    it('hides the new messages alert when every contact has been handled', function (): void {
        $admin = User::factory()->isAdmin()->create();
        Contact::factory()->create(['status' => 'processed']);
        Contact::factory()->create(['status' => 'rejected']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('nouveau message');
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

    it('gives an administrator every agenda block', function (): void {
        Training::factory()->count(2)->create();
        Contact::factory()->count(2)->create();

        $response = $this->actingAs(User::factory()->isAdmin()->create())
            ->get(route('dashboard'))
            ->assertOk();

        $keys = array_map(fn ($block): string => $block->key, $response->viewData('agendaBlocks'));

        expect($keys)->toContain('trainings')->toContain('messages')->toContain('new_members');
    });

    it('hides the management blocks from a member without a role', function (): void {
        Training::factory()->count(2)->create();
        Contact::factory()->count(2)->create();

        $response = $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk();

        $keys = array_map(fn ($block): string => $block->key, $response->viewData('agendaBlocks'));

        expect($keys)->toContain('trainings')
            ->not->toContain('messages')
            ->not->toContain('new_members');
    });

    it('offers a member no link towards a screen they would be refused', function (): void {
        Training::factory()->count(2)->create();

        $response = $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk();

        $routes = array_map(fn ($block): ?string => $block->seeAllRoute, $response->viewData('agendaBlocks'));

        expect(array_filter($routes))->toBeEmpty();
        $response->assertDontSee(route('admin.trainings.index'));
    });

    it('lays the agenda blocks two-up between the phone and the sidebar', function (): void {
        Training::factory()->count(2)->create();

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('sm:grid-cols-2 lg:grid-cols-1', false);
    });

    it('leaves only Mon espace open below the sidebar breakpoint', function (): void {
        $response = $this->actingAs(User::factory()->isAdmin()->create())
            ->get(route('dashboard'))
            ->assertOk();

        // Read off the sections themselves rather than counted across the page:
        // the layout runs a matchMedia of its own for the colour scheme, and the
        // breakpoint shows up in the stylesheet too.
        preg_match_all('/<section x-data="([^"]*)"/', (string) $response->getContent(), $sections);
        $collapsed = array_filter(
            $sections[1],
            fn (string $state): bool => str_contains($state, 'min-width: 1024px'),
        );

        // Four of the five accordions an administrator gets: Mon espace stays
        // open, so the page still lands on something.
        expect($sections[1])->toHaveCount(5)
            ->and($collapsed)->toHaveCount(4)
            ->and($sections[1][0])->not->toContain('min-width: 1024px');
    });

});
