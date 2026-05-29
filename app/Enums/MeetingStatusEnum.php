<?php

declare(strict_types=1);

namespace App\Enums;

enum MeetingStatusEnum: string
{
    case CANCELLED = 'cancelled';
    case COMPLETED = 'completed';
    case CONFIRMED = 'confirmed';
    case PLANNING = 'planning';
    case POSTPONED = 'postponed';

    public static function getOptions(): array
    {
        return array_map(
            fn (self $case) => ['id' => $case->value, 'name' => $case->getLabel()],
            self::cases()
        );
    }

    public function getBadgeClass(): string
    {
        return match ($this) {
            self::PLANNING => 'badge-info badge-soft',
            self::CONFIRMED => 'badge-success badge-soft',
            self::COMPLETED => 'badge-ghost',
            self::POSTPONED => 'badge-warning badge-soft',
            self::CANCELLED => 'badge-error badge-soft',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::PLANNING => __('Planning'),
            self::CONFIRMED => __('Confirmed'),
            self::COMPLETED => __('Completed'),
            self::POSTPONED => __('Postponed'),
            self::CANCELLED => __('Cancelled'),
        };
    }
}
