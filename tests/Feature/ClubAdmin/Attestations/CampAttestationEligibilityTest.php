<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Services\CampAttestationEligibility;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Services\CampDaysCounter;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\AttestationRefusal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Mail::fake();
    Notification::fake();
    Club::factory()->ownClub()->create();
    Carbon::setTestNow('2026-10-30 10:00:00');

    $this->season = Season::factory()->create([
        'name' => '2026-2027',
        'is_active' => true,
        'start_at' => '2026-07-01',
        'end_at' => '2027-06-30',
    ]);
});

afterEach(fn () => Carbon::setTestNow());

describe('the days a camp counts', function (): void {
    it('counts each date a session was held once', function (): void {
        $camp = campAttestationCamp($this->season, [
            ['2026-10-20 09:00', '2026-10-20 12:00'],
            ['2026-10-20 13:00', '2026-10-20 16:00'],
            ['2026-10-21 09:00', '2026-10-21 16:00'],
        ]);
        $line = campAttestationLine(campAttestationAffiliation($this->season), $camp);

        $days = app(CampDaysCounter::class)->for($line);

        expect($days->participationDays())->toBe(2)
            ->and($days->fullDays())->toBe(2)
            ->and($days->halfDays())->toBe(0)
            ->and($days->first()->toDateString())->toBe('2026-10-20')
            ->and($days->last()->toDateString())->toBe('2026-10-21');
    });

    it('reads a date under four hours from first start to last end as a half day', function (): void {
        $camp = campAttestationCamp($this->season, [
            ['2026-10-20 09:00', '2026-10-20 12:00'],
            ['2026-10-21 09:00', '2026-10-21 13:00'],
        ]);
        $line = campAttestationLine(campAttestationAffiliation($this->season), $camp);

        $days = app(CampDaysCounter::class)->for($line);

        expect($days->halfDays())->toBe(1)
            ->and($days->fullDays())->toBe(1)
            ->and($days->countsInHalfDays())->toBeTrue()
            // A mixed camp is counted in half days: one day is two of them.
            ->and($days->inFormUnits())->toBe(3);
    });

    it('leaves out a session that was called off', function (): void {
        $camp = campAttestationCamp($this->season, [
            ['2026-10-20 09:00', '2026-10-20 16:00'],
            ['2026-10-21 09:00', '2026-10-21 16:00', 'cancelled_closed'],
        ]);
        $line = campAttestationLine(campAttestationAffiliation($this->season), $camp);

        expect(app(CampDaysCounter::class)->for($line)->participationDays())->toBe(1);
    });

    it('stops at the day a member left', function (): void {
        $line = campAttestationLine(campAttestationAffiliation($this->season), campAttestationCamp($this->season));
        $line->forceFill(['status' => 'left', 'ends_on' => '2026-10-21'])->save();

        expect(app(CampDaysCounter::class)->for($line->refresh())->participationDays())->toBe(2);
    });
});

describe('when the club certifies a camp', function (): void {
    it('certifies a finished camp paid in full, for a member in order', function (): void {
        $line = campAttestationLine(campAttestationAffiliation($this->season), campAttestationCamp($this->season));

        $verdict = app(CampAttestationEligibility::class)->for($line);

        expect($verdict->allowed)->toBeTrue()
            ->and($verdict->line->is($line))->toBeTrue()
            ->and(app(CampAttestationEligibility::class)->certifiedAmount($line))->toBe(80.0);
    });

    it('waits for the day after the last session', function (): void {
        Carbon::setTestNow('2026-10-24 18:00:00');
        $line = campAttestationLine(campAttestationAffiliation($this->season), campAttestationCamp($this->season));

        $verdict = app(CampAttestationEligibility::class)->for($line);

        expect($verdict->allowed)->toBeFalse()
            ->and($verdict->refusal)->toBe(AttestationRefusal::CampNotOver)
            ->and($verdict->availableOn->toDateString())->toBe('2026-10-25');
    });

    it('names what is still owed on the camp', function (): void {
        $line = campAttestationLine(campAttestationAffiliation($this->season), campAttestationCamp($this->season), paid: false);
        $claim = $line->payments()->first();
        (new AllocateTransactionAction)->credit($claim, 50.0, 'cash');

        $verdict = app(CampAttestationEligibility::class)->for($line->refresh());

        expect($verdict->refusal)->toBe(AttestationRefusal::CampBalanceDue)
            ->and($verdict->balanceDue)->toBe(30.0);
    });

    it('refuses a member whose affiliation is not paid in full', function (): void {
        $line = campAttestationLine(campAttestationAffiliation($this->season, paid: 100.0), campAttestationCamp($this->season));

        $verdict = app(CampAttestationEligibility::class)->for($line);

        expect($verdict->refusal)->toBe(AttestationRefusal::BalanceDue)
            ->and($verdict->balanceDue)->toBe(25.0);
    });

    it('certifies what stayed with the club once a refund is committed', function (): void {
        $line = campAttestationLine(campAttestationAffiliation($this->season), campAttestationCamp($this->season));
        $line->payments()->create([
            'reference' => 'REFUND-1',
            'amount_due' => 20,
            'amount_paid' => 0,
            'status' => 'to_refund',
            'payment_method' => 'refund',
        ]);
        $line->forceFill(['override_amount' => 6000, 'override_reason' => 'Two days missed'])->save();

        $eligibility = app(CampAttestationEligibility::class);

        expect($eligibility->for($line->refresh())->allowed)->toBeTrue()
            ->and($eligibility->certifiedAmount($line))->toBe(60.0);
    });

    it('lists only camps billed on their own line, ended within twelve months', function (): void {
        $affiliation = campAttestationAffiliation($this->season);
        $recent = campAttestationLine($affiliation, campAttestationCamp($this->season));

        $old = campAttestationCamp($this->season, [['2025-09-01 09:00', '2025-09-01 16:00']]);
        $oldLine = campAttestationLine($affiliation, $old);

        $inside = campAttestationCamp($this->season);
        $insideLine = campAttestationLine($affiliation, $inside);
        $insideLine->forceFill(['invoiced_separately' => false])->save();

        $lines = app(CampAttestationEligibility::class)->linesFor($affiliation->user);

        expect($lines->pluck('id')->all())->toBe([$recent->id])
            ->and($lines->pluck('id'))->not->toContain($oldLine->id);
    });
});
