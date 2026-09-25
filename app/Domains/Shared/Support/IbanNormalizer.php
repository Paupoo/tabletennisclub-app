<?php

declare(strict_types=1);

namespace App\Domains\Shared\Support;

class IbanNormalizer
{
    public static function format(?string $iban): ?string
    {
        $normalized = self::normalize($iban);

        if ($normalized === null) {
            return null;
        }

        return implode(' ', str_split($normalized, 4));
    }

    /**
     * The account in its printed form, with everything but the country code and
     * the last four characters starred — enough to recognise it, not to use it.
     */
    /**
     * Ce numéro est-il un IBAN, ou seulement une chaîne&nbsp;?
     *
     * Structure — deux lettres de pays, deux chiffres de contrôle, puis le
     * compte — puis la clé mod-97 de la norme ISO 13616 : les quatre premiers
     * caractères passent à la fin, chaque lettre devient sa position + 9, et le
     * nombre obtenu doit laisser 1 pour reste.
     *
     * Calculée chiffre par chiffre : un IBAN fait jusqu'à 34 caractères, donc
     * bien plus qu'un entier natif, et `bcmath` n'est pas garanti sur l'hôte.
     */
    public static function isValid(?string $iban): bool
    {
        $normalized = self::normalize($iban);

        if ($normalized === null || preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{10,30}$/', $normalized) !== 1) {
            return false;
        }

        $rearranged = substr($normalized, 4) . substr($normalized, 0, 4);
        $remainder = 0;

        foreach (str_split($rearranged) as $character) {
            $value = ctype_digit($character) ? $character : (string) (ord($character[0]) - 55);

            foreach (str_split($value) as $digit) {
                $remainder = ($remainder * 10 + (int) $digit) % 97;
            }
        }

        return $remainder === 1;
    }

    public static function mask(?string $iban): ?string
    {
        $normalized = self::normalize($iban);

        if ($normalized === null) {
            return null;
        }

        $length = strlen($normalized);
        $masked = substr($normalized, 0, 2)
            . str_repeat('*', max(0, $length - 6))
            . substr($normalized, max(2, $length - 4));

        return self::format($masked);
    }

    public static function normalize(?string $iban): ?string
    {
        if ($iban === null) {
            return null;
        }

        $normalized = strtoupper(str_replace([' ', '-'], '', $iban));

        return $normalized === '' ? null : $normalized;
    }
}
