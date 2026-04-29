<?php

declare(strict_types=1);

use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Season;
use App\Models\ClubEvents\Interclub\Team;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

pest()->group('club-admin', 'users');

const USER_INDEX_COMPONENT = 'pages::club-admin.users.index';

beforeEach(function () {
    // On crée un utilisateur admin pour les tests
    $this->admin = User::factory()->create(['is_admin' => true]);
    actingAs($this->admin);

    Season::factory()->create();
});

describe('rendering and display', function () {
    it('renders successfully', function () {
        Livewire::test(USER_INDEX_COMPONENT)
            ->assertStatus(200);
    });

    it('displays the correct headers', function () {
        Livewire::test(USER_INDEX_COMPONENT)
            ->assertSee(__('Name'))
            ->assertSee(__('Email'))
            ->assertSee(__('Licence'))
            ->assertSee(__('Ranking'));
    });

    it('displays users in the table', function () {
        $users = User::factory()->count(3)->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->assertSee($users[0]->first_name)
            ->assertSee($users[1]->email)
            ->assertSee($users[2]->last_name);
    });

    it('paginates users correctly', function () {
        User::factory()->count(20)->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->assertSee('1') // page 1
            ->assertSee('2'); // page 2
    });

    it('displays mobile view elements', function () {
        $user = User::factory()->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->assertSee($user->email)
            ->assertSee($user->phone_number);
    });
});

describe('search functionality', function () {
    it('filters users by first name', function () {
        $john = User::factory()->create(['first_name' => 'John']);
        $jane = User::factory()->create(['first_name' => 'Jane']);

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('search', 'John')
            ->assertSee($john->first_name)
            ->assertDontSee($jane->first_name);
    });

    it('filters users by last name', function () {
        $smith = User::factory()->create(['last_name' => 'Smith']);
        $doe = User::factory()->create(['last_name' => 'Doe']);

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('search', 'Smith')
            ->assertSee($smith->last_name)
            ->assertDontSee($doe->last_name);
    });

    it('filters users by email', function () {
        $user1 = User::factory()->create(['email' => 'john@example.com']);
        $user2 = User::factory()->create(['email' => 'jane@example.com']);

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('search', 'john@')
            ->assertSee($user1->email)
            ->assertDontSee($user2->email);
    });

    it('resets pagination when searching', function () {
        User::factory()->count(20)->create();
        
        Livewire::test(USER_INDEX_COMPONENT)
            ->call('setPage', 2)
            ->set('search', 'test')
            ->assertcall('setPage', 1);
    });

    it('performs case-insensitive search', function () {
        $user = User::factory()->create(['first_name' => 'John']);

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('search', 'john')
            ->assertSee($user->first_name);
    });
});

describe('licence type filtering', function () {
    it('shows both competitive and recreational users by default', function () {
        $competitive = User::factory()->create(['is_competitor' => true]);
        $recreational = User::factory()->create(['is_competitor' => false]);

        Livewire::test(USER_INDEX_COMPONENT)
            ->assertSee($competitive->email)
            ->assertSee($recreational->email);
    });

    it('filters only competitive users', function () {
        $competitive = User::factory()->create(['is_competitor' => true]);
        $recreational = User::factory()->create(['is_competitor' => false]);

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selectedLicenceType', 'competitive')
            ->assertSee($competitive->email)
            ->assertDontSee($recreational->email);
    });

    it('filters only recreational users', function () {
        $competitive = User::factory()->create(['is_competitor' => true]);
        $recreational = User::factory()->create(['is_competitor' => false]);

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selectedLicenceType', 'recreative')
            ->assertDontSee($competitive->email)
            ->assertSee($recreational->email);
    });

    it('resets pagination when changing licence type', function () {
        User::factory()->count(20)->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->call('setPage', 2)
            ->set('selectedLicenceType', 'competitive')
            ->assertcall('setPage', 1);
    });
});

describe('gender filtering', function () {
    it('filters users by gender', function () {
        $male = User::factory()->create(['gender' => 'male']);
        $female = User::factory()->create(['gender' => 'female']);

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('categories', ['male'])
            ->assertSee($male->email)
            ->assertDontSee($female->email);
    });

    it('filters users by multiple genders', function () {
        $male = User::factory()->create(['gender' => 'male']);
        $female = User::factory()->create(['gender' => 'female']);
        $other = User::factory()->create(['gender' => 'other']);

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('categories', ['male', 'female'])
            ->assertSee($male->email)
            ->assertSee($female->email)
            ->assertDontSee($other->email);
    });

    it('resets pagination when changing categories', function () {
        User::factory()->count(20)->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->call('setPage', 2)
            ->set('categories', ['male'])
            ->assertcall('setPage', 1);
    });
});

