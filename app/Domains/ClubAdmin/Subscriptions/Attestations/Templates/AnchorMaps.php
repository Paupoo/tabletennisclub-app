<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Subscriptions\Attestations\Templates;

use App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf\Anchor;
use App\Domains\Shared\Enums\AttestationAnchorPlacement as Place;
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
            // « À compléter par le bénéficiaire ». The club holds every one of
            // these except the national register number, which the member types
            // at the moment of the request and which is never stored. Only the
            // signature is left blank — that one is theirs to write.
            'member_full_name' => new Anchor('Nom et prénom', dx: 3),
            'member_birthdate_day' => new Anchor('Date de naissance', dx: 2.5),
            'member_birthdate_month' => new Anchor('Date de naissance', dx: 13),
            'member_birthdate_year' => new Anchor('Date de naissance', dx: 24),
            'member_nrn' => new Anchor('Numéro de registre national', dx: 3),
            'member_address' => new Anchor('Rue et n°', dx: 3),
            'member_city' => new Anchor('CP et localité', dx: 3),
            'member_phone' => new Anchor('Tél.', dx: 3),
            'member_email' => new Anchor('E-mail', dx: 3),
            // The beneficiary's own date, three boxes. « Date » appears three
            // times on this form: the birth date, this one, and the club's.
            'issued_day' => new Anchor('Date', occurrence: 2, dx: 3),
            'issued_month' => new Anchor('Date', occurrence: 2, dx: 15.5),
            'issued_year' => new Anchor('Date', occurrence: 2, dx: 27.5),

            'club_name' => new Anchor("Nom du club, de l'infrastructure ou de l'association", dx: 3),
            'club_address' => new Anchor('Adresse du siège', dx: 3),
            'discipline' => new Anchor("Activité pratiquée par l'affilié ou nom de l'événement sportif", dx: 3),
            'signatory_name' => new Anchor("Nom du responsable de l'activité", dx: 3),
            'member_full_name_club' => new Anchor("Certifie sur l'honneur que", dx: 3),
            // The form draws « [euros] , [cents] € », so one string would
            // straddle the comma it prints itself.
            'amount_euros' => new Anchor('a bien payé la somme de', dx: 5),
            'amount_cents' => new Anchor('a bien payé la somme de', dx: 28),
            'period_from' => new Anchor('abonnement couvrant la période du', dx: 2),
            'period_to' => new Anchor('abonnement couvrant la période du', dx: 40),
            // Three "Date" on this form: the member's date of birth, the date
            // they sign, and the club's. Two "Signature": the member's and the
            // club's. The club is last in both cases.
            'issued_on' => new Anchor('Date', occurrence: 3, dx: 3),
            'signature' => new Anchor('Signature', occurrence: 2, dx: 4, dy: -3),
            'seal' => new Anchor('Cachet', placement: Place::Below, dx: 2, dy: 0, scale: 0.6),
        ];
    }

    /**
     * @return array<string, Anchor>
     */
    private static function mutplus(): array
    {
        return [
            'signatory_name' => new Anchor('Je soussigné', dx: 3),

            // « Vos données » is a two-column table: the labels run from 34,9 to
            // 67,1 mm, and every value belongs in the same column past them.
            'member_full_name' => new Anchor('Nom et prénom', column: 72.5),
            'member_address' => new Anchor('Adresse', occurrence: 1, column: 72.5),
            'member_nrn' => new Anchor('Numéro de registre national', column: 72.5),
            'member_email' => new Anchor('Adresse e-mail', column: 72.5),
            'period_from' => new Anchor('en date du', dx: 3),
            // The form welds its own rule to the word: « de__________________ euros ».
            // Naming both words is what makes this « de » the right one, and the
            // offset clears the printed « de » before the rule begins.
            'amount' => new Anchor('de euros', placement: Place::At, dx: 6),
            // « sportive____- _____.et » — two rules welded to the words around
            // them, so both years are placed from the label that precedes them.
            'season_year_1' => new Anchor("pour l'année", dx: 13),
            'season_year_2' => new Anchor("pour l'année", dx: 22),
            'mark_affiliation' => new Anchor('est inscrite dans notre club pour le sport', placement: Place::At, dx: -4),
            'discipline' => new Anchor('est inscrite dans notre club pour le sport', dx: 3),
            // The first "date" belongs to « en date du », which is the payment
            // date, not the signing date.
            'issued_on' => new Anchor('Date', occurrence: 2, dx: 3),
            'signature' => new Anchor('Signature du responsable', dx: 4, dy: -3),
            // The frame reads « Nom, adresse **et/ou** cachet » — the seal alone
            // answers it, so the club's name is not printed a second time over
            // the frame's own wording. It goes in the empty right-hand cell.
            'seal' => new Anchor('Nom, adresse et/ou cachet du club de sport', dx: 31, dy: -5.5, scale: 0.53),
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
            'mark_affiliation' => new Anchor('une affiliation au club sportif', placement: Place::At, dx: -4),
            'discipline' => new Anchor('avec comme discipline', dx: 3),
            'amount' => new Anchor('le montant de', dx: 3),
            'validated_on' => new Anchor('en date du', dx: 3),
            'mark_transfer' => new Anchor('virement', placement: Place::At, dx: -4),
            'mark_cash' => new Anchor('en espèce', placement: Place::At, dx: -4),
            'mark_other' => new Anchor('autre (précisez)', placement: Place::At, dx: -4),
            'period_from' => new Anchor('Période couverte par le paiement : du', dx: 2),
            'period_to' => new Anchor('Période couverte par le paiement : du', dx: 32),
            'issued_on' => new Anchor('Date de signature', dx: 3),
            // The shallowest frame of the five — barely a centimetre under its
            // caption. Both marks go beside the caption rather than under it,
            // and shrink, so nothing spills onto the statutory text below.
            'seal' => new Anchor('Cachet et signature du club', dx: 4, dy: -6, scale: 0.35),
            'signature' => new Anchor('Cachet et signature du club', dx: 14, dy: -4, scale: 0.38),
        ];
    }

    /**
     * @return array<string, Anchor>
     */
    private static function partenamut(): array
    {
        return [
            // « Coordonnées du client ». The form carries AcroForm fields for
            // exactly this block and they are unusable — importing a page
            // through FPDI flattens them away — so it is overlaid like the rest.
            'member_mutual_number' => new Anchor("N° d'affiliation", dx: 3),
            'member_last_name' => new Anchor('Nom', occurrence: 1, dx: 3),
            'member_first_name' => new Anchor('Prénom', dx: 3),
            'member_address' => new Anchor('Adresse', dx: 3),
            'member_city' => new Anchor('CP et Localité', dx: 3),

            'club_name' => new Anchor('La direction du club sportif', dx: 3),
            'federation' => new Anchor('affilié à la Fédération/Ligue', dx: 3),
            'member_full_name' => new Anchor('certifie que (nom et prénom)', dx: 3),
            // Three boxed cells with printed separators between them.
            'period_from_day' => new Anchor('est affiliée à partir du', dx: 4.5),
            'period_from_month' => new Anchor('est affiliée à partir du', dx: 16.5),
            'period_from_year' => new Anchor('est affiliée à partir du', dx: 30.5),
            'discipline' => new Anchor('Sport pratiqué', dx: 3),
            'amount_euros' => new Anchor('Montant payé', dx: 8),
            'amount_cents' => new Anchor('Montant payé', dx: 31),
            'seal' => new Anchor('Cachet du club sportif', placement: Place::Below, dy: -1, scale: 0.8),
            'signature' => new Anchor('Signature du responsable du club', placement: Place::Below, dy: -1, scale: 0.9),
        ];
    }

    /**
     * @return array<string, Anchor>
     */
    private static function solidaris(): array
    {
        // The national register number is drawn as eleven single-character
        // cells, evenly spaced after their label. One digit per anchor, or the
        // number runs across every border on the line.
        $nrnCells = [];

        for ($cell = 1; $cell <= 11; $cell++) {
            $nrnCells['member_nrn_' . $cell] = new Anchor(
                "N° d'identification du Registre National",
                dx: 6.3 + 7.6 * ($cell - 1),
            );
        }

        return [
            // « À compléter par le bénéficiaire ». Only the signature is left
            // for the member to write.
            'member_full_name' => new Anchor('Nom et Prénom', dx: 3),
            ...$nrnCells,
            'issued_day' => new Anchor('Fait le', dx: 2),
            'issued_month' => new Anchor('Fait le', dx: 11.5),
            'issued_year' => new Anchor('Fait le', dx: 23),
            // The town is asked for on a line that reads « à .......... » and
            // nothing else — « à » is far too common to anchor on, and the first
            // one is up in the covering sentence. Hung off « Fait le » instead,
            // which is unique and sits one line above it.
            'member_town' => new Anchor('Fait le', column: 29, dy: 7.8),

            'signatory_name' => new Anchor('Je soussigné.e', dx: 3),
            // « Nom » three times: the beneficiary's block, the association's,
            // then the beneficiary again inside the club's declaration.
            'club_name' => new Anchor('Nom', occurrence: 2, dx: 3),
            'club_address' => new Anchor('Adresse', occurrence: 1, dx: 3),
            'club_city' => new Anchor('Code postal et localité', dx: 3),
            'club_phone' => new Anchor('N° de téléphone', dx: 3),
            'member_full_name_club' => new Anchor('Nom et prénom du/de la bénéficiaire du service', dx: 3),
            'amount' => new Anchor("Certifie sur l'honneur que la somme de", dx: 3),
            'period_from' => new Anchor('pour la période du', dx: 2),
            'period_to' => new Anchor('pour la période du', dx: 34),
            'discipline' => new Anchor('pour la pratique du sport suivant', dx: 3),
            'issued_on' => new Anchor('Date', occurrence: 1, dx: 3),
            'seal' => new Anchor('Signature et cachet', placement: Place::Below, dy: -1, scale: 0.45),
            'signature' => new Anchor('Signature et cachet', placement: Place::Below, dx: 22, dy: 1, scale: 0.7),
        ];
    }
}
