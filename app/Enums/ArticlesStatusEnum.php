<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ArticlesStatusEnum: string implements HasLabel
{
    case DRAFT = 'draft';
    case PUBLISHED = 'dublished';
    case ARCHIVED = 'archived';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => __('Draft'),
            self::PUBLISHED => __('Published'),
            self::ARCHIVED => __('Archived'),
        };
    }

    /**
     * Return the values of the enum into an array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
