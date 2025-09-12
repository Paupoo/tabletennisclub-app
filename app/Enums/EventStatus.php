<?php

namespace App\Enums;

enum EventStatus: string
{
    case DRAFT = 'draft';

    case PUBLISHED = 'published';

    case ARCHIVED = 'archived';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => __('Draft'),
            self::PUBLISHED => __('Published'),
            self::ARCHIVED => __('Archived'),
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::DRAFT => 'bg-gray-100 text-gray-800',
            self::PUBLISHED => 'bg-green-100 text-green-800',
            self::ARCHIVED => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }
}
