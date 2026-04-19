<?php

declare(strict_types=1);

namespace App\Enums;

enum CommitteeRolesEnum: string
{
    case PRESIDENT = 'PRESIDENT';
    case VICE_PRESIDENT = 'VICE_PRESIDENT';
    case TREASURER = 'TREASURER';
    case SECRETARY = 'SECRETARY';
    case ADMINISTRATOR = 'ADMINISTRATOR';


    public function label(): string
    {
        return match ($this) {
            self::PRESIDENT => __('President'),
            self::VICE_PRESIDENT => __('Vice-President'),
            self::TREASURER => __('Treasurer'),
            self::SECRETARY => __('Secretary'),
            self::ADMINISTRATOR => __('Administrator'),
        };
    }

    public static function getOptions(): array
    {
        return [
            ['id' => self::PRESIDENT->value, 'name' => self::PRESIDENT->label()],
            ['id' => self::VICE_PRESIDENT->value, 'name' => self::VICE_PRESIDENT->label()],
            ['id' => self::TREASURER->value, 'name' => self::TREASURER->label()],
            ['id' => self::SECRETARY->value, 'name' => self::SECRETARY->label()],
            ['id' => self::ADMINISTRATOR->value, 'name' => self::ADMINISTRATOR->label()],
        ];
    }   
}
