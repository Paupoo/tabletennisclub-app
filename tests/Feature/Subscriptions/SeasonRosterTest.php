<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

const ROSTER = 'pages::club-admin.subscriptions.roster';

beforeEach(function (): void {
    $this->season = makeActiveSeason();

    // Editing the roster is the `membres` duty; the title alone no longer grants it.
    $this->manager = User::factory()->isCommitteeMember()->withRole(Role::MEMBERS)->create([
        'committee_role' => CommitteeRolesEnum::SECRETARY,
    ]);

    $this->viewer = User::factory()->isCommitteeMember()->create([
        'committee_role' => CommitteeRolesEnum::TREASURER,
    ]);
});

/**
 * @param  array<string, mixed>  $userAttributes
 * @param  array<string, mixed>  $subscriptionAttributes
 */
function rosterMember(Season $season, array $userAttributes = [], array $subscriptionAttributes = []): Subscription
{
    $member = User::factory()->create($userAttributes);

    return Subscription::factory()->create(array_merge([
        'user_id' => $member->id,
        'season_id' => $season->id,
        'status' => 'paid',
    ], $subscriptionAttributes));
}

describe('listing', function (): void {
    it('lists only subscriptions of the active season', function (): void {
        $current = rosterMember($this->season, ['first_name' => 'Alice', 'last_name' => 'Active']);

        $otherSeason = Season::factory()->create([
            'is_active' => false,
            'start_at' => now()->subYears(2)->startOfYear(),
            'end_at' => now()->subYears(2)->endOfYear(),
        ]);
        rosterMember($otherSeason, ['first_name' => 'Olivia', 'last_name' => 'Old']);

        Livewire::actingAs($this->manager)
            ->test(ROSTER)
            ->assertSee('Alice')
            ->assertDontSee('Olivia');
    });

    it('derives the age category from the member birthdate', function (): void {
        rosterMember($this->season, [
            'first_name' => 'Kiddo',
            'last_name' => 'Young',
            'birthdate' => now()->subYears(10),
        ]);
        rosterMember($this->season, [
            'first_name' => 'Grown',
            'last_name' => 'Up',
            'birthdate' => now()->subYears(30),
        ]);

        $rows = Livewire::actingAs($this->manager)
            ->test(ROSTER)
            ->viewData('rows');

        $byName = $rows->keyBy('name');
        expect($byName->get('Kiddo Young')->age_category)->toBe('CHILD');
        expect($byName->get('Grown Up')->age_category)->toBe('ADULT');
    });

    it('shows the member ranking', function (): void {
        rosterMember($this->season, ['first_name' => 'Ranked', 'ranking' => 'B2']);

        Livewire::actingAs($this->manager)
            ->test(ROSTER)
            ->assertSee('B2');
    });
});

describe('active members only', function (): void {
    it('hides members whose subscription is not confirmed', function (string $status): void {
        rosterMember($this->season, ['first_name' => 'Ghost', 'last_name' => 'Member'], ['status' => $status]);

        Livewire::actingAs($this->manager)
            ->test(ROSTER)
            ->assertDontSee('Ghost');
    })->with(['pending', 'cancelled', 'refunded']);

    it('shows members whose subscription is confirmed or paid', function (string $status): void {
        rosterMember($this->season, ['first_name' => 'Real', 'last_name' => 'Member'], ['status' => $status]);

        Livewire::actingAs($this->manager)
            ->test(ROSTER)
            ->assertSee('Real');
    })->with(['confirmed', 'paid']);

    /**
     * Régression #29 : une inscription rejetée puis resoumise créait une
     * seconde ligne, et le membre apparaissait deux fois dans l'effectif.
     */
    it('lists a member once after a rejected registration was resubmitted', function (): void {
        $member = User::factory()->create(['first_name' => 'Aurelien', 'last_name' => 'Resubmitted']);

        Subscription::factory()->create([
            'user_id' => $member->id,
            'season_id' => $this->season->id,
            'status' => 'cancelled',
        ]);

        Subscription::factory()->create([
            'user_id' => $member->id,
            'season_id' => $this->season->id,
            'status' => 'confirmed',
        ]);

        Livewire::actingAs($this->manager)
            ->test(ROSTER)
            ->assertSee('Aurelien')
            ->assertViewHas('rows', fn ($rows): bool => $rows->where('name', 'Aurelien Resubmitted')->count() === 1);
    });
});

