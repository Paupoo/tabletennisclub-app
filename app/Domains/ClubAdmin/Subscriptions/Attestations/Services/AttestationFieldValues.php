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
            // MC prints the member's name twice: once in their own block, once
            // inside the club's sworn statement. Two anchors, one value.
            'member_full_name_club' => $data->memberFullName,
            'member_first_name' => $data->memberFirstName,
            'member_last_name' => $data->memberLastName,
            'member_address' => $data->memberStreet,
            'member_city' => $data->memberCity,
            // « Fait à … » wants the town on its own; the postcode belongs on
            // the address line, not after a preposition.
            'member_town' => trim((string) preg_replace('/^\d+\s*/', '', $data->memberCity)),
            'member_birthdate' => $data->memberBirthdate?->format('d/m/Y') ?? '',
            // Several forms draw a date as three boxes with their own slashes.
            'member_birthdate_day' => $data->memberBirthdate?->format('d') ?? '',
            'member_birthdate_month' => $data->memberBirthdate?->format('m') ?? '',
            'member_birthdate_year' => $data->memberBirthdate?->format('Y') ?? '',
            'member_email' => (string) $data->memberEmail,
            'member_phone' => (string) $data->memberPhone,
            'member_nrn' => (string) $identifiers->nationalRegisterNumber,
            ...$this->digitCells('member_nrn', (string) $identifiers->nationalRegisterNumber, 11),
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
            // Partenamut draws the amount and the date as single-character
            // cells, so each digit needs a key and a place of its own.
            ...$this->digitCells('amount_euros', (string) (int) floor($data->amountPaid), 4, rightAlign: true),
            ...$this->digitCells('amount_cents', str_pad((string) (int) round(fmod($data->amountPaid, 1) * 100), 2, '0', STR_PAD_LEFT), 2),
            ...$this->digitCells('period_from_day', $data->periodFrom->format('d'), 2),
            ...$this->digitCells('period_from_month', $data->periodFrom->format('m'), 2),
            ...$this->digitCells('period_from_year', $data->periodFrom->format('Y'), 4),
            'period_from' => $data->periodFrom->format('d/m/Y'),
            'period_from_day' => $data->periodFrom->format('d'),
            'period_from_month' => $data->periodFrom->format('m'),
            'period_from_year' => $data->periodFrom->format('Y'),
            'period_to' => $data->periodTo->format('d/m/Y'),
            'period_to_day' => $data->periodTo->format('d'),
            'period_to_month' => $data->periodTo->format('m'),
            'period_to_year' => $data->periodTo->format('Y'),
            'validated_on' => $data->periodFrom->format('d/m/Y'),
            'season_label' => $data->seasonLabel,
            'season_year_1' => (string) $data->periodFrom->year,
            'season_year_2' => (string) $data->periodTo->year,
            'issued_on' => $data->issuedOn->format('d/m/Y'),
            'issued_day' => $data->issuedOn->format('d'),
            // The same day, where a form asks the member to date their own
            // declaration as well as the club to date its statement.
            'member_signed_day' => $data->issuedOn->format('d'),
            'member_signed_month' => $data->issuedOn->format('m'),
            'member_signed_year' => $data->issuedOn->format('Y'),
            'issued_month' => $data->issuedOn->format('m'),
            'issued_year' => $data->issuedOn->format('Y'),
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
        // The treasury writes these as free strings, not an enum, and the
        // spellings in use are « Wire », « Cash » and « electronic ». Reading
        // them literally ticked « autre » on every transfer the club ever
        // received — which is nearly all of them.
        return match (mb_strtolower(trim((string) $method))) {
            '' => null,
            'wire', 'transfer', 'bank_transfer', 'bank', 'sepa', 'electronic' => 'transfer',
            'cash' => 'cash',
            default => 'other',
        };
    }

    /**
     * One digit per key, for a form that draws a number as separate boxes.
     *
     * Solidaris prints the national register number as eleven single-character
     * cells. Written as one string it would run across every border on the
     * line; written a digit at a time it lands in the boxes the form drew.
     *
     * @return array<string, string>
     */
    private function digitCells(string $prefix, string $value, int $count, bool $rightAlign = false): array
    {
        $digits = str_split(str_pad(
            mb_substr((string) preg_replace('/\D/', '', $value), 0, $count),
            $count,
            ' ',
            $rightAlign ? STR_PAD_LEFT : STR_PAD_RIGHT,
        ));

        $cells = [];

        foreach ($digits as $index => $digit) {
            $cells[$prefix . '_' . ($index + 1)] = trim($digit);
        }

        return $cells;
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