describe('active status filtering', function () {
    it('shows all users when onlyActive is false', function () {
        $active = User::factory()->create(['is_active' => true]);
        $inactive = User::factory()->create(['is_active' => false]);

        Livewire::test(USER_INDEX_COMPONENT)
            ->assertSee($active->email)
            ->assertSee($inactive->email);
    });

    it('filters only active users', function () {
        $active = User::factory()->create(['is_active' => true]);
        $inactive = User::factory()->create(['is_active' => false]);

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('onlyActive', true)
            ->assertSee($active->email)
            ->assertDontSee($inactive->email);
    });

    it('resets pagination when toggling onlyActive', function () {
        User::factory()->count(20)->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->call('setPage', 2)
            ->set('onlyActive', true)
            ->assertcall('setPage', 1);
    });
});

describe('team filtering', function () {
    it('filters users by team', function () {
        $team = Team::factory(['name' => 'A'])->create();
        $userInTeam = User::factory()->create();
        $userNotInTeam = User::factory()->create();
        
        $team->members()->attach($userInTeam);

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('team_ids', [$team->id])
            ->assertSee($userInTeam->email)
            ->assertDontSee($userNotInTeam->email);
    });

    it('filters users by multiple teams', function () {
        $team1 = Team::factory(['name' => 'A'])->create();
        $team2 = Team::factory(['name' => 'B'])->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();
        
        $team1->members()->attach($user1);
        $team2->members()->attach($user2);

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('team_ids', [$team1->id, $team2->id])
            ->assertSee($user1->email)
            ->assertSee($user2->email)
            ->assertDontSee($user3->email);
    });
});

describe('filter combination', function () {
    it('combines search and licence type filters', function () {
        $competitiveJohn = User::factory()->create([
            'first_name' => 'John',
            'is_competitor' => true,
        ]);
        $recreationalJohn = User::factory()->create([
            'first_name' => 'John',
            'is_competitor' => false,
        ]);

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('search', 'John')
            ->set('selectedLicenceType', 'competitive')
            ->assertSee($competitiveJohn->email)
            ->assertDontSee($recreationalJohn->email);
    });

    it('combines multiple filters correctly', function () {
        $target = User::factory()->create([
            'first_name' => 'John',
            'is_competitor' => true,
            'gender' => 'male',
            'is_active' => true,
        ]);
        
        $wrong = User::factory()->create([
            'first_name' => 'Jane',
            'is_competitor' => false,
            'gender' => 'female',
            'is_active' => false,
        ]);

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('search', 'John')
            ->set('selectedLicenceType', 'competitive')
            ->set('categories', ['male'])
            ->set('onlyActive', true)
            ->assertSee($target->email)
            ->assertDontSee($wrong->email);
    });
});

describe('active filters count', function () {
    it('counts zero active filters by default', function () {
        Livewire::test(USER_INDEX_COMPONENT)
            ->assertSet('activeFiltersCount', 0);
    });

    it('counts licence type filter', function () {
        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selectedLicenceType', 'competitive')
            ->assertSet('activeFiltersCount', 1);
    });

    it('counts category filters', function () {
        Livewire::test(USER_INDEX_COMPONENT)
            ->set('categories', ['male', 'female'])
            ->assertSet('activeFiltersCount', 2);
    });

    it('counts onlyActive filter', function () {
        Livewire::test(USER_INDEX_COMPONENT)
            ->set('onlyActive', true)
            ->assertSet('activeFiltersCount', 1);
    });

    it('counts all active filters combined', function () {
        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selectedLicenceType', 'competitive')
            ->set('categories', ['male', 'female'])
            ->set('onlyActive', true)
            ->assertSet('activeFiltersCount', 4); // 1 + 2 + 1
    });
});

