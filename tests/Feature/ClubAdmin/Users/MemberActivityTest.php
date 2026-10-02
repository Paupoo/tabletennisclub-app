<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Domains\Trainings\Models\Training;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

pest()->group('club-admin', 'users');

const ACTIVITY_LIST = 'pages::club-admin.users.index';

/*
 * The season runs from four months ago to eight months ahead, whatever the
 * day the suite runs: six weeks ago always falls inside it, which a calendar
 * year would not grant in January.
 */
beforeEach(function (): void {
    actingAs(User::factory()->isAdmin()->create(['last_name' => 'Admin-Secretariat']));

    Cache::forget('season.current');
    $this->season = Season::factory()->create([
        'is_active' => true,
        'start_at' => now()->subMonths(4)->startOfDay(),
        'end_at' => now()->addMonths(8)->endOfDay(),
    ]);
});

/** A member affiliated this season, named so no fixture can print it by chance. */
function affiliatedMember(Season $season, string $lastName): User
{
    $member = User::factory()->create(['last_name' => $lastName, 'last_login_at' => null]);

    Subscription::factory()->for($member)->for($season)->create(['status' => 'confirmed']);

    return $member;
}

function attendedTraining(User $member, CarbonInterface $on, string $status = 'present', bool $attendanceTaken = true): void
{
    $training = Training::factory()->create([
        'start' => $on,
        'end' => $on->copy()->addMinutes(90),
        'attendance_taken_at' => $attendanceTaken ? $on->copy()->addHours(2) : null,
    ]);

    $training->trainees()->attach($member->id, ['status' => $status]);
}

/** @param  array<string, mixed>  $pivot */
function interclubEntry(User $member, CarbonInterface $on, array $pivot): void
{
    $interclub = Interclub::factory()->create(['start_date_time' => $on]);

    $interclub->users()->attach($member->id, $pivot);
}

function tournamentEntry(User $member, CarbonInterface $on, string $registration = 'registered', TournamentStatusEnum $status = TournamentStatusEnum::CLOSED): void
{
    $tournament = Tournament::factory()->create([
        'start_date' => $on,
        'end_date' => $on->copy()->addHours(8),
        'status' => $status,
    ]);

    $tournament->users()->attach($member->id, ['registration_status' => $registration]);
}

function lastActivityOf(User $member): ?string
{
    $value = User::query()->withLastActivity()->whereKey($member->id)->value('last_activity_at');

    return $value === null ? null : Carbon::parse($value)->toDateString();
}

