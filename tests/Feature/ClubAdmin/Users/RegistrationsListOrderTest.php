<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

pest()->group('club-admin', 'registrations');

beforeEach(function (): void {
    Club::factory()->ownClub()->create();
    $this->season = Season::factory()->create(['is_active' => true, 'affiliations_open' => true]);

    actingAs(User::factory()->isAdmin()->create([
        'first_name' => 'Permanence',
        'last_name' => 'Secretariat',
        'email' => 'permanence@example.test',
    ]));
});

/**
 * Une affiliation dont le membre est nommé : la factory tire un membre au
 * hasard parmi ceux déjà créés, ce qui ferait porter deux inscriptions au même
 * nom et rendrait tout ordre indécidable.
 *
 * @param  array<string, mixed>  $attributes
 */
function affiliate(string $firstName, string $lastName, array $attributes = []): Subscription
{
    $member = User::factory()->create([
        'first_name' => $firstName,
        'last_name' => $lastName,
    ]);

    return Subscription::factory()->create([
        'user_id' => $member->id,
        'season_id' => test()->season->id,
        'status' => 'confirmed',
        ...$attributes,
    ]);
}

it('orders affiliations of the same status by member name', function (): void {
    affiliate('Zoe', 'Zorro');
    affiliate('Eric', 'Godart');
    affiliate('Anne', 'Albert');

    Livewire::test('pages::club-admin.users.registrations')
        ->assertSeeInOrder(['Anne Albert', 'Eric Godart', 'Zoe Zorro']);
});

it('keeps the workflow order between statuses, names ordering each group', function (): void {
    affiliate('Anne', 'Albert', ['status' => 'paid']);
    affiliate('Zoe', 'Zorro', ['status' => 'pending']);
    affiliate('Bob', 'Bosco', ['status' => 'pending']);

    Livewire::test('pages::club-admin.users.registrations')
        ->assertSeeInOrder(['Bob Bosco', 'Zoe Zorro', 'Anne Albert']);
});

it('shows every affiliation exactly once across its pages', function (): void {
    /*
     * Vingt-cinq lignes pour deux pages de vingt : c'est le découpage qui
     * perdait une ligne quand l'ordre laissait deux statuts égaux indécis.
     */
    $names = [];

    foreach (range(1, 25) as $index) {
        $name = 'Member' . str_pad((string) $index, 2, '0', STR_PAD_LEFT);
        affiliate('Test', $name);
        $names[] = 'Test ' . $name;
    }

    $shown = collect([1, 2])->flatMap(fn (int $page): array => Livewire::test('pages::club-admin.users.registrations')
        ->set('paginators.page', $page)
        ->instance()
        ->registrations()
        ->getCollection()
        ->pluck('name')
        ->all()
    );

    expect($shown)->toHaveCount(25)
        ->and($shown->unique())->toHaveCount(25)
        ->and($shown->sort()->values()->all())->toBe($names);
});

it('sorts by amount, largest first', function (): void {
    affiliate('Anne', 'Albert', ['amount_due' => 60]);
    affiliate('Bob', 'Bosco', ['amount_due' => 300]);
    affiliate('Zoe', 'Zorro', ['amount_due' => 125]);

    Livewire::test('pages::club-admin.users.registrations')
        ->set('sortBy', ['column' => 'amount_due', 'direction' => 'desc'])
        ->assertSeeInOrder(['Bob Bosco', 'Zoe Zorro', 'Anne Albert'])
        ->set('sortBy', ['column' => 'amount_due', 'direction' => 'asc'])
        ->assertSeeInOrder(['Anne Albert', 'Zoe Zorro', 'Bob Bosco']);
});

it('sorts by member name in both directions', function (): void {
    affiliate('Anne', 'Albert');
    affiliate('Eric', 'Godart');
    affiliate('Zoe', 'Zorro');

    Livewire::test('pages::club-admin.users.registrations')
        ->set('sortBy', ['column' => 'name', 'direction' => 'desc'])
        ->assertSeeInOrder(['Zoe Zorro', 'Eric Godart', 'Anne Albert']);
});

it('falls back to the workflow order when the sort column is unknown', function (): void {
    affiliate('Anne', 'Albert');
    affiliate('Zoe', 'Zorro', ['status' => 'pending']);

    Livewire::test('pages::club-admin.users.registrations')
        ->set('sortBy', ['column' => 'deleted_at; drop', 'direction' => 'sideways'])
        ->assertSuccessful()
        ->assertSeeInOrder(['Zoe Zorro', 'Anne Albert']);
});

it('finds a member on his first and last name together', function (): void {
    affiliate('Eric', 'Godart');
    affiliate('Anne', 'Albert');

    Livewire::test('pages::club-admin.users.registrations')
        ->set('search', 'eric godart')
        ->assertSee('Eric Godart')
        ->assertDontSee('Anne Albert');
});

it('returns to the first page whenever the list is recut', function (): void {
    foreach (range(1, 25) as $index) {
        affiliate('Test', 'Member' . str_pad((string) $index, 2, '0', STR_PAD_LEFT));
    }

    $component = Livewire::test('pages::club-admin.users.registrations');

    foreach ([['search', 'member'], ['statusFilter', 'confirmed'], ['selectedSeasonId', null]] as [$property, $value]) {
        $component->set('paginators.page', 2)
            ->set($property, $value)
            ->assertSet('paginators.page', 1);
    }

    $component->set('paginators.page', 2)
        ->set('sortBy', ['column' => 'name', 'direction' => 'desc'])
        ->assertSet('paginators.page', 1);
});
