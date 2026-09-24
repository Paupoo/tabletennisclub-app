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
