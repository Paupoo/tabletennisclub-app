<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\MemberDeparture;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\Ranking;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

pest()->group('club-admin', 'users');

const AFFILIATION_LIST = 'pages::club-admin.users.index';

/**
 * The last names on the page, read off the paginator rather than the HTML: a
 * ward's row serialises its guardian's account, so a name can sit in the page
 * without being listed.
 *
 * @return list<string>
 */
function listedLastNames(Testable $component): array
{
    return $component->viewData('users')->pluck('last_name')->values()->all();
}

/**
 * Hyphenated last names on purpose: faker's fr_BE list holds none, so no
 * other fixture can ever print one of them by chance.
 */
beforeEach(function (): void {
    actingAs(User::factory()->isAdmin()->create(['last_name' => 'Admin-Secretariat']));

    $this->current = makeActiveSeason();
    $this->previous = Season::factory()->create([
        'name' => 'previous-season',
        'start_at' => now()->subYear()->startOfYear(),
        'end_at' => now()->subYear()->endOfYear(),
    ]);
    $this->older = Season::factory()->create([
        'start_at' => now()->subYears(2)->startOfYear(),
        'end_at' => now()->subYears(2)->endOfYear(),
    ]);

    $member = function (string $lastName, array $seasons, bool $competitive = false, string $ranking = 'NA'): User {
        $user = User::factory()->create(['last_name' => $lastName, 'ranking' => $ranking]);

        foreach ($seasons as $season) {
            Subscription::factory()->for($user)->for($season)->create([
                'status' => 'confirmed',
                'is_competitive' => $competitive,
            ]);
        }

        return $user;
    };

    $this->new = $member('Nouveau-Venu', [$this->current]);
    $this->renewed = $member('Fidele-Renouvele', [$this->current, $this->previous], competitive: true, ranking: 'B4');
    $this->returning = $member('Revenant-Retour', [$this->current, $this->older]);
    $this->toFollowUp = $member('Relance-Attendue', [$this->previous], competitive: true, ranking: 'E6');
    $this->former = $member('Ancien-Parti', [$this->older]);
    $this->never = $member('Jamais-Inscrit', []);

    $this->parent = $member('Parent-Responsable', []);
    $guardian = Guardian::factory()->create(['user_id' => $this->parent->id]);
    $guardian->users()->attach($this->new);

    $this->archived = $member('Archive-Efface', [$this->older]);
    $this->archived->delete();
});

describe('the default view', function (): void {
    it('opens on the current members, those to follow up and the responsible adults', function (): void {
        expect(listedLastNames(Livewire::test(AFFILIATION_LIST)))->toEqualCanonicalizing([
            'Nouveau-Venu', 'Fidele-Renouvele', 'Revenant-Retour', 'Relance-Attendue', 'Parent-Responsable',
        ]);
    });

    it('says so with a chip', function (): void {
        $component = Livewire::test(AFFILIATION_LIST);

        expect(collect($component->get('filterChips'))->pluck('label'))
            ->toContain(__('Current members + to follow up'));
    });

    it('shows everybody but the archived once the chip is removed', function (): void {
        $component = Livewire::test(AFFILIATION_LIST)->call('removeFilter', 'currentMembers');

        expect(listedLastNames($component))->toEqualCanonicalizing([
            'Admin-Secretariat', 'Nouveau-Venu', 'Fidele-Renouvele', 'Revenant-Retour', 'Relance-Attendue',
            'Ancien-Parti', 'Jamais-Inscrit', 'Parent-Responsable',
        ])->and($component->get('filterChips'))->toBe([]);
    });

    it('comes back when the filters are cleared', function (): void {
        $component = Livewire::test(AFFILIATION_LIST)
            ->call('removeFilter', 'currentMembers')
            ->call('clearFilters');

        expect(listedLastNames($component))->not->toContain('Ancien-Parti')->toContain('Nouveau-Venu');
    });
});

describe('the affiliation filter', function (): void {
    it('narrows the list to the statuses ticked', function (): void {
        $component = Livewire::test(AFFILIATION_LIST)->set('affiliation', ['former', 'never']);

        expect(listedLastNames($component))->toEqualCanonicalizing([
            'Admin-Secretariat', 'Ancien-Parti', 'Jamais-Inscrit', 'Parent-Responsable',
        ])->and(collect($component->get('filterChips'))->pluck('label')->all())->toBe([
            __('Former member'), __('Never affiliated'),
        ]);
    });

    it('drops one status when its chip is removed', function (): void {
        $component = Livewire::test(AFFILIATION_LIST)
            ->set('affiliation', ['former', 'never'])
            ->call('removeFilter', 'affiliation_never')
            ->assertSet('affiliation', ['former']);

        expect(listedLastNames($component))->toBe(['Ancien-Parti']);
    });

    it('falls back on the default view for a status it does not know', function (): void {
        $component = Livewire::withQueryParams(['affiliation' => ['bogus']])->test(AFFILIATION_LIST);

        expect(listedLastNames($component))->toContain('Nouveau-Venu')->not->toContain('Ancien-Parti');
    });
});

