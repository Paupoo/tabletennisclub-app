<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Subscriptions\Attestations\Templates;

use App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf\Anchor;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf\AnchorPlacement as Place;
use App\Domains\Shared\Enums\Mutuality;

/**
 * Where each value goes on each insurer's form, said in the form's own words.
 *
 * Every entry names a label the insurer printed, not a coordinate. The offsets
 * that follow are the only tuning: how far past the label the rule it belongs
 * to begins. A revision that moves the block down the page keeps working; one
 * that rewords the label surfaces as an unresolved anchor on the test screen,
 * which is the point.
 *
 * Three of the five carry AcroForm fields, and they are deliberately unused:
 * on Partenamut and Mutualité Neutre those fields cover only the member's own
 * block — the part the club cannot fill — and importing a page through FPDI
 * flattens them away in any case.
 */
final readonly class AnchorMaps
{
    /**
     * @return array<string, Anchor>
     */
    public static function for(Mutuality $mutuality): array
    {
        return match ($mutuality) {
            Mutuality::MC => self::mc(),
            Mutuality::MutPlus => self::mutplus(),
            Mutuality::Neutral => self::neutral(),
            Mutuality::Partenamut => self::partenamut(),
            Mutuality::Solidaris => self::solidaris(),
            Mutuality::Other => [],
        };
    }

    /**
     * @return array<string, Anchor>
     */
    private static function mc(): array
    {
        return [
            'club_name' => new Anchor("Nom du club, de l'infrastructure ou de l'association", dx: 3),
            'club_address' => new Anchor('Adresse du siège', dx: 3),
            'discipline' => new Anchor("Activité pratiquée par l'affilié ou nom de l'événement sportif", dx: 3),
            'signatory_name' => new Anchor("Nom du responsable de l'activité", dx: 3),
            'member_full_name' => new Anchor("Certifie sur l'honneur que", dx: 3),
            'amount' => new Anchor('a bien payé la somme de', dx: 3),
            'period_from' => new Anchor('abonnement couvrant la période du', dx: 2),
            'period_to' => new Anchor('abonnement couvrant la période du', dx: 40),
            // Three "Date" on this form: the member's date of birth, the date
            // they sign, and the club's. Two "Signature": the member's and the
            // club's. The club is last in both cases.
            'issued_on' => new Anchor('Date', occurrence: 3, dx: 3),
            'signature' => new Anchor('Signature', occurrence: 2, dx: 4, dy: -3),
            'seal' => new Anchor('Cachet', placement: Place::Below, dy: 1),
        ];
    }

    /**
     * @return array<string, Anchor>
     */
    private static function mutplus(): array
    {
        return [
            'signatory_name' => new Anchor('Je soussigné', dx: 3),
            'member_full_name' => new Anchor('Nom et prénom', placement: Place::After, dx: 6),
            'member_address' => new Anchor('Adresse', occurrence: 1, dx: 6),
            'member_nrn' => new Anchor('Numéro de registre national', dx: 6),
            'member_email' => new Anchor('Adresse e-mail', dx: 6),
            'period_from' => new Anchor('en date du', dx: 3),
            // The form welds its own rule to the word: « de__________________ euros ».
            // Naming both words is what makes this « de » the right one.
            'amount' => new Anchor('de euros', placement: Place::At, dx: 2),
            'season_year_1' => new Anchor("pour l'année sportive", dx: 2),
            'season_year_2' => new Anchor("pour l'année sportive", dx: 14),
            'discipline' => new Anchor('est inscrite dans notre club pour le sport', dx: 3),
            'club_name' => new Anchor('Nom, adresse et/ou cachet du club de sport', placement: Place::Below, dy: 1),
            // The first "date" belongs to « en date du », which is the payment
            // date, not the signing date.
            'issued_on' => new Anchor('Date', occurrence: 2, dx: 3),
            'signature' => new Anchor('Signature du responsable', dx: 4, dy: -3),
            'seal' => new Anchor('Nom, adresse et/ou cachet du club de sport', placement: Place::Below, dy: 10),
        ];
    }

    /**
     * @return array<string, Anchor>
     */
    private static function neutral(): array
    {
        return [
            'member_last_name' => new Anchor('Nom', occurrence: 1, dx: 3),
            'member_first_name' => new Anchor('Prénom', dx: 3),
            'member_nrn' => new Anchor('N° registre national', dx: 3),
            'member_address' => new Anchor('Rue, N° (Bte)', dx: 3),
            'member_city' => new Anchor('Code Postal et localité', dx: 3),
            'member_phone' => new Anchor('N° fixe ou GSM', dx: 3),
            'member_email' => new Anchor('Email', dx: 3),
            'signatory_name' => new Anchor("Je soussigné, (nom du responsable de l'organisation)", dx: 3),
            'club_name' => new Anchor("représentant autorisé de (nom de l'organisation)", dx: 3),
            'club_address' => new Anchor("situé(e) à (adresse de l'organisation)", dx: 3),
            'member_full_name' => new Anchor('(nom et prénom du participant)', dx: 3),
            'discipline' => new Anchor('avec comme discipline', dx: 3),
            'amount' => new Anchor('le montant de', dx: 3),
            'validated_on' => new Anchor('en date du', dx: 3),
            'period_from' => new Anchor('Période couverte par le paiement : du', dx: 2),
            'period_to' => new Anchor('Période couverte par le paiement : du', dx: 32),
            'issued_on' => new Anchor('Date de signature', dx: 3),
            'seal' => new Anchor('Cachet et signature du club', placement: Place::Below, dy: 2),
            'signature' => new Anchor('Cachet et signature du club', placement: Place::Below, dx: 30, dy: 6),
        ];
    }

    /**
     * @return array<string, Anchor>
     */
    private static function partenamut(): array
    {
        return [
            'club_name' => new Anchor('La direction du club sportif', dx: 3),
            'federation' => new Anchor('affilié à la Fédération/Ligue', dx: 3),
            'member_full_name' => new Anchor('certifie que (nom et prénom)', dx: 3),
            'period_from' => new Anchor('est affiliée à partir du', dx: 3),
            'discipline' => new Anchor('Sport pratiqué', dx: 3),
            'amount' => new Anchor('Montant payé', dx: 3),
            'seal' => new Anchor('Cachet du club sportif', placement: Place::Below, dy: 2),
            'signature' => new Anchor('Signature du responsable du club', placement: Place::Below, dy: 2),
        ];
    }

    /**
     * @return array<string, Anchor>
     */
    private static function solidaris(): array
    {
        return [
            'signatory_name' => new Anchor('Je soussigné.e', dx: 3),
            // « Nom » three times: the beneficiary's block, the association's,
            // then the beneficiary again inside the club's declaration.
            'club_name' => new Anchor('Nom', occurrence: 2, dx: 3),
            'club_address' => new Anchor('Adresse', occurrence: 1, dx: 3),
            'club_city' => new Anchor('Code postal et localité', dx: 3),
            'club_phone' => new Anchor('N° de téléphone', dx: 3),
            'member_full_name' => new Anchor('Nom et prénom du/de la bénéficiaire du service', dx: 3),
            'amount' => new Anchor("Certifie sur l'honneur que la somme de", dx: 3),
            'period_from' => new Anchor('pour la période du', dx: 2),
            'period_to' => new Anchor('pour la période du', dx: 30),
            'discipline' => new Anchor('pour la pratique du sport suivant', dx: 3),
            'issued_on' => new Anchor('Date', occurrence: 1, dx: 3),
            'seal' => new Anchor('Signature et cachet', placement: Place::Below, dy: 2),
            'signature' => new Anchor('Signature et cachet', placement: Place::Below, dx: 30, dy: 6),
        ];
    }
}