describe('what counts as activity', function (): void {
    it('reads a training the member was ticked present at, on the day of the session', function (): void {
        $member = affiliatedMember($this->season, 'Present-Entrainement');
        attendedTraining($member, now()->subDays(10)->setTime(18, 0));

        expect(lastActivityOf($member))->toBe(now()->subDays(10)->toDateString());
    });

    it('ignores a session the member missed, and one whose attendance was never taken', function (): void {
        $member = affiliatedMember($this->season, 'Absent-Entrainement');
        attendedTraining($member, now()->subDays(10), status: 'absent');
        attendedTraining($member, now()->subDays(5), attendanceTaken: false);

        expect(lastActivityOf($member))->toBeNull();
    });

    it('reads a past match the member played or was in the published line-up of', function (): void {
        $played = affiliatedMember($this->season, 'Joueur-Joue');
        interclubEntry($played, now()->subDays(20), ['has_played' => true]);

        $lineup = affiliatedMember($this->season, 'Joueur-Aligne');
        interclubEntry($lineup, now()->subDays(12), ['is_selected' => true, 'selection_confirmed_at' => now()->subDays(14)]);

        expect(lastActivityOf($played))->toBe(now()->subDays(20)->toDateString())
            ->and(lastActivityOf($lineup))->toBe(now()->subDays(12)->toDateString());
    });

    it('ignores a draft line-up, a mere availability, a named walkover and a match still to come', function (): void {
        $member = affiliatedMember($this->season, 'Joueur-Brouillon');
        interclubEntry($member, now()->subDays(20), ['is_selected' => true]);
        interclubEntry($member, now()->subDays(13), ['is_subscribed' => true, 'availability' => 'available']);
        interclubEntry($member, now()->subDays(6), ['is_selected' => true, 'is_walkover' => true, 'selection_confirmed_at' => now()->subDays(8)]);
        interclubEntry($member, now()->addDays(3), ['is_selected' => true, 'selection_confirmed_at' => now()->subDay()]);

        expect(lastActivityOf($member))->toBeNull();
    });

    it('reads a past tournament the member was registered for', function (): void {
        $member = affiliatedMember($this->season, 'Joueur-Tournoi');
        tournamentEntry($member, now()->subDays(30));

        expect(lastActivityOf($member))->toBe(now()->subDays(30)->toDateString());
    });

    it('ignores a cancelled registration, a no-show, a cancelled tournament and one still to come', function (): void {
        $member = affiliatedMember($this->season, 'Joueur-Desiste');
        tournamentEntry($member, now()->subDays(30), registration: 'cancelled');
        tournamentEntry($member, now()->subDays(25), registration: 'no_show');
        tournamentEntry($member, now()->subDays(20), status: TournamentStatusEnum::CANCELLED);
        tournamentEntry($member, now()->addDays(5), status: TournamentStatusEnum::PUBLISHED);

        expect(lastActivityOf($member))->toBeNull();
    });

    it('keeps the latest of the three', function (): void {
        $member = affiliatedMember($this->season, 'Joueur-Complet');
        attendedTraining($member, now()->subDays(40));
        interclubEntry($member, now()->subDays(4), ['has_played' => true]);
        tournamentEntry($member, now()->subDays(15));

        expect(lastActivityOf($member))->toBe(now()->subDays(4)->toDateString());
    });

    it('does not count a sign-in as activity', function (): void {
        $member = affiliatedMember($this->season, 'Joueur-Connecte');
        $member->forceFill(['last_login_at' => now()->subDay()])->saveQuietly();

        expect(lastActivityOf($member))->toBeNull();
    });
});

