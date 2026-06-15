<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

use DateTimeInterface;
use Illuminate\Support\Carbon;

enum AgeCategoryEnum: string
{
    case ADULT = 'ADULT';
    case CHILD = 'CHILD';
    case TEEN = 'TEEN';

    /**
     * Minimum age (inclusive) to be considered an adult.
     */
    public const int ADULT_MIN_AGE = 18;

    /**
     * Minimum age (inclusive) to be considered a teenager.
     */
    public const int TEEN_MIN_AGE = 13;

    public static function fromBirthdate(Carbon|DateTimeInterface $birthdate): self
    {
        $age = Carbon::parse($birthdate)->age;

        return match (true) {
            $age >= self::ADULT_MIN_AGE => self::ADULT,
            $age >= self::TEEN_MIN_AGE => self::TEEN,
            default => self::CHILD,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::CHILD => __('Child'),
            self::TEEN => __('Teenager'),
            self::ADULT => __('Adult'),
        };
    }
}
