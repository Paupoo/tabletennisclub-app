<?php

namespace App\Enums;

enum ArticlesCategoryEnum: string
{
    case COMPETITION = 'Compétition';
    case PARTNERSHIP = 'Partnership';
    case PORTRAIT = 'Portrait';
    case EVENT = 'Events';
    case TRAINING = 'Training';
    case NEWS = 'News';

    public function getLabel(): string
    {
        return match ($this) {
            self::COMPETITION => __('Compétition'),
            self::PARTNERSHIP => __('Partnership'),
            self::PORTRAIT => __('Portrait'),
            self::EVENT => __('Events'),
            self::TRAINING => __('Training'),
            self::NEWS => __('News'),
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
