<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Mail\InviteNewUserMail;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

pest()->group('club-admin', 'users');

const USER_INDEX_COMPONENT = 'pages::club-admin.users.index';

beforeEach(function (): void {
    // On crée un utilisateur admin pour les tests
    $this->admin = User::factory()->isAdmin()->create();
    actingAs($this->admin);

    Season::factory()->create(['is_active' => true]);
});

describe('rendering and display', function (): void {
    it('renders successfully', function (): void {
        Livewire::test(USER_INDEX_COMPONENT)
            ->assertStatus(200);
    });

    it('displays the correct headers', function (): void {
        Livewire::test(USER_INDEX_COMPONENT)
            ->assertSee(__('Name'))
            ->assertSee(__('Email'))
            ->assertSee(__('Licence'))
            ->assertSee(__('Ranking'));
    });

    it('displays users in the table', function (): void {
        $users = User::factory()->count(3)->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->assertSee($users[0]->first_name)
            ->assertSee($users[1]->email)
            ->assertSee($users[2]->last_name);
    });

    it('paginates users correctly', function (): void {
        User::factory()->count(20)->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->assertSee('1') // page 1
            ->assertSee('2'); // page 2
    });

    it('displays mobile view elements', function (): void {
        $user = User::factory()->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->assertSee($user->email)
            ->assertSee($user->phone_number);
    });
});

describe('search functionality', function (): void {
    it('filters users by first name', function (): void {
        $john = User::factory()->create(['first_name' => 'John']);
        $jane = User::factory()->create(['first_name' => 'Jane']);

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('search', 'John')
            ->assertSee($john->first_name)
            ->assertDontSee($jane->first_name);
    });

    it('filters users by last name', function (): void {
        $smith = User::factory()->create(['last_name' => 'Smith']);
        $doe = User::factory()->create(['last_name' => 'Doe']);

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('search', 'Smith')
            ->assertSee($smith->last_name)
            ->assertDontSee($doe->last_name);
    });

    it('filters users by email', function (): void {
        $user1 = User::factory()->create(['email' => 'john@example.com']);
        $user2 = User::factory()->create(['email' => 'jane@example.com']);

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('search', 'john@')
            ->assertSee($user1->email)
            ->assertDontSee($user2->email);
    });

    it('resets pagination when searching', function (): void {
        User::factory()->count(20)->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->call('setPage', 2)
            ->set('search', 'test')
            ->assertSet('paginators.page', 1);
    });

    it('performs case-insensitive search', function (): void {
        $user = User::factory()->create(['first_name' => 'John']);

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('search', 'john')
            ->assertSee($user->first_name);
    });

    it('finds compound names with words spanning first and last name', function (): void {
        // L'adresse est figée : `fake()->city()` en fr_BE tire de vraies communes,
        // dont Saint-Martin, et la ligne affichée est celle de ce membre-ci. Le
        // « Martin » attendu absent serait alors venu du résultat cherché, pas de
        // celui qu'on veut voir écarté — la CI l'a fait tomber une fois.
        $jp = User::factory()->create([
            'first_name' => 'Jean-Pierre',
            'last_name' => 'Van Oudenhove',
            'street' => 'Rue du Sport 1',
            'city_name' => 'Ottignies',
        ]);
        $other = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Martin']);

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('search', 'Jean Van')
            ->assertSee('Van Oudenhove')
            ->assertDontSee('Martin');
    });
});

describe('licence type filtering', function (): void {
    it('shows both competitive and recreational users by default', function (): void {
        $competitive = User::factory()->isCompetitor()->create();
        $recreational = User::factory()->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->assertSee($competitive->email)
            ->assertSee($recreational->email);
    });

    it('filters only competitive users', function (): void {
        $competitive = User::factory()->isCompetitor()->create();
        $recreational = User::factory()->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selectedLicenceType', 'competitive')
            ->assertSee($competitive->email)
            ->assertDontSee($recreational->email);
    });

    it('filters only recreational users', function (): void {
        $competitive = User::factory()->isCompetitor()->create();
        $recreational = User::factory()->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selectedLicenceType', 'recreative')
            ->assertDontSee($competitive->email)
            ->assertSee($recreational->email);
    });

    it('resets pagination when changing licence type', function (): void {
        User::factory()->count(20)->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->call('setPage', 2)
            ->set('selectedLicenceType', 'competitive')
            ->assertSet('paginators.page', 1);
    });
});

