<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Competitions\Tournament\Services\TournamentService;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Livewire\Public\Events\EventList;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function agePricedTournament(): Tournament
{
    return Tournament::factory()->create([
        'status' => TournamentStatusEnum::PUBLISHED,
        'registration_deadline' => null,
        'start_date' => '2026-11-29 13:00',
        'price' => 15,
        'reduced_price' => 10,
        'reduced_price_under_age' => 12,
    ]);
}

describe('Tournament::priceFor()', function (): void {
    it('charges the reduced price to a player under the age limit on the tournament day', function (): void {
        $child = User::factory()->create(['birthdate' => '2015-01-10']);

        expect(agePricedTournament()->priceFor($child))->toBe(10.0);
    });

    it('charges the full price to a player who turns the age limit on the tournament day', function (): void {
        $twelveThatDay = User::factory()->create(['birthdate' => '2014-11-29']);

        expect(agePricedTournament()->priceFor($twelveThatDay))->toBe(15.0);
    });

    it('still charges the reduced price the day before the birthday', function (): void {
        $twelveTheDayAfter = User::factory()->create(['birthdate' => '2014-11-30']);

        expect(agePricedTournament()->priceFor($twelveTheDayAfter))->toBe(10.0);
    });

    it('measures the age on the tournament day, not on the registration day', function (): void {
        $this->travelTo('2026-10-16');
        $elevenTodayTwelveOnTheDay = User::factory()->create(['birthdate' => '2014-11-01']);

        expect(agePricedTournament()->priceFor($elevenTodayTwelveOnTheDay))->toBe(15.0);
    });

    it('charges the full price when the birthdate is unknown', function (): void {
        $unknownAge = User::factory()->create(['birthdate' => null]);

        expect(agePricedTournament()->priceFor($unknownAge))->toBe(15.0);
    });

    it('charges everyone the full price when the tournament has no reduced price', function (): void {
        $tournament = Tournament::factory()->create(['start_date' => '2026-11-29 13:00', 'price' => 15]);
        $child = User::factory()->create(['birthdate' => '2018-05-04']);

        expect($tournament->priceFor($child))->toBe(15.0);
    });
});

describe('registering for an age-priced tournament', function (): void {
    beforeEach(fn () => $this->travelTo('2026-10-16'));

    it('bills a child the reduced price', function (): void {
        $tournament = agePricedTournament();
        $child = User::factory()->create(['birthdate' => '2016-03-02']);

        app(TournamentService::class)->registerUser($tournament, $child);

        $registration = TournamentRegistration::where('tournament_id', $tournament->id)
            ->where('user_id', $child->id)
            ->firstOrFail();

        expect($registration->payment->amount_due)->toEqual(10);
    });
});

describe('paying cash on the tournament day', function (): void {
    it('takes the reduced price from a child', function (): void {
        $this->actingAs(User::factory()->isAdmin()->create());
        $this->travelTo('2026-11-29 12:00');
        $tournament = agePricedTournament();
        $child = User::factory()->create(['birthdate' => '2016-03-02']);
        $tournament->users()->attach($child->id, ['registration_status' => 'registered']);
        $register = CashRegister::create(['name' => 'Main register', 'balance' => 0]);

        app(TournamentService::class)->recordCashPayment($tournament, $child, $register);

        $registration = TournamentRegistration::where('tournament_id', $tournament->id)
            ->where('user_id', $child->id)
            ->firstOrFail();

        expect($registration->payment->amount_paid)->toEqual(10)
            ->and(CashRegisterEntry::where('cash_register_id', $register->id)->value('amount'))->toBe(1000);
    });
});

/**
 * A draft tournament complete enough for the wizard to mount.
 *
 * @param  array<string, mixed>  $overrides
 */
