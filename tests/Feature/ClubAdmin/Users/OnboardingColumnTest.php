<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\CharterSignature;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Trainings\Models\Training;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

pest()->group('club-admin', 'users');

const ONBOARDING_LIST = 'pages::club-admin.users.index';

/*
 * A new member travels five steps before they are settled at the club. The
 * column showing them only appears on the list of the new members: anywhere
 * else most rows would be all ticks, and the column would cost its width for
 * nothing.
 *
 * The season started four months ago, whatever the day the suite runs, so a
 * session ten days ago always falls inside it.
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

/**
 * A member affiliated for the first time this season, who has done none of
 * the five steps yet. Every field a step reads is pinned: the factory draws
 * them at random.
 */
function newcomer(Season $season, string $lastName): User
{
    $member = User::factory()->create([
        'last_name' => $lastName,
        'email_verified_at' => null,
        'last_invited_at' => null,
        'birthdate' => now()->subYears(30),
        'phone_number' => '0470000000',
        'street' => null,
        'city_code' => '1340',
        'city_name' => 'Ottignies',
    ]);

    Subscription::factory()->for($member)->for($season)->create(['status' => 'confirmed']);

    return $member;
}

/** A session the member was ticked present at. */
function onboardingTraining(User $member, int $daysAgo): void
{
    $on = now()->subDays($daysAgo)->setTime(18, 0);

    Training::factory()->create([
        'start' => $on,
        'end' => $on->copy()->addMinutes(90),
        'attendance_taken_at' => $on->copy()->addHours(2),
    ])->trainees()->attach($member->id, ['status' => 'present']);
}

/**
 * The five steps of a member's row in the desktop table, as step => done.
 *
 * @return array<string, bool>
 */
function onboardingStepsOf(string $html, User $member): array
{
    $found = preg_match('/data-onboarding-steps="' . $member->id . '"(.*?)<\/ul>/s', $html, $block);

    expect($found)->toBe(1, "no onboarding steps for {$member->last_name}");

    preg_match_all('/data-onboarding-step="([a-z_]+)"\s+data-done="(true|false)"/', $block[1], $steps, PREG_SET_ORDER);

    return collect($steps)->mapWithKeys(fn (array $step): array => [$step[1] => $step[2] === 'true'])->all();
}

describe('when the column shows', function (): void {
    it('shows on the list of the new members only', function (array $statuses, bool $shows): void {
        newcomer($this->season, 'Nouveau-Arrive');
        newcomer($this->season, 'Nouvelle-Arrivee');

        $component = Livewire::test(ONBOARDING_LIST)->set('affiliation', $statuses);

        expect(collect($component->get('headers'))->pluck('key')->contains('onboarding'))->toBe($shows);

        $shows
            ? $component->assertSeeHtml('data-onboarding-steps=')->assertSeeHtml('data-onboarding-summary=')
            : $component->assertDontSeeHtml('data-onboarding-steps=')->assertDontSeeHtml('data-onboarding-summary=');
    })->with([
        'the new members' => [['new'], true],
        'the new members, ticked twice by an old link' => [['new', 'new'], true],
        'the default view' => [[], false],
        'the new and the renewed' => [['new', 'renewed'], false],
        'the renewed alone' => [['renewed'], false],
    ]);

    it('is headed by its name, which the screen reader reads before each step', function (): void {
        $component = Livewire::test(ONBOARDING_LIST)->set('affiliation', ['new']);

        expect(collect($component->get('headers'))->firstWhere('key', 'onboarding')['label'])->toBe(__('Onboarding'));
    });

    it('takes the place of the ranking and the two dates, which the steps already say', function (): void {
        $component = Livewire::test(ONBOARDING_LIST)->set('affiliation', ['new']);

        expect(collect($component->get('headers'))->pluck('key')->all())->toBe(['name', 'affiliation', 'onboarding', 'status']);
    });
});