describe('searching', function (): void {
    it('looks through every member while the default view is on', function (): void {
        $component = Livewire::test(AFFILIATION_LIST)->set('search', 'Ancien');

        expect(listedLastNames($component))->toBe(['Ancien-Parti']);
        $component->assertSee(__('Searching all members'));
    });

    it('keeps an affiliation filter chosen on purpose', function (): void {
        $component = Livewire::test(AFFILIATION_LIST)
            ->set('affiliation', ['new'])
            ->set('search', 'Ancien');

        expect(listedLastNames($component))->toBe([]);
        $component->assertDontSee(__('Searching all members'));
    });

    it('never brings an archived member back', function (): void {
        expect(listedLastNames(Livewire::test(AFFILIATION_LIST)->set('search', 'Archive')))->toBe([]);
    });
});

describe('the licence filter', function (): void {
    it('keeps the competitive licences of this season', function (): void {
        $component = Livewire::test(AFFILIATION_LIST)->set('selectedLicenceType', 'competitive');

        expect(listedLastNames($component))->toBe(['Fidele-Renouvele']);
    });

    it('reads recreational as affiliated this season without a competitive licence', function (): void {
        $component = Livewire::test(AFFILIATION_LIST)->set('selectedLicenceType', 'recreative');

        expect(listedLastNames($component))->toEqualCanonicalizing(['Nouveau-Venu', 'Revenant-Retour']);
    });

    it('ignores a value it does not know', function (): void {
        $component = Livewire::withQueryParams(['selectedLicenceType' => 'bogus'])->test(AFFILIATION_LIST);

        expect(listedLastNames($component))->toContain('Fidele-Renouvele', 'Nouveau-Venu');
    });
});

describe('the responsible adults toggle', function (): void {
    it('keeps only the members who answer for somebody', function (): void {
        $component = Livewire::test(AFFILIATION_LIST)->set('responsibleAdultsOnly', true);

        expect(listedLastNames($component))->toBe(['Parent-Responsable']);
    });
});

describe('the columns', function (): void {
    it('heads the table with the affiliation and the account', function (): void {
        $headers = collect(Livewire::test(AFFILIATION_LIST)->get('headers'));

        expect($headers->pluck('key')->all())->toBe(['name', 'affiliation', 'ranking', 'status'])
            ->and($headers->firstWhere('key', 'status')['label'])->toBe(__('Account'))
            ->and($headers->firstWhere('key', 'affiliation')['label'])->toBe(__('Affiliation'));
    });

    /*
     * The drawer prints every status and licence word as an option, so the
     * cells are read through their data attributes rather than their text.
     */
    it('shows the status, and the licence only for a member affiliated this season', function (): void {
        $html = Livewire::test(AFFILIATION_LIST)
            ->set('search', 'Relance-Attendue')
            ->html();

        expect($html)->toContain('data-membership-status="to_follow_up"')
            ->toContain(__('Last season: :season', ['season' => 'previous-season']))
            ->not->toContain('data-licence=')
            ->not->toContain('>E6<');
    });

    it('shows the licence and the ranking of a competitor of this season', function (): void {
        $html = Livewire::test(AFFILIATION_LIST)
            ->set('search', 'Fidele-Renouvele')
            ->html();

        expect($html)->toContain('data-membership-status="renewed"')
            ->toContain('data-licence="competitive"')
            ->not->toContain('data-licence="recreational"')
            ->toContain('>B4<');
    });

    it('badges the responsible adults, whatever their status', function (): void {
        $parent = Livewire::test(AFFILIATION_LIST)->set('search', 'Parent-Responsable')->html();
        $child = Livewire::test(AFFILIATION_LIST)->set('search', 'Nouveau-Venu')->html();

        expect($parent)->toContain('data-responsible-adult')
            ->toContain('data-membership-status="never"')
            ->and($child)->not->toContain('data-responsible-adult');
    });

    it('reads every row from the page query, whatever the number of rows', function (): void {
        // Everybody on file, so the departed members below are listed too.
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::withQueryParams(['allMembers' => true])->test(AFFILIATION_LIST)->set('selectedLicenceType', 'both');
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        // The first render warms the caches (current season, permissions).
        $count();
        $few = $count();

        foreach (range(1, 4) as $i) {
            $user = User::factory()->create(['ranking' => Ranking::C2->value]);
            Subscription::factory()->for($user)->for($this->current)->create(['status' => 'paid', 'is_competitive' => true]);

            // Half of them left: their badge names a reason, read in the same query.
            if ($i % 2 === 0) {
                MemberDeparture::factory()->for($user)->for($this->current)->create();
            }
        }

        expect($count())->toBe($few);
    });
});

describe('the stat strip', function (): void {
    it('counts with the same words as the filters', function (): void {
        $component = Livewire::test(AFFILIATION_LIST);

        expect($component->get('stats'))->toBe([
            'affiliated' => 3,
            'new' => 1,
            'to_follow_up' => 1,
            'left' => 0,
            'responsible_adults' => 1,
        ]);

        $component->assertSee(__('Affiliated'))
            ->assertSee(__('To follow up'))
            ->assertSee(__('Responsible adults'))
            ->assertSee(trans_choice('Including :count newcomer|Including :count newcomers', 1, ['count' => 1]));
    });
});
