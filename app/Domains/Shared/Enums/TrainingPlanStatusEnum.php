<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

enum TrainingPlanStatusEnum: string
{
    case ARCHIVED = 'archived';
    case DRAFT = 'draft';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => __('Draft'),
            self::ARCHIVED => __('Archived'),
        };
    }
}