describe('gender filtering', function (): void {
    it('filters users by gender', function (): void {
        $male = User::factory()->create(['gender' => 'MEN']);
        $female = User::factory()->create(['gender' => 'WOMEN']);

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('categories', ['MEN'])
            ->assertSee($male->email)
            ->assertDontSee($female->email);
    });

    it('filters users by multiple genders', function (): void {
        $male = User::factory()->create(['gender' => 'MEN']);
        $female = User::factory()->create(['gender' => 'WOMEN']);

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('categories', ['MEN', 'WOMEN'])
            ->assertSee($male->email)
            ->assertSee($female->email);
    });

    it('resets pagination when changing categories', function (): void {
        User::factory()->count(20)->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->call('setPage', 2)
            ->set('categories', ['MEN'])
            ->assertSet('paginators.page', 1);
    });
});

describe('team filtering', function (): void {
    it('filters users by team', function (): void {
        $team = Team::factory(['name' => 'A'])->create();
        $userInTeam = User::factory()->create();
        $userNotInTeam = User::factory()->create();

        $team->users()->attach($userInTeam);

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('team_ids', [$team->id])
            ->assertSee($userInTeam->email)
            ->assertDontSee($userNotInTeam->email);
    });

    it('filters users by multiple teams', function (): void {
        $team1 = Team::factory(['name' => 'A'])->create();
        $team2 = Team::factory(['name' => 'B'])->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $team1->users()->attach($user1);
        $team2->users()->attach($user2);

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('team_ids', [$team1->id, $team2->id])
            ->assertSee($user1->email)
            ->assertSee($user2->email)
            ->assertDontSee($user3->email);
    });
});

describe('filter combination', function (): void {
    it('combines search and licence type filters', function (): void {
        $competitiveJohn = User::factory()->isCompetitor()->create(['first_name' => 'John']);
        $recreationalJohn = User::factory()->create(['first_name' => 'John']);

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('search', 'John')
            ->set('selectedLicenceType', 'competitive')
            ->assertSee($competitiveJohn->email)
            ->assertDontSee($recreationalJohn->email);
    });

    it('combines multiple filters correctly', function (): void {
        $target = User::factory()->isCompetitor()->create([
            'first_name' => 'John',
            'gender' => 'MEN',
        ]);

        $wrong = User::factory()->create([
            'first_name' => 'Jane',
            'gender' => 'WOMEN',
        ]);

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('search', 'John')
            ->set('selectedLicenceType', 'competitive')
            ->set('categories', ['MEN'])
            ->assertSee($target->email)
            ->assertDontSee($wrong->email);
    });
});

describe('active filters count', function (): void {
    it('counts zero active filters by default', function (): void {
        Livewire::test(USER_INDEX_COMPONENT)
            ->assertCount('filterChips', 0);
    });

    it('counts licence type filter', function (): void {
        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selectedLicenceType', 'competitive')
            ->assertCount('filterChips', 1);
    });

    it('counts category filters', function (): void {
        Livewire::test(USER_INDEX_COMPONENT)
            ->set('categories', ['MEN', 'WOMEN'])
            ->assertCount('filterChips', 2);
    });

    it('counts all active filters combined', function (): void {
        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selectedLicenceType', 'competitive')
            ->set('categories', ['MEN', 'WOMEN'])
            ->assertCount('filterChips', 3); // 1 + 2
    });
});

describe('filter reset', function (): void {
    it('resets all filters', function (): void {
        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selectedLicenceType', 'competitive')
            ->set('categories', ['MEN'])
            ->call('clearFilters')
            ->assertSet('selectedLicenceType', 'both')
            ->assertSet('categories', []);
    });

    it('resets pagination when resetting filters', function (): void {
        User::factory()->count(20)->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->call('setPage', 2)
            ->call('clearFilters')
            ->assertSet('paginators.page', 1);
    });
});

describe('sorting', function (): void {
    it('sorts by last name ascending by default', function (): void {
        Livewire::test(USER_INDEX_COMPONENT)
            ->assertSet('sortBy', ['column' => 'last_name', 'direction' => 'asc']);
    });

    it('sorts users correctly', function (): void {
        $alice = User::factory()->create(['last_name' => 'Alice']);
        $bob = User::factory()->create(['last_name' => 'Bob']);
        $charlie = User::factory()->create(['last_name' => 'Charlie']);

        $users = Livewire::test(USER_INDEX_COMPONENT)->get('users');
        $ids = $users->pluck('id')->toArray();

        expect(array_search($alice->id, $ids))->toBeLessThan(array_search($bob->id, $ids));
        expect(array_search($bob->id, $ids))->toBeLessThan(array_search($charlie->id, $ids));
    });

    it('sorts by the name column using first name then last name', function (): void {
        // Same last name to prove first_name is the primary sort key.
        $anna = User::factory()->create(['first_name' => 'Anna', 'last_name' => 'Dupont']);
        $bruno = User::factory()->create(['first_name' => 'Bruno', 'last_name' => 'Dupont']);

        $users = Livewire::test(USER_INDEX_COMPONENT)
            ->set('sortBy', ['column' => 'name', 'direction' => 'asc'])
            ->get('users');

        $ids = $users->pluck('id')->toArray();

        expect(array_search($anna->id, $ids))->toBeLessThan(array_search($bruno->id, $ids));
    });

    it('falls back to a safe default when the sort column is unknown', function (): void {
        User::factory()->count(3)->create();

        // A tampered `sortBy` URL value must not reach orderBy() raw and crash.
        Livewire::test(USER_INDEX_COMPONENT)
            ->set('sortBy', ['column' => 'not_a_column', 'direction' => 'asc'])
            ->assertStatus(200)
            ->get('users');
    });
});

