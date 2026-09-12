<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Subscriptions\Attestations\Services;

use App\Data\Attestation\AttestationData;
use App\Data\Attestation\MemberIdentifiers;
use App\Domains\Shared\Enums\Mutuality;

/**
 * Every value a form can ask for, written the way a Belgian form expects it.
 *
 * One dictionary for all six documents: the anchor map of each insurer picks
 * the entries it has a place for and ignores the rest. Formatting lives here
 * rather than in the renderer so that « 205,00 » and « 14/09/2026 » are the
 * same string on all of them.
 *
 * The € sign is never printed: every one of the five forms has it preprinted
 * next to the box.
 */
final readonly class AttestationFieldValues
{
    /**
     * @return array<string, string>
     */
    public function for(
        AttestationData $data,
        Mutuality $mutuality,
        MemberIdentifiers $identifiers,
        string $reference,
    ): array {
        $method = $this->canonicalMethod($data->paymentMethod);

        return [
            'member_full_name' => $data->memberFullName,
            'member_first_name' => $data->memberFirstName,
            'member_last_name' => $data->memberLastName,
            'member_address' => $data->memberStreet,
            'member_city' => $data->memberCity,
            'member_birthdate' => $data->memberBirthdate?->format('d/m/Y') ?? '',
            'member_email' => (string) $data->memberEmail,
            'member_phone' => (string) $data->memberPhone,
            'member_nrn' => (string) $identifiers->nationalRegisterNumber,
            'member_mutual_number' => (string) $identifiers->mutualMembershipNumber,

            'club_name' => $data->clubName,
            'club_address' => $data->clubStreet,
            'club_city' => $data->clubCity,
            'club_phone' => (string) $data->clubPhone,
            'club_licence' => $data->clubLicence,
            'federation' => $data->federation,
            'discipline' => $data->discipline,
            'signatory_name' => $data->signatoryName,

            'amount' => number_format($data->amountPaid, 2, ',', ' '),
            // Several forms draw the euros and the cents as separate boxes with
            // their own comma between them, so the one string will not do.
            'amount_euros' => number_format(floor($data->amountPaid), 0, ',', ' '),
            'amount_cents' => str_pad((string) (int) round(fmod($data->amountPaid, 1) * 100), 2, '0', STR_PAD_LEFT),
            'period_from' => $data->periodFrom->format('d/m/Y'),
            'period_from_day' => $data->periodFrom->format('d'),
            'period_from_month' => $data->periodFrom->format('m'),
            'period_from_year' => $data->periodFrom->format('Y'),
            'period_to' => $data->periodTo->format('d/m/Y'),
            'validated_on' => $data->periodFrom->format('d/m/Y'),
            'season_label' => $data->seasonLabel,
            'season_year_1' => (string) $data->periodFrom->year,
            'season_year_2' => (string) $data->periodTo->year,
            'issued_on' => $data->issuedOn->format('d/m/Y'),
            'payment_method' => $this->paymentMethod($data->paymentMethod),

            'reference' => $reference,
            'mutuality' => $mutuality->label(),

            // Tick boxes. The club only ever declares one thing — a club
            // affiliation — so that box is always crossed; the payment boxes
            // follow whichever way the money actually came in.
            'mark_affiliation' => 'X',
            'mark_transfer' => $method === 'transfer' ? 'X' : '',
            'mark_cash' => $method === 'cash' ? 'X' : '',
            'mark_other' => $method !== null && ! in_array($method, ['transfer', 'cash'], true) ? 'X' : '',
        ];
    }

    /**
     * The three answers a form offers, whatever the treasury called it.
     */
    private function canonicalMethod(?string $method): ?string
    {
        return match ($method) {
            null, '' => null,
            'transfer', 'bank_transfer', 'sepa', 'bank' => 'transfer',
            'cash' => 'cash',
            default => 'other',
        };
    }

    /**
     * How the money arrived, in the words Mutualité Neutre offers as boxes.
     */
    private function paymentMethod(?string $method): string
    {
        return match ($this->canonicalMethod($method)) {
            'transfer' => __('bank transfer'),
            'cash' => __('cash'),
            null => '',
            default => __('other'),
        };
    }
}
