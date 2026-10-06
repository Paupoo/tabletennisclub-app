<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * Where an offer of help stands. Only one stays open: while the club owes the
 * member an answer, the forms stop asking them again.
 */
enum HelpOfferStatus: string
{
    case Contacted = 'contacted';
    case Declined = 'declined';
    case ToContact = 'to_contact';

    public function label(): string
    {
        return match ($this) {
            self::ToContact => __('To contact'),
            self::Contacted => __('Contacted'),
            self::Declined => __('No follow-up'),
        };
    }
}
