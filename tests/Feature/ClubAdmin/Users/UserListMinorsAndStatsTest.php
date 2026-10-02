<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\Gender;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

pest()->group('club-admin', 'users');

const MINORS_LIST = 'pages::club-admin.users.index';

/**
 * The last names on the page, read off the paginator: a ward's row
 * serialises its guardian's account, so a name can sit in the HTML without
 * being listed.
 *
 * @return list<string>
 */
function minorsListLastNames(Testable $component): array
{
    return $component->viewData('users')->pluck('last_name')->sort()->values()->all();
}

/**
 * Only the stat strip: the table below repeats these words in its own cells.
 */
function minorsListStatStrip(Testable $component): string
{
    return str($component->html())->after('data-stat-strip')->before('</section>')->toString();
}

/**
 * A member pinned on everything the filters and the cards read: age, gender,
 * guardian, licence and seasons. The factory draws the gender and the
 * birthdate at random, and either would move a count.
 *
 * @param  list<Season>  $seasons
 */
function minorsListMember(string $lastName, ?int $age, Gender $gender, array $seasons = [], bool $competitive = false, bool $guarded = false): User
{
    $member = User::factory()->create([
        'last_name' => $lastName,
        'gender' => $gender->value,
        'birthdate' => $age === null ? null : now()->subYears($age)->subMonths(2),
    ]);

    foreach ($seasons as $season) {
        Subscription::factory()->for($member)->for($season)->create([
            'status' => 'confirmed',
            'is_competitive' => $competitive,
        ]);
    }

    if ($guarded) {
        Guardian::factory()->create()->users()->attach($member);
    }

    return $member;
}

/**
 * Hyphenated last names on purpose: faker's fr_BE list holds none, so no
 * other fixture can ever print one of them by chance.
 */
beforeEach(function (): void {
    actingAs(User::factory()->isAdmin()->create([
        'last_name' => 'Admin-Secretariat',
        'gender' => Gender::MEN->value,
        'birthdate' => now()->subYears(40),
    ]));

    $this->current = makeActiveSeason();
    $this->previous = Season::factory()->create([
        'start_at' => now()->subYear()->startOfYear(),
        'end_at' => now()->subYear()->endOfYear(),
    ]);
});

describe('the "minors without a responsible adult" filter', function (): void {
    beforeEach(function (): void {
        minorsListMember('Mineur-Seul', 12, Gender::WOMEN, [$this->current]);
        minorsListMember('Mineur-Accompagne', 12, Gender::MEN, [$this->current], guarded: true);
        minorsListMember('Adulte-Seul', 30, Gender::WOMEN, [$this->current]);
        minorsListMember('Age-Inconnu', null, Gender::MEN, [$this->current]);
        minorsListMember('Mineur-Non-Affilie', 10, Gender::MEN);
        minorsListMember('Mineur-Archive', 11, Gender::MEN)->delete();
    });

    it('keeps the minors nobody answers for, affiliated or not', function (): void {
        $component = Livewire::withQueryParams(['allMembers' => true, 'minorsWithoutGuardian' => true])->test(MINORS_LIST);

        // Not the ward with a guardian, not the adult, not the member of
        // unknown age — the "Incomplete profile" filter is the one that finds
        // them — and not the archived minor.
        expect(minorsListLastNames($component))->toBe(['Mineur-Non-Affilie', 'Mineur-Seul']);
    });

    it('applies within the default view like any other filter', function (): void {
        $component = Livewire::test(MINORS_LIST)->set('minorsWithoutGuardian', true);

        expect(minorsListLastNames($component))->toBe(['Mineur-Seul']);
    });

    it('shows a chip that removes it, and is cleared with the other filters', function (): void {
        $component = Livewire::withQueryParams(['minorsWithoutGuardian' => true])->test(MINORS_LIST);

        expect($component->get('filterChips'))->toContain(['key' => 'minorsWithoutGuardian', 'label' => __('Minors without a responsible adult')]);

        $component
            ->call('removeFilter', 'minorsWithoutGuardian')
            ->assertSet('minorsWithoutGuardian', false)
            ->set('minorsWithoutGuardian', true)
            ->call('clearFilters')
            ->assertSet('minorsWithoutGuardian', false);
    });

    it('sits in the Profile section of the drawer', function (): void {
        Livewire::test(MINORS_LIST)->assertSeeInOrder([
            __('Incomplete profile'),
            __('Adult without an address'),
            __('Minors without a responsible adult'),
            __('Unpaid subscription'),
        ]);
    });
});

