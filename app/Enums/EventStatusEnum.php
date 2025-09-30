<?php

declare(strict_types=1);

namespace App\Enums;

enum EventStatusEnum: string
{
    case ARCHIVED = 'ARCHIVED';
    case DRAFT = 'DRAFT';
    case PUBLISHED = 'PUBLISHED';

    /**
     * Return the values of the enum into an array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => __('Draft'),
            self::PUBLISHED => __('Published'),
            self::ARCHIVED => __('Archived'),
        };
    }
}
