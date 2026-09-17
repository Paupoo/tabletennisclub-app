<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

enum LeagueCategory: string
{
    case MEN = 'Men';
    case VETERANS = 'Veterans';
    case WOMEN = 'Women';

    /**
     * The federation's own category code, as TabT numbers divisions.
     *
     * Lives here rather than in the importer because two different callers now
     * need it: the calendar import, which decides whether a division concerns
     * us at all, and the fixture matcher, which has only a category to tell
     * apart an "A" team in men from an "A" team in veterans.
     */
    public static function fromFederationCategory(int $code): ?self
    {
        return match ($code) {
            37 => self::MEN,
            3 => self::VETERANS,
            38 => self::WOMEN,
            default => null,
        };
    }

    /**
     * Resolve a raw database value: leagues store the case *name*
     * ('MEN', 'VETERANS', 'WOMEN'), not the backed value.
     */
    public static function fromName(?string $name): ?self
    {
        return collect(self::cases())->firstWhere('name', $name);
    }

    /**
     * Design-system badge classes for the category (token-based, theme-safe).
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::MEN => 'badge-primary badge-soft',
            self::VETERANS => 'badge-warning badge-soft',
            self::WOMEN => 'badge-error badge-soft',
        };
    }

    /**
     * Design-system classes for the category section chip (pill header).
     */
    public function chipClasses(): string
    {
        return match ($this) {
            self::MEN => 'bg-primary/10 border-primary/20 text-primary',
            self::VETERANS => 'bg-warning/10 border-warning/20 text-warning-content',
            self::WOMEN => 'bg-error/10 border-error/20 text-error',
        };
    }

    public function dotClasses(): string
    {
        return match ($this) {
            self::MEN => 'bg-primary',
            self::VETERANS => 'bg-warning',
            self::WOMEN => 'bg-error',
        };
    }

    public function label(): string
    {
        return __($this->value);
    }

    /**
     * Display order when grouping teams or matches by category.
     */
    public function sortOrder(): int
    {
        return match ($this) {
            self::MEN => 1,
            self::VETERANS => 2,
            self::WOMEN => 3,
        };
    }
}
