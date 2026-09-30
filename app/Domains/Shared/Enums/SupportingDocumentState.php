<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * Where a supporting document stands, read off its links — never stored.
 */
enum SupportingDocumentState: string
{
    case Settled = 'settled';
    case ToSettle = 'to_settle';

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $state): array => ['id' => $state->value, 'name' => $state->label()],
            [self::ToSettle, self::Settled],
        );
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Settled => 'badge-success badge-soft',
            self::ToSettle => 'badge-warning badge-soft',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Settled => __('Settled up'),
            self::ToSettle => __('To settle'),
        };
    }
}
