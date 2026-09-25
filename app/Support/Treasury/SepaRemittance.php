<?php

declare(strict_types=1);

namespace App\Support\Treasury;

/**
 * Ce que le payeur lira sur son extrait de compte.
 *
 * La communication libre d'un virement SEPA tient en **140 caractères** et
 * n'admet qu'un jeu latin restreint : lettres non accentuées, chiffres, et
 * `/ - ? : ( ) . , ' +`. Un « ç » ou un tiret cadratin arrive mutilé chez
 * certaines banques, et refusé chez d'autres.
 */
final class SepaRemittance
{
    /** Longueur maximale de `RmtInf/Ustrd` dans un virement SEPA. */
    public const int MAX_LENGTH = 140;

    /**
     * Le libellé d'un remboursement de trop-perçu.
     *
     * L'ordre n'est pas décoratif : le payeur doit d'abord savoir **qui** le
     * rembourse — il a peut-être oublié ce virement — puis pourquoi, puis pour
     * qui. Quand il faut couper, c'est l'événement qui cède : le club et le
     * membre sont ce qui rend le virement identifiable.
     */
    public static function forOverpayment(string $club, string $event, string $member): string
    {
        $club = self::sanitize($club);
        $event = self::sanitize($event);
        $member = self::sanitize($member);

        $label = sprintf('%s - trop-percu %s - %s', $club, $event, $member);

        if (mb_strlen($label) <= self::MAX_LENGTH) {
            return $label;
        }

        $fixed = mb_strlen(sprintf('%s - trop-percu  - %s', $club, $member));
        $room = self::MAX_LENGTH - $fixed;

        // Sous ce seuil l'événement n'apprend plus rien : on le laisse tomber
        // plutôt que d'en afficher trois lettres.
        if ($room < 8) {
            return mb_substr(sprintf('%s - trop-percu - %s', $club, $member), 0, self::MAX_LENGTH);
        }

        return sprintf('%s - trop-percu %s - %s', $club, rtrim(mb_substr($event, 0, $room)), $member);
    }

    /**
     * Forme transportable : sans accent, et sans rien que la norme refuse.
     *
     * `iconv` translittère (« é » devient « e »), puis tout ce qui reste hors
     * du jeu autorisé devient une espace — un caractère inconnu vaut mieux
     * absent que remplacé par un point d'interrogation qu'on prendrait pour
     * une ponctuation voulue.
     */
    private static function sanitize(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        $clean = preg_replace("#[^A-Za-z0-9/\\-?:().,'+ ]#", ' ', $ascii ?: '') ?? '';

        return trim((string) preg_replace('/\s+/', ' ', $clean));
    }
}