describe('filter reset', function () {
    it('resets all filters', function () {
        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selectedLicenceType', 'competitive')
            ->set('categories', ['male'])
            ->set('onlyActive', true)
            ->call('resetFilters')
            ->assertSet('selectedLicenceType', 'both')
            ->assertSet('categories', [])
            ->assertSet('onlyActive', false);
    });

    it('resets pagination when resetting filters', function () {
        User::factory()->count(20)->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->call('setPage', 2)
            ->call('resetFilters')
            ->assertcall('setPage', 1);
    });
});

describe('sorting', function () {
    it('sorts by last name ascending by default', function () {
        Livewire::test(USER_INDEX_COMPONENT)
            ->assertSet('sortBy', ['column' => 'last_name', 'direction' => 'asc']);
    });

    it('sorts users correctly', function () {
        $alice = User::factory()->create(['last_name' => 'Alice']);
        $bob = User::factory()->create(['last_name' => 'Bob']);
        $charlie = User::factory()->create(['last_name' => 'Charlie']);

        $component = Livewire::test(USER_INDEX_COMPONENT);
        
        $users = $component->get('users');
        
        expect($users->first()->id)->toBe($alice->id);
        expect($users->last()->id)->toBe($charlie->id);
    });
});

// Actions

describe('user selection', function () {
    it('can select a single user', function () {
        $user = User::factory()->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selected', [$user->id])
            ->assertSet('selected', [$user->id]);
    });

    it('can select multiple users', function () {
        $users = User::factory()->count(3)->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selected', $users->pluck('id')->toArray())
            ->assertCount('selected', 3);
    });

    it('shows bulk action bar when users are selected', function () {
        $user = User::factory()->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selected', [$user->id])
            ->assertSee(__('Add to a team...'))
            ->assertSee(__('Subscribe to...'));
    });
});

describe('single user deletion', function () {
    it('opens delete confirmation modal', function () {
        $user = User::factory()->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->call('confirmDelete', $user->id)
            ->assertSet('userToDelete', $user->id)
            ->assertSet('deleteModal', true);
    });

    it('deletes a user successfully', function () {
        $user = User::factory()->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->call('confirmDelete', $user->id)
            ->call('delete')
            ->assertSet('deleteModal', false)
            ->assertSet('userToDelete', null);

        expect(User::find($user->id))->toBeNull();
    });

    it('shows success message after deletion', function () {
        $user = User::factory()->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->call('confirmDelete', $user->id)
            ->call('delete')
            ->assertDispatched('mary-toast');
    })->skip('not able to test toasts');

    it('does not delete user if modal is cancelled', function () {
        $user = User::factory()->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->call('confirmDelete', $user->id)
            ->set('deleteModal', false);

        expect(User::find($user->id))->not->toBeNull();
    });
});

describe('bulk deletion', function () {
    it('opens bulk delete confirmation modal', function () {
        $users = User::factory()->count(3)->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selected', $users->pluck('id')->toArray())
            ->call('confirmBulkDelete')
            ->assertSet('deleteSelectedModal', true);
    });

    it('deletes multiple users successfully', function () {
        $users = User::factory()->count(3)->create();
        $userIds = $users->pluck('id')->toArray();

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selected', $userIds)
            ->call('deleteSelected')
            ->assertSet('deleteSelectedModal', false)
            ->assertSet('selected', []);

        foreach ($userIds as $id) {
            expect(User::find($id))->toBeNull();
        }
    });

    it('shows success message after bulk deletion', function () {
        $users = User::factory()->count(3)->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selected', $users->pluck('id')->toArray())
            ->call('deleteSelected')
            ->assertDispatched('mary-toast');
    })->skip('not able to test toasts');

    it('keeps non-selected users intact', function () {
        $toDelete = User::factory()->count(2)->create();
        $toKeep = User::factory()->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selected', $toDelete->pluck('id')->toArray())
            ->call('deleteSelected');

        expect(User::find($toKeep->id))->not->toBeNull();
    });
});

describe('bulk add to team', function () {
    it('requires team selection', function () {
        $users = User::factory()->count(2)->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selected', $users->pluck('id')->toArray())
            ->set('team_id', null)
            ->call('bulkAddToTeam');

        // La méthode retourne early si team_id est null
        // Aucun changement ne devrait être fait
    });

    it('resets team_id after adding', function () {
        $team = Team::factory()->create();
        $users = User::factory()->count(2)->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selected', $users->pluck('id')->toArray())
            ->set('team_id', $team->id)
            ->call('bulkAddToTeam')
            ->assertSet('team_id', null);
    });

    it('shows success message', function () {
        $team = Team::factory()->create();
        $users = User::factory()->count(2)->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selected', $users->pluck('id')->toArray())
            ->set('team_id', $team->id)
            ->call('bulkAddToTeam')
            ->assertDispatched('mary-toast');
    })->skip('not able to test toasts');
});

