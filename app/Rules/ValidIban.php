<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidIban implements ValidationRule
{
    /**
     * Validate an IBAN using the MOD-97-10 algorithm (ISO 13616).
     *
     * Steps:
     *  1. Strip spaces/hyphens, uppercase.
     *  2. Move the first 4 characters to the end.
     *  3. Replace each letter with its numeric equivalent (A=10 … Z=35).
     *  4. Compute the result modulo 97 — valid if remainder equals 1.
     */
    public static function check(string $iban): bool
    {
        $iban = strtoupper(str_replace([' ', '-'], '', $iban));

        if (strlen($iban) < 15 || strlen($iban) > 34) {
            return false;
        }

        if (! preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/', $iban)) {
            return false;
        }

        // Move first 4 chars to the end, then convert letters to digits.
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);

        $numeric = '';
        for ($i = 0, $len = strlen($rearranged); $i < $len; $i++) {
            $char = $rearranged[$i];
            $numeric .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }

        // Chunked modulo to avoid integer overflow on large strings.
        $remainder = 0;
        for ($i = 0, $len = strlen($numeric); $i < $len; $i++) {
            $remainder = ($remainder * 10 + (int) $numeric[$i]) % 97;
        }

        return $remainder === 1;
    }

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::check($value)) {
            $fail(__('The :attribute is not a valid IBAN.'));
        }
    }
}
