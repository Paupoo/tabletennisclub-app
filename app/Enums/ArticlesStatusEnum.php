<?php

namespace App\Enums;

enum ArticlesStatusEnum: string
{
    case DRAFT = __('Draft');
    case PUBLISHED = __('Published');
    case ARCHIVED = __('Archived');

    /**
     * Return the values of the enum into an array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
