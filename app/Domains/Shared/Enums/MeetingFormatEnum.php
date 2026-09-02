<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

enum MeetingFormatEnum: string
{
    case PHYSICAL = 'physical';
    case VIRTUAL = 'virtual';

    public static function getOptions(): array
    {
        return array_map(
            fn (self $case): array => ['id' => $case->value, 'name' => $case->getLabel()],
            self::cases()
        );
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::PHYSICAL => 'o-map-pin',
            self::VIRTUAL => 'o-video-camera',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::PHYSICAL => __('Physical'),
            self::VIRTUAL => __('Virtual'),
        };
    }
}