// Actions

describe('user selection', function (): void {
    it('can select a single user', function (): void {
        $user = User::factory()->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selected', [$user->id])
            ->assertSet('selected', [$user->id]);
    });

    it('can select multiple users', function (): void {
        $users = User::factory()->count(3)->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selected', $users->pluck('id')->toArray())
            ->assertCount('selected', 3);
    });

    it('shows bulk action pill when users are selected', function (): void {
        $user = User::factory()->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selected', [$user->id])
            ->assertSee(__('Add to team'));
    });
});

describe('single user deletion', function (): void {
    it('opens delete confirmation modal', function (): void {
        $user = User::factory()->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->call('confirmDelete', $user->id)
            ->assertSet('userToDelete', $user->id)
            ->assertSet('deleteModal', true);
    });

    it('deletes a user successfully', function (): void {
        $user = User::factory()->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->call('confirmDelete', $user->id)
            ->call('delete')
            ->assertSet('deleteModal', false)
            ->assertSet('userToDelete', null);

        expect(User::find($user->id))->toBeNull();
    });

    it('shows success message after deletion', function (): void {
        $user = User::factory()->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->call('confirmDelete', $user->id)
            ->call('delete')
            ->assertDispatched('mary-toast');
    })->skip('not able to test toasts');

    it('does not delete user if modal is cancelled', function (): void {
        $user = User::factory()->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->call('confirmDelete', $user->id)
            ->set('deleteModal', false);

        expect(User::find($user->id))->not->toBeNull();
    });
});

describe('bulk archive', function (): void {
    it('opens bulk archive confirmation modal', function (): void {
        $users = User::factory()->count(3)->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selected', $users->pluck('id')->toArray())
            ->call('confirmBulkArchive')
            ->assertSet('confirmArchiveModal', true);
    });

    it('archives multiple users successfully', function (): void {
        $users = User::factory()->count(3)->create();
        $userIds = $users->pluck('id')->toArray();

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selected', $userIds)
            ->call('bulkArchive')
            ->assertSet('confirmArchiveModal', false)
            ->assertSet('selected', []);

        foreach ($userIds as $id) {
            expect(User::find($id))->toBeNull();
        }
    });

    it('shows success message after bulk archive', function (): void {
        $users = User::factory()->count(3)->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selected', $users->pluck('id')->toArray())
            ->call('bulkArchive')
            ->assertDispatched('mary-toast');
    })->skip('not able to test toasts');

    it('keeps non-selected users intact', function (): void {
        $toArchive = User::factory()->count(2)->create();
        $toKeep = User::factory()->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selected', $toArchive->pluck('id')->toArray())
            ->call('bulkArchive');

        expect(User::find($toKeep->id))->not->toBeNull();
    });
});

describe('bulk add to team', function (): void {
    it('requires team selection', function (): void {
        $users = User::factory()->count(2)->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selected', $users->pluck('id')->toArray())
            ->set('team_id', null)
            ->call('bulkAddToTeam');

        // La méthode retourne early si team_id est null
        // Aucun changement ne devrait être fait
    })->todo();

    it('resets team_id after adding', function (): void {
        $team = Team::factory()->create();
        $users = User::factory()->count(2)->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selected', $users->pluck('id')->toArray())
            ->set('team_id', $team->id)
            ->call('bulkAddToTeam')
            ->assertSet('team_id', null);
    });

    it('shows success message', function (): void {
        $team = Team::factory()->create();
        $users = User::factory()->count(2)->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selected', $users->pluck('id')->toArray())
            ->set('team_id', $team->id)
            ->call('bulkAddToTeam')
            ->assertDispatched('mary-toast');
    })->skip('not able to test toasts');
});

