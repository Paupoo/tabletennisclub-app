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

    public static function normalize(?string $iban): ?string
    {
        if ($iban === null) {
            return null;
        }

        $normalized = strtoupper(str_replace([' ', '-'], '', $iban));

        return $normalized === '' ? null : $normalized;
    }
}