describe('filters', function (): void {
    it('filters by competitive', function (): void {
        rosterMember($this->season, ['first_name' => 'Compyes'], ['is_competitive' => true]);
        rosterMember($this->season, ['first_name' => 'Compno'], ['is_competitive' => false]);

        Livewire::actingAs($this->manager)
            ->test(ROSTER)
            ->set('competitive', '1')
            ->assertSee('Compyes')
            ->assertDontSee('Compno');
    });

    it('filters by drivers', function (): void {
        rosterMember($this->season, ['first_name' => 'Driver'], ['can_drive' => true, 'seats_available' => 4]);
        rosterMember($this->season, ['first_name' => 'NoDriver'], ['can_drive' => false]);

        Livewire::actingAs($this->manager)
            ->test(ROSTER)
            ->set('canDrive', '1')
            ->assertSee('Driver')
            ->assertDontSee('NoDriver');
    });

    it('filters by captain volunteers', function (): void {
        rosterMember($this->season, ['first_name' => 'Captain'], ['wants_to_be_captain' => true]);
        rosterMember($this->season, ['first_name' => 'NoCaptain'], ['wants_to_be_captain' => false]);

        Livewire::actingAs($this->manager)
            ->test(ROSTER)
            ->set('wantsToBeCaptain', '1')
            ->assertSee('Captain')
            ->assertDontSee('NoCaptain');
    });

    it('filters by volunteers', function (): void {
        rosterMember($this->season, ['first_name' => 'Helper'], ['volunteer_help' => true]);
        rosterMember($this->season, ['first_name' => 'NoHelper'], ['volunteer_help' => false]);

        Livewire::actingAs($this->manager)
            ->test(ROSTER)
            ->set('volunteerHelp', '1')
            ->assertSee('Helper')
            ->assertDontSee('NoHelper');
    });

    it('filters by directed training', function (): void {
        rosterMember($this->season, ['first_name' => 'Directed'], ['wants_directed_training' => true]);
        rosterMember($this->season, ['first_name' => 'NoDirected'], ['wants_directed_training' => false]);

        Livewire::actingAs($this->manager)
            ->test(ROSTER)
            ->set('wantsDirectedTraining', '1')
            ->assertSee('Directed')
            ->assertDontSee('NoDirected');
    });

    it('filters by age category', function (): void {
        rosterMember($this->season, ['first_name' => 'Childone', 'birthdate' => now()->subYears(9)]);
        rosterMember($this->season, ['first_name' => 'Adultone', 'birthdate' => now()->subYears(40)]);

        Livewire::actingAs($this->manager)
            ->test(ROSTER)
            ->set('ageCategory', 'CHILD')
            ->assertSee('Childone')
            ->assertDontSee('Adultone');
    });
});

describe('sorting', function (): void {
    it('sorts by ranking', function (): void {
        rosterMember($this->season, ['first_name' => 'Aaa', 'ranking' => 'C0']);
        rosterMember($this->season, ['first_name' => 'Bbb', 'ranking' => 'B0']);

        $rows = Livewire::actingAs($this->manager)
            ->test(ROSTER)
            ->set('sortBy', ['column' => 'ranking', 'direction' => 'asc'])
            ->viewData('rows');

        expect($rows->first()->ranking)->toBe('B0');
    });
});

describe('inline edition', function (): void {
    it('lets a manager toggle season attributes and set seats', function (): void {
        $subscription = rosterMember($this->season, [], ['can_drive' => false]);

        Livewire::actingAs($this->manager)
            ->test(ROSTER)
            ->call('updateAttribute', $subscription->id, 'can_drive', true)
            ->call('updateAttribute', $subscription->id, 'wants_to_be_captain', true)
            ->call('updateAttribute', $subscription->id, 'volunteer_help', true)
            ->call('updateAttribute', $subscription->id, 'wants_directed_training', true)
            ->call('updateSeats', $subscription->id, 3);

        $subscription->refresh();
        expect($subscription->can_drive)->toBeTrue();
        expect($subscription->wants_to_be_captain)->toBeTrue();
        expect($subscription->volunteer_help)->toBeTrue();
        expect($subscription->wants_directed_training)->toBeTrue();
        expect($subscription->seats_available)->toBe(3);
    });

    it('clears seats when can_drive is turned off', function (): void {
        $subscription = rosterMember($this->season, [], ['can_drive' => true, 'seats_available' => 4]);

        Livewire::actingAs($this->manager)
            ->test(ROSTER)
            ->call('updateAttribute', $subscription->id, 'can_drive', false);

        $subscription->refresh();
        expect($subscription->can_drive)->toBeFalse();
        expect($subscription->seats_available)->toBeNull();
    });
});

describe('authorization', function (): void {
    it('lets a non-manager committee member view the roster', function (): void {
        rosterMember($this->season, ['first_name' => 'Visible']);

        Livewire::actingAs($this->viewer)
            ->test(ROSTER)
            ->assertOk()
            ->assertSee('Visible');
    });

    it('forbids a non-manager from mutating an attribute', function (): void {
        $subscription = rosterMember($this->season, [], ['can_drive' => false]);

        Livewire::actingAs($this->viewer)
            ->test(ROSTER)
            ->call('updateAttribute', $subscription->id, 'can_drive', true)
            ->assertForbidden();

        expect($subscription->refresh()->can_drive)->toBeFalse();
    });

    it('forbids a non-manager from setting seats', function (): void {
        $subscription = rosterMember($this->season, [], ['can_drive' => true, 'seats_available' => 4]);

        Livewire::actingAs($this->viewer)
            ->test(ROSTER)
            ->call('updateSeats', $subscription->id, 2)
            ->assertForbidden();

        expect($subscription->refresh()->seats_available)->toBe(4);
    });
});
