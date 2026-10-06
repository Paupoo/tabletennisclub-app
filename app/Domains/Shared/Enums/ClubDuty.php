<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * The duties a member may need someone for, as the « Who does what » page
 * lists them, and the délégations whose holders answer for each.
 *
 * A whitelist on purpose: several délégations are technical (access rights,
 * supervision, the accounts audit) and mean nothing to a member with a
 * question. A délégation missing here is simply not shown.
 */
enum ClubDuty: string
{
    case Affiliation = 'affiliation';
    case Attestations = 'attestations';
    case Bar = 'bar';
    case Facilities = 'facilities';
    case Feedback = 'feedback';
    case Fines = 'fines';
    case Interclubs = 'interclubs';
    case Payments = 'payments';
    case Tournaments = 'tournaments';
    case Trainings = 'trainings';
    case Website = 'website';

    public function icon(): string
    {
        return match ($this) {
            self::Interclubs => 'o-trophy',
            self::Trainings => 'o-academic-cap',
            self::Tournaments => 'o-star',
            self::Affiliation => 'o-identification',
            self::Payments => 'o-credit-card',
            self::Bar => 'o-shopping-bag',
            self::Facilities => 'o-key',
            self::Attestations => 'o-document-check',
            self::Fines => 'o-scale',
            self::Website => 'o-globe-alt',
            self::Feedback => 'o-chat-bubble-left-ellipsis',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Interclubs => __('Interclubs and team selections'),
            self::Trainings => __('Training sessions and courses'),
            self::Tournaments => __('Tournaments'),
            self::Affiliation => __('Affiliation'),
            self::Payments => __('Payments and refunds'),
            self::Bar => __('Bar'),
            self::Facilities => __('Rooms, keys and equipment'),
            self::Attestations => __('Mutual attestations'),
            self::Fines => __('Fines'),
            self::Website => __('Website and communication'),
            self::Feedback => __('Your feedback and suggestions'),
        };
    }

    /**
     * @return array<int, Role>
     */
    public function roles(): array
    {
        return match ($this) {
            self::Interclubs => [Role::INTERCLUBS, Role::SELECTIONS],
            self::Trainings => [Role::TRAININGS, Role::COACH],
            self::Tournaments => [Role::TOURNAMENTS],
            self::Affiliation => [Role::MEMBERS],
            self::Payments => [Role::TREASURY],
            // The store keeper runs the bar; the barmen serving on a given
            // evening are too many, and change too often, to be « the » contact.
            self::Bar => [Role::STORE_KEEPER],
            self::Facilities => [Role::FACILITIES],
            self::Attestations => [Role::ATTESTATIONS],
            self::Fines => [Role::FINES],
            self::Website => [Role::WEBSITE],
            self::Feedback => [Role::FEEDBACK],
        };
    }
}
