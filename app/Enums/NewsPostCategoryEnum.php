<?php

declare(strict_types=1);

namespace App\Enums;

enum NewsPostCategoryEnum: string
{
    case COMPETITION = 'Compétition';
    case EVENT = 'Events';
    case NEWS = 'News';
    case PARTNERSHIP = 'Partnership';
    case PORTRAIT = 'Portrait';
    case TRAINING = 'Training';

    /**
     * Returns the values of the enum into an array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Return the localized string of a particular value
     * @return string
     */
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
}
