<?php

declare(strict_types=1);

namespace App\Enums;

enum MeetingUserStatusEnum: string
{
    case ABSENT = 'absent';
    case ATTENDED = 'attended';
    case CONFIRMED = 'confirmed';
    case DECLINED = 'declined';
    case INVITED = 'invited';

    public static function getOptions(): array
    {
        return array_map(
            fn (self $case) => ['id' => $case->value, 'name' => $case->getLabel()],
            self::cases()
        );
    }

    public function getBadgeClass(): string
    {
        return match ($this) {
            self::INVITED => 'badge-ghost',
            self::CONFIRMED => 'badge-success badge-soft',
            self::DECLINED => 'badge-error badge-soft',
            self::ATTENDED => 'badge-success',
            self::ABSENT => 'badge-warning badge-soft',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::INVITED => __('Invited'),
            self::CONFIRMED => __('Confirmed'),
            self::DECLINED => __('Declined'),
            self::ATTENDED => __('Attended'),
            self::ABSENT => __('Absent'),
        };
    }
}
