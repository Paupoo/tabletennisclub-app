<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * Where a piece of feedback stands for the committee.
 *
 * Internal on purpose: the member who wrote it is only ever told that it was
 * read. « Retained » shown to them would read as a promise, and the club
 * promises nothing beyond reading and weighing what it is told.
 */
enum FeedbackStatus: string
{
    case New = 'new';
    case Noted = 'noted';
    case Read = 'read';
    case Retained = 'retained';

    public function label(): string
    {
        return match ($this) {
            self::New => __('New'),
            self::Read => __('Read'),
            self::Retained => __('Retained'),
            self::Noted => __('Noted'),
        };
    }
}
