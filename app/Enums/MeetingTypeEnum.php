<?php

declare(strict_types=1);

namespace App\Enums;

enum MeetingTypeEnum: string
{
    case COMMITTEE = 'committee';
    case GENERAL_ASSEMBLY = 'general_assembly';

    public static function getOptions(): array
    {
        return array_map(
            fn (self $case) => ['id' => $case->value, 'name' => $case->getLabel()],
            self::cases()
        );
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::COMMITTEE => 'o-users',
            self::GENERAL_ASSEMBLY => 'o-building-library',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::COMMITTEE => __('Committee Meeting'),
            self::GENERAL_ASSEMBLY => __('General Assembly'),
        };
    }

    public function isPublic(): bool
    {
        return $this === self::GENERAL_ASSEMBLY;
    }
}
