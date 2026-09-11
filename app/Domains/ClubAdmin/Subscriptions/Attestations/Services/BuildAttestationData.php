<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Subscriptions\Attestations\Services;

use App\Data\Attestation\AttestationData;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\AttestationSetting;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Competitions\Interclub\Models\Club;
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
            clubPhone: $club->phone_contact,
            clubLicence: (string) $club->licence,
            federation: $settings->federation_name,
            signatoryName: (string) $settings->signatory?->full_name,
            issuedOn: Carbon::today(),
        );
    }

    private function address(?string $street, ?string $cityCode, ?string $cityName): string
    {
        $city = trim(implode(' ', array_filter([$cityCode, $cityName])));

        return trim(implode(', ', array_filter([$street, $city === '' ? null : $city])));
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
