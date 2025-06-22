<?php

namespace App\Enums;

enum ArticlesCategoryEnum: string
{
    case COMPETITION = __('Compétition');
    case PARTNERSHIP = __('Partnership');
    case PORTRAIT = __('Portrait');
    case EVENEMENT = __('Events');
    case TRAINING = __('Training');
    case NEWS = __('News');

    /**
     * Return the values of the enum into an array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
