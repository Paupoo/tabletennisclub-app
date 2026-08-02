<?php

declare(strict_types=1);

namespace App\Domains\Shared\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidPhone implements ValidationRule
{
    /**
     * Separators members habitually sprinkle inside a phone number. They carry
     * no meaning, so they are dropped before anything else happens.
     *
     * @var list<string>
     */
    private const SEPARATORS = [' ', "\u{00A0}", "\u{202F}", '.', '-', '–', '/', '(', ')'];

    /**
     * Validate a phone number the way a Belgian club actually receives them.
     *
     * Deliberately permissive on presentation (`0475 12 34 56`, `0475.12.34.56`,
     * `+32 475 12 34 56` and `010 45 67 89` are all the same number to us) and
     * strict only on substance: digits, an optional leading `+`, and a length
     * that can plausibly be dialled (8 to 15 digits, the E.164 ceiling).
     */
    public static function check(string $phone): bool
    {
        $normalized = self::normalize($phone);

        if ($normalized === null) {
            return false;
        }

        if (! preg_match('/^\+?\d+$/', $normalized)) {
            return false;
        }

        $digits = ltrim($normalized, '+');

        return strlen($digits) >= 8 && strlen($digits) <= 15;
    }

    /**
     * Reduce a phone number to a comparable form: separators stripped, the
     * international prefix spelled `+`, and Belgian numbers brought back to
     * their national `0…` notation so `+32 475 12 34 56` and `0475123456`
     * compare equal.
     *
     * Returns the compacted string as typed when it is not a phone number at
     * all — telling valid from invalid is `check()`'s job, not this one's.
     */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $compact = str_replace(self::SEPARATORS, '', trim($phone));

        if ($compact === '') {
            return null;
        }

        // 0032… and +32… are the same number as 0…
        $compact = (string) preg_replace('/^00/', '+', $compact);

        return (string) preg_replace('/^\+32/', '0', $compact);
    }

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::check($value)) {
            $fail(__('The :attribute is not a valid phone number.'));
        }
    }
}