describe('the age filter', function (): void {
    beforeEach(function (): void {
        minorsListMember('Mineur-Un', 12, Gender::WOMEN);
        minorsListMember('Adulte-Un', 30, Gender::MEN);
        minorsListMember('Age-Inconnu', null, Gender::MEN);
    });

    it('keeps the members under 18 today', function (): void {
        $component = Livewire::withQueryParams(['allMembers' => true, 'age' => 'minors'])->test(MINORS_LIST);

        expect(minorsListLastNames($component))->toBe(['Mineur-Un']);
    });

    it('keeps the members of 18 and over, the secretary among them', function (): void {
        $component = Livewire::withQueryParams(['allMembers' => true, 'age' => 'adults'])->test(MINORS_LIST);

        expect(minorsListLastNames($component))->toBe(['Admin-Secretariat', 'Adulte-Un']);
    });

    it('turns 18 on the birthday itself', function (): void {
        User::factory()->create(['last_name' => 'Majeur-Aujourdhui', 'birthdate' => now()->subYears(18)->startOfDay()]);
        User::factory()->create(['last_name' => 'Majeur-Demain', 'birthdate' => now()->subYears(18)->addDay()->startOfDay()]);

        $minors = Livewire::withQueryParams(['allMembers' => true, 'age' => 'minors'])->test(MINORS_LIST);
        $adults = Livewire::withQueryParams(['allMembers' => true, 'age' => 'adults'])->test(MINORS_LIST);

        expect(minorsListLastNames($minors))->toContain('Majeur-Demain')->not->toContain('Majeur-Aujourdhui')
            ->and(minorsListLastNames($adults))->toContain('Majeur-Aujourdhui')->not->toContain('Majeur-Demain');
    });

    it('lists everybody when no age is chosen, or an unknown one', function (): void {
        $all = Livewire::withQueryParams(['allMembers' => true])->test(MINORS_LIST);
        $tampered = Livewire::withQueryParams(['allMembers' => true, 'age' => 'teenagers'])->test(MINORS_LIST);

        expect(minorsListLastNames($all))->toBe(['Admin-Secretariat', 'Adulte-Un', 'Age-Inconnu', 'Mineur-Un'])
            ->and(minorsListLastNames($tampered))->toBe(minorsListLastNames($all));
        $tampered->assertCount('filterChips', 0);
    });

    it('shows a chip that removes it, and is cleared with the other filters', function (): void {
        $component = Livewire::withQueryParams(['age' => 'adults'])->test(MINORS_LIST);

        expect($component->get('filterChips'))->toContain(['key' => 'age', 'label' => __('Adults')]);

        $component
            ->call('removeFilter', 'age')
            ->assertSet('age', '')
            ->set('age', 'minors')
            ->call('clearFilters')
            ->assertSet('age', '');
    });
});

describe('the stat strip', function (): void {
    it('draws four cards, all read off this season\'s affiliates', function (): void {
        minorsListMember('Mineur-Seul', 12, Gender::WOMEN, [$this->current]);
        minorsListMember('Mineur-Accompagne', 12, Gender::MEN, [$this->current, $this->previous], competitive: true, guarded: true);
        minorsListMember('Adulte-Seul', 30, Gender::WOMEN, [$this->current], competitive: true);
        minorsListMember('Age-Inconnu', null, Gender::MEN, [$this->current]);
        // Neither of these counts anywhere: not affiliated this season.
        minorsListMember('Mineur-Non-Affilie', 10, Gender::WOMEN);
        minorsListMember('Ancienne-Joueuse', 14, Gender::WOMEN, [$this->previous], competitive: true);

        $component = Livewire::test(MINORS_LIST);

        expect($component->get('stats'))->toBe([
            'affiliated' => 4,
            'new' => 3,
            'competitors' => 2,
            'recreational' => 2,
            'minors' => 2,
            'minors_without_guardian' => 1,
            'women' => 2,
        ]);

        $strip = minorsListStatStrip($component);

        expect(substr_count($strip, 'data-stat-value'))->toBe(4)
            ->and($strip)->toContain(e(__('Affiliated')))
            ->toContain(e(trans_choice('Including :count newcomer|Including :count newcomers', 3, ['count' => 3])))
            ->toContain(e(__('Competitors')))
            ->toContain(e(trans_choice(':count recreational|:count recreational', 2, ['count' => 2])))
            ->toContain(e(__('Minors')))
            ->toContain(e(trans_choice('Including :count without a responsible adult|Including :count without a responsible adult', 1, ['count' => 1])))
            ->toContain(e(__('Women')))
            ->not->toContain(e(__('To follow up')))
            ->not->toContain(e(__('Responsible adults')))
            ->not->toContain(e(__('No activity recorded')));
    });

    it('words a single member in the singular', function (): void {
        minorsListMember('Mineur-Seul', 12, Gender::WOMEN, [$this->current]);
        minorsListMember('Adulte-Competiteur', 30, Gender::MEN, [$this->current, $this->previous], competitive: true);

        $strip = minorsListStatStrip(Livewire::test(MINORS_LIST));

        expect($strip)
            ->toContain(e(trans_choice('Including :count newcomer|Including :count newcomers', 1, ['count' => 1])))
            ->toContain(e(trans_choice(':count recreational|:count recreational', 1, ['count' => 1])))
            ->toContain(e(trans_choice('Including :count without a responsible adult|Including :count without a responsible adult', 1, ['count' => 1])));
    });

    it('raises the minors without a responsible adult in a warning tone, without any gesture', function (): void {
        minorsListMember('Mineur-Seul', 12, Gender::WOMEN, [$this->current]);
        minorsListMember('Mineur-Seul-Bis', 13, Gender::MEN, [$this->current]);

        $strip = minorsListStatStrip(Livewire::test(MINORS_LIST));
        $alert = str($strip)->after('data-stat-alert')->before('</div>')->toString();

        expect($alert)->toContain('badge-warning badge-soft')
            ->toContain(e(trans_choice('Including :count without a responsible adult|Including :count without a responsible adult', 2, ['count' => 2])))
            ->and($strip)->not->toContain('<a ')
            ->not->toContain('<button')
            ->not->toContain('wire:click');
    });

    it('says nothing about guardians when every minor has one', function (): void {
        minorsListMember('Mineur-Accompagne', 12, Gender::MEN, [$this->current], guarded: true);
        // Without a guardian, but not affiliated this season: the card ignores them.
        minorsListMember('Mineur-Non-Affilie', 10, Gender::WOMEN);

        $component = Livewire::test(MINORS_LIST);

        expect($component->get('stats')['minors'])->toBe(1)
            ->and($component->get('stats')['minors_without_guardian'])->toBe(0)
            ->and(minorsListStatStrip($component))->not->toContain('data-stat-alert');
    });
});
