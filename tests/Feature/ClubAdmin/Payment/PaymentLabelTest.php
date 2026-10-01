<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\GeneratePaymentReference;
use App\Actions\ClubAdmin\Subscriptions\AddMemberToTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\CalculatePriceAction;
use App\Actions\ClubAdmin\Subscriptions\MoveMemberBetweenTrainingPacksAction;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Support\PaymentCovers;
use App\Domains\ClubAdmin\Payment\Support\PaymentCoversBackfill;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Trainings\Models\TrainingPack;
use App\Mail\PaymentInvitationEmail;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| What a subscription payment says it is for
|--------------------------------------------------------------------------
|
| Every subscription payment read "Affiliation 2026-2027", the first one as
| well as the complement for a pack added months later. A parent adding a
| pack to a child already affiliated could not tell what she was asked to
| pay. A payment now records what it bills when it is created.
|
*/

function labelSubscription(array $attributes = []): Subscription
{
    $season = Season::current() ?? makeActiveSeason();

    return Subscription::factory()->for($season, 'season')->for(User::factory(), 'user')
        ->create(array_merge(['is_competitive' => false, 'status' => 'confirmed'], $attributes));
}

function coveredPayment(Subscription $subscription, ?array $covers): Payment
{
    return Payment::factory()->create([
        'payable_type' => Subscription::class,
        'payable_id' => $subscription->id,
        'status' => 'pending',
        'covers' => $covers,
        // The factory draws a date over the last 30 days: the backfill reads
        // the order of the payments from it, so it must be the moment of the test.
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

describe('the label', function (): void {
    beforeEach(function (): void {
        $this->season = makeActiveSeason();
        $this->subscription = labelSubscription();
        $this->pack = TrainingPack::factory()->for($this->season, 'season')->create(['name' => 'Mini-ping du mercredi']);
        $this->other = TrainingPack::factory()->for($this->season, 'season')->create(['name' => 'Jeunes du samedi']);
    });

    it('names the affiliation, and the packs taken with it', function (): void {
        $this->subscription->trainingPacks()->attach($this->pack->id, ['status' => 'enrolled']);

        $label = coveredPayment($this->subscription, PaymentCovers::affiliation($this->subscription))->label();

        expect($label)->toBe(['type' => __('Subscription'), 'name' => $this->season->name . ' + Mini-ping du mercredi']);
    });

    it('names only the affiliation when no pack is taken with it', function (): void {
        $label = coveredPayment($this->subscription, PaymentCovers::affiliation($this->subscription))->label();

        expect($label['name'])->toBe($this->season->name);
    });

    it('names only the pack added to a member already affiliated', function (): void {
        $label = coveredPayment($this->subscription, PaymentCovers::packs([$this->pack]))->label();

        expect($label)->toBe(['type' => __('Training pack'), 'name' => 'Mini-ping du mercredi']);
    });

    it('names every pack a payment bills', function (): void {
        $label = coveredPayment($this->subscription, PaymentCovers::packs([$this->pack, $this->other]))->label();

        expect($label)->toBe(['type' => __('Training packs'), 'name' => 'Mini-ping du mercredi, Jeunes du samedi']);
    });

    it('says a change of formula, or of pack', function (): void {
        expect(coveredPayment($this->subscription, PaymentCovers::formulaChange())->label()['name'])
            ->toBe(__(':name (change of formula)', ['name' => $this->season->name]))
            ->and(coveredPayment($this->subscription, PaymentCovers::packs([$this->other], PaymentCovers::PACK_CHANGE))->label()['name'])
            ->toBe(__(':name (change of pack)', ['name' => 'Jeunes du samedi']));
    });

    it('keeps the name a pack had when it was billed', function (): void {
        $payment = coveredPayment($this->subscription, PaymentCovers::packs([$this->pack]));
        $this->pack->update(['name' => 'Renommé depuis']);

        expect($payment->fresh()->label()['name'])->toBe('Mini-ping du mercredi');
    });

    it('falls back on the affiliation when a payment does not say what it covers', function (): void {
        expect(coveredPayment($this->subscription, null)->label())
            ->toBe(['type' => __('Subscription'), 'name' => $this->season->name]);
    });
});

describe('what a new payment records', function (): void {
    function invoicedFor(TrainingPack $pack): Subscription
    {
        $subscription = Subscription::factory()->for($pack->season, 'season')->for(User::factory(), 'user')
            ->create(['is_competitive' => false, 'status' => 'confirmed']);

        (new CalculatePriceAction)($subscription, 1);
        $subscription->refresh();
        $subscription->payments()->create([
            'reference' => (new GeneratePaymentReference)(),
            'amount_due' => $subscription->amount_due,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

        return $subscription;
    }

    it('records the pack the club adds to a member already invoiced', function (): void {
        Notification::fake();
        $pack = TrainingPack::factory()->create(['max_participants' => 5, 'price' => 90, 'name' => 'Mini-ping du mercredi']);
        $subscription = invoicedFor($pack);

        (new AddMemberToTrainingPackAction)($subscription, $pack);

        expect($subscription->payments()->latest('id')->first()->label())
            ->toBe(['type' => __('Training pack'), 'name' => 'Mini-ping du mercredi']);
    });

    it('records the pack a member moves to, as a change of pack', function (): void {
        Notification::fake();
        Club::factory()->ownClub()->create();
        $from = TrainingPack::factory()->started()->create(['max_participants' => 5, 'price' => 90]);
        $to = TrainingPack::factory()->started()->for($from->season, 'season')->create(['max_participants' => 5, 'price' => 150, 'name' => 'Compétition']);
        $subscription = invoicedFor($from);
        (new AddMemberToTrainingPackAction)($subscription, $from);

        (new MoveMemberBetweenTrainingPacksAction)($subscription, $from, $to);

        expect($subscription->payments()->latest('id')->first()->covers)
            ->toBe(PaymentCovers::packs([$to], PaymentCovers::PACK_CHANGE));
    });
});

describe('the invitation mail', function (): void {
    it('names the pack a parent is asked to pay, and the deadline in French', function (): void {
        $season = makeActiveSeason();
        Club::factory()->ownClub()->create();
        // Tied to the active season: a pack left to its factory draws a season of
        // its own, which overlaps the active one often enough to fail one run in two.
        $pack = TrainingPack::factory()->for($season, 'season')->create(['name' => 'Mini-ping du mercredi']);

        $html = new PaymentInvitationEmail(coveredPayment(labelSubscription(), PaymentCovers::packs([$pack])))->render();

        expect($html)->toContain('Mini-ping du mercredi')
            ->not->toContain(Season::current()->name)
            ->toContain(__('Please make the payment before :date.', ['date' => today()->addDays(30)->format('d/m/Y')]))
            ->not->toContain('Please make the payment');
    });
});

describe('rebuilding the payments made before', function (): void {
    beforeEach(function (): void {
        $this->season = makeActiveSeason();
        $this->subscription = labelSubscription();
        $this->first = TrainingPack::factory()->for($this->season, 'season')->create(['name' => 'Pris au départ']);
        $this->later = TrainingPack::factory()->for($this->season, 'season')->create(['name' => 'Ajouté ensuite']);
    });

    /** Attach a pack and create a payment, both at a given moment. */
    function at(string $when, callable $then): mixed
    {
        Carbon::setTestNow($when);
        $result = $then();
        Carbon::setTestNow();

        return $result;
    }

    it('places the first payment on the affiliation and a later one on the pack added since', function (): void {
        $initial = at('2026-09-01 10:00:00', function () {
            $this->subscription->trainingPacks()->attach($this->first->id, ['status' => 'enrolled']);

            return coveredPayment($this->subscription, null);
        });
        $complement = at('2026-10-15 18:00:00', function () {
            $this->subscription->trainingPacks()->attach($this->later->id, ['status' => 'enrolled']);

            return coveredPayment($this->subscription, null);
        });
        DB::table('payments')->where('id', $initial->id)->update(['created_at' => '2026-09-01 10:00:30']);
        DB::table('payments')->where('id', $complement->id)->update(['created_at' => '2026-10-15 18:00:20']);

        $report = (new PaymentCoversBackfill)->run();

        expect($report)->toBe(['affiliation' => 1, 'packs' => 1, 'unknown' => 0])
            ->and($initial->fresh()->label()['name'])->toBe($this->season->name . ' + Pris au départ')
            ->and($complement->fresh()->label())->toBe(['type' => __('Training pack'), 'name' => 'Ajouté ensuite']);
    });

    it('leaves a later payment with no pack in its window unknown, with the affiliation label', function (): void {
        at('2026-09-01 10:00:00', fn () => coveredPayment($this->subscription, null));
        $formula = at('2026-11-01 10:00:00', fn () => coveredPayment($this->subscription, null));

        $report = (new PaymentCoversBackfill)->run();

        expect($report['unknown'])->toBe(1)
            ->and($formula->fresh()->covers)->toBeNull()
            ->and($formula->fresh()->label()['name'])->toBe($this->season->name);
    });

    it('writes nothing on a dry run, and leaves a payment that already says what it covers', function (): void {
        $old = at('2026-09-01 10:00:00', fn () => coveredPayment($this->subscription, null));
        $new = at('2026-10-01 10:00:00', fn () => coveredPayment($this->subscription, PaymentCovers::packs([$this->later])));

        $report = (new PaymentCoversBackfill)->run(dryRun: true);

        expect($report['affiliation'])->toBe(1)
            ->and($old->fresh()->covers)->toBeNull()
            ->and($new->fresh()->covers)->toBe(PaymentCovers::packs([$this->later]));

        (new PaymentCoversBackfill)->run();
        (new PaymentCoversBackfill)->run();

        expect($old->fresh()->covers['affiliation'])->toBeTrue()
            ->and($new->fresh()->covers)->toBe(PaymentCovers::packs([$this->later]));
    });

    it('prints its report from the command', function (): void {
        at('2026-09-01 10:00:00', fn () => coveredPayment($this->subscription, null));

        $this->artisan('payments:backfill-covers', ['--dry-run' => true])
            ->expectsOutputToContain('1 first payment(s)')
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();
    });

    it('reports on a dry run even before the column exists, as it will be run in production', function (): void {
        at('2026-09-01 10:00:00', fn () => coveredPayment($this->subscription, null));
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('covers');
        });

        expect((new PaymentCoversBackfill)->run(dryRun: true))->toBe(['affiliation' => 1, 'packs' => 0, 'unknown' => 0]);
    });
});