describe('bulk subscription', function () {
    it('requires subscription selection', function () {
        $users = User::factory()->count(2)->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selected', $users->pluck('id')->toArray())
            ->set('subscription_id', null)
            ->call('bulkSubscribe');

        // La méthode retourne early si subscription_id est null
    });

    it('resets subscription_id after subscribing', function () {
        $users = User::factory()->count(2)->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selected', $users->pluck('id')->toArray())
            ->set('subscription_id', 'event-1')
            ->call('bulkSubscribe')
            ->assertSet('subscription_id', null);
    });

    it('shows success message', function () {
        $users = User::factory()->count(2)->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selected', $users->pluck('id')->toArray())
            ->set('subscription_id', 'event-1')
            ->call('bulkSubscribe')
            ->assertDispatched('mary-toast');
    })->skip('not able to test toasts');
});

describe('bulk activation', function () {
    it('activates multiple users', function () {
        $users = User::factory()->count(3)->create(['is_active' => false]);
        $userIds = $users->pluck('id')->toArray();

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selected', $userIds)
            ->call('bulkActivate');

        foreach ($userIds as $id) {
            expect(User::find($id)->is_active)->toBeTrue();
        }
    });

    it('shows success message after activation', function () {
        $users = User::factory()->count(2)->create(['is_active' => false]);

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selected', $users->pluck('id')->toArray())
            ->call('bulkActivate')
            ->assertDispatched('mary-toast');
    })->skip('not able to test toasts');
});

describe('bulk deactivation', function () {
    it('deactivates multiple users', function () {
        $users = User::factory()->count(3)->create(['is_active' => true]);
        $userIds = $users->pluck('id')->toArray();

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selected', $userIds)
            ->call('bulkDeactivate');

        foreach ($userIds as $id) {
            expect(User::find($id)->is_active)->toBeFalse();
        }
    });

    it('shows success message after deactivation', function () {
        $users = User::factory()->count(2)->create(['is_active' => true]);

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selected', $users->pluck('id')->toArray())
            ->call('bulkDeactivate')
            ->assertDispatched('mary-toast');
    })->skip('not able to test toasts');
});

describe('teams dropdown', function () {
    it('loads teams correctly', function () {
        $team = Team::factory()->create(['name' => 'Team A']);

        $component = Livewire::test(USER_INDEX_COMPONENT);
        $teams = $component->get('teams');

        expect($teams)->toHaveCount(1);
        expect($teams->first()['name'])->toContain('Team A');
    });

    it('formats teams with captain avatar', function () {
        $captain = User::factory()->create(['photo' => 'captain.jpg']);
        $team = Team::factory()->create(['captain_id' => $captain->id]);

        $component = Livewire::test(USER_INDEX_COMPONENT);
        $teams = $component->get('teams');

        expect($teams->first()['avatar'])->toBe('captain.jpg');
    });

    it('uses default avatar when captain has no photo', function () {
        $captain = User::factory()->create(['photo' => null]);
        $team = Team::factory()->create(['captain_id' => $captain->id]);

        $component = Livewire::test(USER_INDEX_COMPONENT);
        $teams = $component->get('teams');

        expect($teams->first()['avatar'])->toBe('/images/empty-user.jpg');
    });
});

describe('subscriptions dropdown', function () {
    it('loads subscriptions correctly', function () {
        $component = Livewire::test(USER_INDEX_COMPONENT);
        $subscriptions = $component->get('subscriptions');

        expect($subscriptions)->toHaveCount(5);
    });

    it('groups subscriptions correctly', function () {
        $component = Livewire::test(USER_INDEX_COMPONENT);
        $subscriptions = $component->get('subscriptions');

        $events = $subscriptions->where('group', __('Events'));
        $trainings = $subscriptions->where('group', __('Trainings'));

        expect($events)->toHaveCount(3);
        expect($trainings)->toHaveCount(2);
    });
});