describe('bulk subscription', function (): void {
    it('requires subscription selection', function (): void {
        $users = User::factory()->count(2)->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selected', $users->pluck('id')->toArray())
            ->set('subscription_id', null)
            ->call('bulkSubscribe');

        // La méthode retourne early si subscription_id est null
    })->todo();

    it('resets subscription_id after subscribing', function (): void {
        $users = User::factory()->count(2)->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selected', $users->pluck('id')->toArray())
            ->set('subscription_id', 'event-1')
            ->call('bulkSubscribe')
            ->assertSet('subscription_id', null);
    });

    it('shows success message', function (): void {
        $users = User::factory()->count(2)->create();

        Livewire::test(USER_INDEX_COMPONENT)
            ->set('selected', $users->pluck('id')->toArray())
            ->set('subscription_id', 'event-1')
            ->call('bulkSubscribe')
            ->assertDispatched('mary-toast');
    })->skip('not able to test toasts');
});

describe('teams dropdown', function (): void {
    it('loads teams correctly', function (): void {
        $team = Team::factory()->create(['name' => 'Team A']);

        $component = Livewire::test(USER_INDEX_COMPONENT);
        $teams = $component->get('teams');

        expect($teams)->toHaveCount(1);
        expect($teams->first()['name'])->toContain('Team A');
    });

    it('formats teams with captain avatar', function (): void {
        $captain = User::factory()->create(['photo' => 'captain.jpg']);
        $team = Team::factory()->create(['captain_id' => $captain->id]);

        $component = Livewire::test(USER_INDEX_COMPONENT);
        $teams = $component->get('teams');

        expect($teams->first()['avatar'])->toBe('captain.jpg');
    });

    it('uses default avatar when captain has no photo', function (): void {
        $captain = User::factory()->create(['photo' => null]);
        $team = Team::factory()->create(['captain_id' => $captain->id]);

        $component = Livewire::test(USER_INDEX_COMPONENT);
        $teams = $component->get('teams');

        expect($teams->first()['avatar'])->toBe('/images/empty-user.jpg');
    });
});

describe('subscriptions dropdown', function (): void {
    it('loads subscriptions correctly', function (): void {
        $component = Livewire::test(USER_INDEX_COMPONENT);
        $subscriptions = $component->get('subscriptions');

        expect($subscriptions)->toHaveCount(5);
    });

    it('groups subscriptions correctly', function (): void {
        $component = Livewire::test(USER_INDEX_COMPONENT);
        $subscriptions = $component->get('subscriptions');

        $events = $subscriptions->where('group', __('Events'));
        $trainings = $subscriptions->where('group', __('Trainings'));

        expect($events)->toHaveCount(3);
        expect($trainings)->toHaveCount(2);
    });
});

// ── sendInvitation ────────────────────────────────────────────────────────────

describe('sendInvitation', function (): void {
    it('queues InviteNewUserMail to the target user', function (): void {
        Mail::fake();

        $target = User::factory()->create(['email' => 'member@example.com']);

        Livewire::test(USER_INDEX_COMPONENT)
            ->call('sendInvitation', $target->id);

        Mail::assertQueued(InviteNewUserMail::class, fn ($mail) => $mail->hasTo('member@example.com'));
    });

    it('shows a success toast after sending', function (): void {
        Mail::fake();

        $target = User::factory()->create(['email' => 'toast@example.com']);

        Livewire::test(USER_INDEX_COMPONENT)
            ->call('sendInvitation', $target->id)
            ->assertDispatched('toast');
    })->skip('Mary UI toast events are not assertable in this test setup');
});

describe('the stat strip', function (): void {
    /*
     * The members list is the most visited screen of the back office, and it
     * hand-rolled its four stat cards — inverting the rule the shared component
     * documents in its own header: colour belongs on the icon chip, never on the
     * figure. A green "0", a blue "0" and a "7" at 30 % opacity sat side by side.
     */
    /** Only the stat strip: the table below repeats these words in its own cells. */
    function statStrip(string $html): string
    {
        return str($html)->after('grid grid-cols-2 gap-4 lg:grid-cols-4')->before('</div>&#10;')->toString();
    }

    it('draws its four figures with the shared stat card', function (): void {
        $html = Livewire::test(USER_INDEX_COMPONENT)->html();

        $strip = statStrip($html);

        expect(substr_count($strip, 'font-black tabular-nums'))->toBe(4);
    });

    it('never colours the figure itself', function (): void {
        $html = Livewire::test(USER_INDEX_COMPONENT)->html();

        $strip = statStrip($html);

        foreach (['text-success', 'text-primary', 'text-base-content/30', 'text-base-content/60'] as $colour) {
            expect($strip)->not->toContain('font-bold ' . $colour);
        }
    });
});
