<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Trainings\Models\TrainingPack;

/**
 * The functional domains that can be switched off in a given environment.
 *
 * The unit is the domain, not the individual screen: a domain is what one judges
 * mature or not, and it keeps the combinations testable. Flags are read from
 * config (`config/features.php`, driven by `.env`) — they are per-environment,
 * never per-user, so an answer never depends on who is asking.
 *
 * Switching a domain off must remove it from all four surfaces at once, or the
 * result is worse than leaving it on: routes 404, navigation entries disappear,
 * scheduled commands stop firing, and public calendar entries are filtered out.
 *
 * Deliberately absent: members, seasons and facilities. They are load-bearing —
 * turning them off would brick the application rather than hide a feature.
 */
enum Feature: string
{
    case Bar = 'bar';
    case CashRegister = 'cash_register';
    case Contacts = 'contacts';
    case HelpCentre = 'help_centre';
    case Interclubs = 'interclubs';
    case Meetings = 'meetings';
    case Supervision = 'supervision';
    case Tournaments = 'tournaments';
    case TrainingPlanning = 'training_planning';
    case Trainings = 'trainings';
    case Treasury = 'treasury';
    case Website = 'website';

    /**
     * Domains whose public calendar entries must disappear along with the domain.
     *
     * @return array<int, class-string>
     */
    public static function eventableTypesToHide(): array
    {
        $hidden = [];

        if (! self::Tournaments->enabled()) {
            $hidden[] = Tournament::class;
        }

        if (! self::Trainings->enabled()) {
            $hidden[] = TrainingPack::class;
        }

        if (! self::Meetings->enabled()) {
            $hidden[] = Meeting::class;
        }

        if (! self::Interclubs->enabled()) {
            $hidden[] = Interclub::class;
        }

        return $hidden;
    }

    public function disabled(): bool
    {
        return ! $this->enabled();
    }

    public function enabled(): bool
    {
        return (bool) config('features.' . $this->value, true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Bar => __('Bar'),
            self::CashRegister => __('Cash register'),
            self::Contacts => __('Contacts'),
            self::HelpCentre => __('Help centre'),
            self::Interclubs => __('Interclubs'),
            self::Meetings => __('Meetings'),
            self::Supervision => __('Technical supervision'),
            self::Tournaments => __('Tournaments'),
            self::TrainingPlanning => __('Season training planning'),
            self::Trainings => __('Trainings'),
            self::Treasury => __('Treasury'),
            self::Website => __('Website'),
        };
    }
}