function wizardReadyTournament(array $overrides = []): Tournament
{
    return Tournament::factory()->create(array_merge([
        'status' => TournamentStatusEnum::DRAFT,
        'price' => 15,
        'duration_minutes' => 180,
        'pool_size' => 4,
        'nb_pools' => 2,
        'nb_qualifiers_per_pool' => 2,
        'sets_to_win' => 3,
        'logistics_buffer_minutes' => 3,
        'match_type' => 'double',
        'max_users' => 16,
        'has_handicap_points' => false,
        'deuce_enabled' => false,
    ], $overrides));
}

function wizardSetupOf(Tournament $tournament): Testable
{
    return Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
        ->set('step', '1');
}

describe('setting a reduced price in the wizard', function (): void {
    it('stores the reduced price and its age limit', function (): void {
        $tournament = wizardReadyTournament();

        wizardSetupOf($tournament)
            ->set('reducedPrice', '10')
            ->set('reducedPriceUnderAge', '12')
            ->call('save')
            ->assertHasNoErrors();

        expect($tournament->fresh())
            ->reduced_price->toEqual(10)
            ->reduced_price_under_age->toBe(12);
    });

    it('refuses a reduced price that is not below the full price', function (): void {
        wizardSetupOf(wizardReadyTournament())
            ->set('reducedPrice', '15')
            ->set('reducedPriceUnderAge', '12')
            ->call('save')
            ->assertHasErrors(['reducedPrice' => 'lt']);
    });

    it('refuses a reduced price without its age limit, and the reverse', function (): void {
        wizardSetupOf(wizardReadyTournament())
            ->set('reducedPrice', '10')
            ->call('save')
            ->assertHasErrors(['reducedPriceUnderAge' => 'required_with'])
            ->set('reducedPrice', '')
            ->set('reducedPriceUnderAge', '12')
            ->call('save')
            ->assertHasErrors(['reducedPrice' => 'required_with']);
    });

    it('removes the reduced price when both fields are emptied', function (): void {
        $tournament = wizardReadyTournament(['reduced_price' => 10, 'reduced_price_under_age' => 12]);

        wizardSetupOf($tournament)
            ->assertSet('reducedPrice', '10')
            ->assertSet('reducedPriceUnderAge', '12')
            ->set('reducedPrice', '')
            ->set('reducedPriceUnderAge', '')
            ->call('save')
            ->assertHasNoErrors();

        expect($tournament->fresh()->reduced_price_under_age)->toBeNull();
    });
});

describe('the price a member sees before registering', function (): void {
    beforeEach(function (): void {
        $this->travelTo('2026-10-16');
        makeActiveSeason();
    });

    it('shows a child only the reduced price', function (): void {
        agePricedTournament();
        $child = User::factory()->create(['birthdate' => '2016-03-02']);

        Livewire::actingAs($child)
            ->test('pages::club-admin.users.user-space.event-subscription', ['user' => $child])
            ->assertSee('10,00 €')
            ->assertDontSee('15,00 €');
    });

    it('shows an adult only the full price', function (): void {
        agePricedTournament();
        $adult = User::factory()->create(['birthdate' => '1980-03-02']);

        Livewire::actingAs($adult)
            ->test('pages::club-admin.users.user-space.event-subscription', ['user' => $adult])
            ->assertSee('15,00 €')
            ->assertDontSee('10,00 €');
    });
});

describe('the price announced on the public agenda', function (): void {
    it('announces both prices once the tournament article is published', function (): void {
        $this->travelTo('2026-10-16');
        makeActiveSeason();
        $tournament = wizardReadyTournament([
            'start_date' => '2026-11-29 13:00',
            'reduced_price' => 10,
            'reduced_price_under_age' => 12,
        ]);

        wizardSetupOf($tournament)
            ->set('eventTitle', 'Tournoi de doubles')
            ->set('eventDescription', 'Les paires sont composées par le comité.')
            ->call('saveEventPost', 'published')
            ->assertHasNoErrors();

        Livewire::test(EventList::class)
            ->assertSee('15,00 € (10,00 € pour les moins de 12 ans)');
    });
});