describe('the members list', function (): void {
    it('shows the last activity and the last sign-in, a dash when there is none', function (): void {
        $active = affiliatedMember($this->season, 'Actif-Recemment');
        attendedTraining($active, now()->subDays(3)->setTime(18, 0));
        $active->forceFill(['last_login_at' => now()->subDays(2)])->saveQuietly();
        affiliatedMember($this->season, 'Inactif-Total');

        $component = Livewire::test(ACTIVITY_LIST);

        $rows = $component->viewData('users')->keyBy('last_name');

        expect(Carbon::parse($rows['Actif-Recemment']->last_activity_at)->toDateString())->toBe(now()->subDays(3)->toDateString())
            ->and($rows['Inactif-Total']->last_activity_at)->toBeNull();

        $component->assertSee(now()->subDays(3)->format('d/m/Y'))
            ->assertSee(now()->subDays(2)->format('d/m/Y'))
            ->assertSee('—')
            ->assertDontSee(__('Never'));
    });

    it('offers both columns from the widths that have room for them', function (): void {
        $headers = collect(Livewire::test(ACTIVITY_LIST)->instance()->headers())->keyBy('key');

        expect($headers['last_activity_at']['class'])->toBe('hidden 2xl:table-cell whitespace-normal')
            ->and($headers['last_activity_at']['sortable'])->toBeTrue()
            ->and($headers['last_login_at']['class'])->toBe('hidden 2xl:table-cell whitespace-normal')
            ->and($headers['last_login_at']['sortable'])->toBeTrue();
    });

    it('sorts by last activity across pages, every member on exactly one of them', function (): void {
        // Twenty members, five of them sharing a day: ties are where a page
        // without a total order loses rows.
        $members = collect(range(1, 20))->map(function (int $i): User {
            $member = affiliatedMember($this->season, 'Trie-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT));

            if ($i <= 15) {
                attendedTraining($member, now()->subDays($i <= 5 ? 7 : $i)->setTime(18, 0));
            }

            return $member;
        });

        $sort = ['column' => 'last_activity_at', 'direction' => 'desc'];

        $page1 = Livewire::withQueryParams(['sortBy' => $sort])->test(ACTIVITY_LIST)->viewData('users');
        $page2 = Livewire::withQueryParams(['sortBy' => $sort, 'page' => 2])->test(ACTIVITY_LIST)->viewData('users');

        $seen = $page1->getCollection()->concat($page2->getCollection())
            ->filter(fn (User $u): bool => str_starts_with($u->last_name, 'Trie-'));

        expect($seen->pluck('id')->sort()->values()->all())->toBe($members->pluck('id')->sort()->values()->all());

        $dates = $seen->pluck('last_activity_at')->filter()->values()->all();
        $sorted = $dates;
        rsort($sorted);

        expect($dates)->toBe($sorted)
            ->and($page1->first()->last_activity_at)->not->toBeNull();
    });

    it('sorts by last sign-in', function (): void {
        affiliatedMember($this->season, 'Connexion-Ancienne')->forceFill(['last_login_at' => now()->subDays(30)])->saveQuietly();
        affiliatedMember($this->season, 'Connexion-Recente')->forceFill(['last_login_at' => now()->subDay()])->saveQuietly();

        $names = Livewire::withQueryParams(['sortBy' => ['column' => 'last_login_at', 'direction' => 'desc']])
            ->test(ACTIVITY_LIST)
            ->viewData('users')
            ->pluck('last_name')
            ->filter(fn (string $name): bool => str_starts_with($name, 'Connexion-'))
            ->values()
            ->all();

        expect($names)->toBe(['Connexion-Recente', 'Connexion-Ancienne']);
    });
});

describe('the activity filter', function (): void {
    beforeEach(function (): void {
        $this->recent = affiliatedMember($this->season, 'Venu-Recemment');
        attendedTraining($this->recent, now()->subDays(5));

        $this->earlier = affiliatedMember($this->season, 'Venu-Ce-Printemps');
        attendedTraining($this->earlier, now()->subWeeks(10));

        $this->lastSeasonOnly = affiliatedMember($this->season, 'Venu-Avant-Saison');
        attendedTraining($this->lastSeasonOnly, $this->season->start_at->copy()->subDays(10));

        $this->nothing = affiliatedMember($this->season, 'Jamais-Venu');

        // Not affiliated this season: no activity is expected of them.
        $this->notAffiliated = User::factory()->create(['last_name' => 'Pas-Affilie']);
    });

    it('finds the affiliated members with no trace since the season began', function (): void {
        $names = Livewire::withQueryParams(['activity' => 'season'])
            ->test(ACTIVITY_LIST)
            ->viewData('users')
            ->pluck('last_name')
            ->sort()
            ->values()
            ->all();

        expect($names)->toBe(['Jamais-Venu', 'Venu-Avant-Saison']);
    });

    it('finds the affiliated members with no trace for six weeks', function (): void {
        $names = Livewire::withQueryParams(['activity' => 'six_weeks'])
            ->test(ACTIVITY_LIST)
            ->viewData('users')
            ->pluck('last_name')
            ->sort()
            ->values()
            ->all();

        expect($names)->toBe(['Jamais-Venu', 'Venu-Avant-Saison', 'Venu-Ce-Printemps']);
    });

    it('keeps the filter around a search', function (): void {
        $names = Livewire::withQueryParams(['activity' => 'season', 'search' => 'Venu'])
            ->test(ACTIVITY_LIST)
            ->viewData('users')
            ->pluck('last_name')
            ->sort()
            ->values()
            ->all();

        expect($names)->toBe(['Jamais-Venu', 'Venu-Avant-Saison']);
    });

    it('reads any other value as no filter, and shows a removable chip', function (): void {
        Livewire::withQueryParams(['activity' => 'tampered'])
            ->test(ACTIVITY_LIST)
            ->assertSee('Venu-Recemment');

        Livewire::withQueryParams(['activity' => 'season'])
            ->test(ACTIVITY_LIST)
            ->assertSee(__('No activity recorded this season'))
            ->call('removeFilter', 'activity')
            ->assertSet('activity', '')
            ->assertSee('Venu-Recemment');
    });

    it('is cleared with the other filters', function (): void {
        Livewire::withQueryParams(['activity' => 'six_weeks'])
            ->test(ACTIVITY_LIST)
            ->call('clearFilters')
            ->assertSet('activity', '');
    });

    it('counts the members without activity this season on a card worded like the filter', function (): void {
        $component = Livewire::test(ACTIVITY_LIST);

        expect($component->viewData('stats')['no_activity'])->toBe(2);

        $component->assertSee(__('No activity recorded'));
    });
});