describe('the five steps', function (): void {
    it('ticks no step for a member who has done none of them', function (): void {
        $waiting = newcomer($this->season, 'Debutant-Attendu');
        newcomer($this->season, 'Debutante-Attendue');

        $html = Livewire::test(ONBOARDING_LIST)->set('affiliation', ['new'])->html();

        expect(onboardingStepsOf($html, $waiting))->toBe([
            'account' => false,
            'profile' => false,
            'charter' => false,
            'paid' => false,
            'first_visit' => false,
        ]);
    });

    it('ticks each step on its own fact', function (string $step, Closure $fulfil): void {
        $member = newcomer($this->season, 'Pas-A-Pas');
        $other = newcomer($this->season, 'Temoin-Immobile');

        $fulfil($member, $this->season);

        $html = Livewire::test(ONBOARDING_LIST)->set('affiliation', ['new'])->html();

        expect(array_keys(array_filter(onboardingStepsOf($html, $member))))->toBe([$step])
            ->and(array_filter(onboardingStepsOf($html, $other)))->toBe([]);
    })->with([
        'account created' => ['account', fn (User $member) => $member->forceFill(['email_verified_at' => now()->subDay()])->saveQuietly()],
        'profile complete' => ['profile', fn (User $member) => $member->forceFill(['street' => 'Rue du Stade 1'])->saveQuietly()],
        'charter signed' => ['charter', fn (User $member, Season $season) => CharterSignature::sign($member, $season, $member)],
        'paid' => ['paid', fn (User $member, Season $season) => $member->subscriptions()->where('season_id', $season->id)->update(['status' => 'paid'])],
        'first visit' => ['first_visit', fn (User $member) => onboardingTraining($member, 10)],
    ]);

    it('does not count the charter signed for an earlier season', function (): void {
        $member = newcomer($this->season, 'Signe-Autrefois');
        newcomer($this->season, 'Jamais-Signe');

        $earlier = Season::factory()->create([
            'start_at' => now()->subYears(3)->startOfYear(),
            'end_at' => now()->subYears(3)->endOfYear(),
        ]);
        CharterSignature::sign($member, $earlier, $member);

        $html = Livewire::test(ONBOARDING_LIST)->set('affiliation', ['new'])->html();

        expect(onboardingStepsOf($html, $member)['charter'])->toBeFalse();
    });

    it('names every step in words, whether done or not', function (): void {
        $member = newcomer($this->season, 'Lu-A-Voix-Haute');
        newcomer($this->season, 'Ecoute-Aussi');
        $member->forceFill(['email_verified_at' => now()->subDay()])->saveQuietly();

        Livewire::test(ONBOARDING_LIST)->set('affiliation', ['new'])
            ->assertSee(__(':step: done', ['step' => __('Account created')]))
            ->assertSee(__(':step: to do', ['step' => __('Profile complete')]))
            ->assertSee(__(':step: to do', ['step' => __('Charter signed')]))
            ->assertSee(__(':step: to do', ['step' => __('Paid')]))
            ->assertSee(__(':step: to do', ['step' => __('First visit')]));
    });

    it('sums the steps up on a phone, as done out of five', function (): void {
        $member = newcomer($this->season, 'Telephone-Compact');
        newcomer($this->season, 'Telephone-Aussi');
        $member->forceFill(['email_verified_at' => now()->subDay()])->saveQuietly();
        CharterSignature::sign($member, $this->season, $member);

        Livewire::test(ONBOARDING_LIST)->set('affiliation', ['new'])
            ->assertSeeHtml('data-onboarding-summary="' . $member->id . '"')
            ->assertSee(__('Onboarding: :done of :total steps done', ['done' => 2, 'total' => 5]));
    });
});

it('reads the steps of a member fetched on their own', function (): void {
    $member = newcomer($this->season, 'Seul-Charge');
    CharterSignature::sign($member, $this->season, $member);
    onboardingTraining($member, 3);

    expect(User::query()->findOrFail($member->id)->onboardingSteps())->toBe([
        'account' => false,
        'profile' => false,
        'charter' => true,
        'paid' => false,
        'first_visit' => true,
    ]);
});

it('reads the five steps of a whole page in the query that lists it', function (): void {
    $count = function (): int {
        DB::enableQueryLog();
        Livewire::test(ONBOARDING_LIST)->set('affiliation', ['new']);
        $queries = count(DB::getQueryLog());
        DB::flushQueryLog();
        DB::disableQueryLog();

        return $queries;
    };

    newcomer($this->season, 'Premier-Venu');
    newcomer($this->season, 'Second-Venu');

    // The first render warms the caches (current season, permissions).
    $count();
    $few = $count();

    foreach (range(1, 4) as $i) {
        $member = newcomer($this->season, "Accueilli-{$i}");
        $member->forceFill(['email_verified_at' => now()->subDay(), 'street' => 'Rue du Stade 1'])->saveQuietly();
        CharterSignature::sign($member, $this->season, $member);
        $member->subscriptions()->update(['status' => 'paid']);
        onboardingTraining($member, $i);
    }

    expect($count())->toBe($few);
});
