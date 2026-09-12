<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Subscriptions\Attestations\Services;

use App\Data\Attestation\AttestationData;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\AttestationSetting;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use Illuminate\Support\Carbon;

/**
 * Gathers what the club will certify about one affiliation.
 *
 * Two choices worth stating, both settled against the five real forms:
 *
 * The period runs from the day the committee validated the affiliation to the
 * end of the season. Printing the season's own start would have the club
 * certify that a member who joined in January was affiliated on 1 September.
 * Partenamut asks for that single validation date and nothing else, which is
 * exactly what `confirmed_at` holds.
 *
 * The amount is what the member actually paid, refunds deducted — the only
 * figure the club can match against a bank statement. Eligibility already
 * requires the affiliation to be settled in full, so it equals what was due.
 * The breakdown is carried alongside for the forms that have room for it.
 */
final readonly class BuildAttestationData
{
    /** What a rehearsal prints where a real amount would go. */
    private const float SAMPLE_AMOUNT = 125.0;

    public function for(Subscription $affiliation): AttestationData
    {
        $member = $affiliation->user;
        $season = $affiliation->season;
        $settings = AttestationSetting::current();
        $club = Club::ourClub()->firstOrFail();

        $cotisation = (float) $affiliation->subscription_price;
        $familyCredit = (float) $affiliation->family_credit;

        // amount_due = cotisation + trainings − family credit, so the training
        // side is what remains once the other two are taken back out.
        $trainingsTotal = round((float) $affiliation->amount_due - $cotisation + $familyCredit, 2);

        return new AttestationData(
            memberFullName: $member->full_name,
            memberFirstName: (string) $member->first_name,
            memberLastName: (string) $member->last_name,
            memberBirthdate: $member->birthdate,
            memberAddress: $this->address($member->street, $member->city_code, $member->city_name),
            memberStreet: (string) $member->street,
            memberCity: $this->city($member->city_code, $member->city_name),
            memberEmail: $member->email,
            memberPhone: $member->phone_number,
            periodFrom: Carbon::parse($affiliation->confirmed_at ?? $affiliation->created_at),
            periodTo: Carbon::parse($season->end_at),
            seasonLabel: (string) $season->name,
            amountPaid: $affiliation->netAmountPaid(),
            cotisation: $cotisation,
            trainingsTotal: $trainingsTotal,
            familyCredit: $familyCredit,
            paymentMethod: $this->paymentMethod($affiliation),
            discipline: $settings->discipline,
            clubName: (string) $club->name,
            clubAddress: $this->address($club->street, $club->city_code, $club->city_name),
            clubStreet: (string) $club->street,
            clubCity: $this->city($club->city_code, $club->city_name),
            clubPhone: $club->phone_contact,
            clubLicence: (string) $club->licence,
            federation: $settings->federation_name,
            signatoryName: (string) $settings->signatory?->full_name,
            issuedOn: Carbon::today(),
        );
    }

    /**
     * The same document, for somebody who has nothing to certify.
     *
     * The office needs to see its seal on a form before a member ever asks,
     * and whoever is checking is rarely an affiliated, fully paid member of
     * the current season. Their real identity is used — that is what makes the
     * rehearsal worth looking at — and the money is a stated placeholder.
     *
     * Built here rather than in the preview action so that the club record,
     * the settings and the address formatting are assembled in one place only,
     * and the rehearsal cannot drift away from the real thing.
     */
    public function sampleFor(User $viewer, Season $season): AttestationData
    {
        $settings = AttestationSetting::current();
        $club = Club::ourClub()->firstOrFail();

        return new AttestationData(
            memberFullName: $viewer->full_name,
            memberFirstName: (string) $viewer->first_name,
            memberLastName: (string) $viewer->last_name,
            memberBirthdate: $viewer->birthdate,
            memberAddress: $this->address($viewer->street, $viewer->city_code, $viewer->city_name),
            memberStreet: (string) $viewer->street,
            memberCity: $this->city($viewer->city_code, $viewer->city_name),
            memberEmail: $viewer->email,
            memberPhone: $viewer->phone_number,
            periodFrom: Carbon::parse($season->start_at),
            periodTo: Carbon::parse($season->end_at),
            seasonLabel: (string) $season->name,
            amountPaid: self::SAMPLE_AMOUNT,
            cotisation: self::SAMPLE_AMOUNT,
            trainingsTotal: 0.0,
            familyCredit: 0.0,
            paymentMethod: 'transfer',
            discipline: $settings->discipline,
            clubName: (string) $club->name,
            clubAddress: $this->address($club->street, $club->city_code, $club->city_name),
            clubStreet: (string) $club->street,
            clubCity: $this->city($club->city_code, $club->city_name),
            clubPhone: $club->phone_contact,
            clubLicence: (string) $club->licence,
            federation: $settings->federation_name,
            signatoryName: (string) $settings->signatory?->full_name,
            issuedOn: Carbon::today(),
        );
    }

    private function address(?string $street, ?string $cityCode, ?string $cityName): string
    {
        $city = $this->city($cityCode, $cityName);

        return trim(implode(', ', array_filter([$street, $city === '' ? null : $city])));
    }

    private function city(?string $cityCode, ?string $cityName): string
    {
        return trim(implode(' ', array_filter([$cityCode, $cityName])));
    }

    /**
     * How the cotisation reached the club — Mutualité Neutre asks.
     *
     * The last incoming payment answers it: a cotisation settled in two goes
     * was still settled the way the member last chose, and the form offers one
     * box, not a history.
     */
    private function paymentMethod(Subscription $affiliation): ?string
    {
        return $affiliation->payments()
            ->whereIn('status', ['paid', 'refunded'])
            ->where(fn ($query) => $query->where('payment_method', '!=', 'refund')->orWhereNull('payment_method'))
            ->latest('id')
            ->value('payment_method');
    }
}
