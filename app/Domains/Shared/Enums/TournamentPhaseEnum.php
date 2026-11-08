<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

use App\Domains\Competitions\Tournament\Models\Tournament;

/**
 * The four phases a tournament's page is organised in.
 *
 * The seven statuses stay — merging them would lose "never opened", which three
 * behaviours depend on — but nobody outside the code reasons in seven steps.
 * The committee reads where the tournament stands as one of these four.
 */
enum TournamentPhaseEnum: string
{
    case MATCH_DAY = 'match-day';
    case PREPARATION = 'preparation';
    case REGISTRATIONS = 'registrations';
    case REPORT = 'report';

    /**
     * The phase a visitor lands on.
     *
     * Closed registrations are split by the date: the week before, the
     * committee composes the pairs; on the day, it calls the roll.
     */
    public static function activeFor(Tournament $tournament): ?self
    {
        return match ($tournament->status) {
            TournamentStatusEnum::DRAFT,
            TournamentStatusEnum::LOCKED => self::PREPARATION,
            TournamentStatusEnum::PUBLISHED => self::REGISTRATIONS,
            TournamentStatusEnum::SETUP => $tournament->start_date?->isFuture() && ! $tournament->start_date->isToday()
                ? self::REGISTRATIONS
                : self::MATCH_DAY,
            TournamentStatusEnum::PENDING => self::MATCH_DAY,
            TournamentStatusEnum::CLOSED => self::REPORT,
            TournamentStatusEnum::CANCELLED => null,
        };
    }

    /**
     * The four phases in the order a tournament goes through them.
     *
     * Not `cases()`: Pint sorts the cases alphabetically, and the match day
     * would come first.
     *
     * @return list<self>
     */
    public static function inOrder(): array
    {
        return [self::PREPARATION, self::REGISTRATIONS, self::MATCH_DAY, self::REPORT];
    }

    /**
     * Whether this phase has anything to show yet.
     *
     * A cancelled tournament keeps every phase it had reached, read-only.
     */
    public function isOpenFor(Tournament $tournament): bool
    {
        $reached = match ($tournament->status) {
            TournamentStatusEnum::DRAFT,
            TournamentStatusEnum::LOCKED,
            TournamentStatusEnum::CANCELLED => 1,
            TournamentStatusEnum::PUBLISHED => 2,
            TournamentStatusEnum::SETUP => 3,
            TournamentStatusEnum::PENDING,
            TournamentStatusEnum::CLOSED => 4,
        };

        return $this->position() <= $reached;
    }

    public function label(): string
    {
        return match ($this) {
            self::PREPARATION => __('Preparation'),
            self::REGISTRATIONS => __('Registrations'),
            self::MATCH_DAY => __('Tournament day'),
            self::REPORT => __('Wrap-up'),
        };
    }

    /**
     * The one line a phase not reached yet shows instead of its content.
     */
    public function opensWhen(): string
    {
        return match ($this) {
            self::PREPARATION => '',
            self::REGISTRATIONS => __('Registrations are not open yet.'),
            self::MATCH_DAY => __('The match day opens once registrations are closed.'),
            self::REPORT => __('The report opens once the tournament is launched.'),
        };
    }

    public function position(): int
    {
        return match ($this) {
            self::PREPARATION => 1,
            self::REGISTRATIONS => 2,
            self::MATCH_DAY => 3,
            self::REPORT => 4,
        };
    }

    /**
     * Where the phase lives.
     *
     * Two components carry the page: the one that prepares the tournament and
     * the control room. Once launched, the match day and the report are the
     * control room's; before, the preparation component shows them.
     */
    public function urlFor(Tournament $tournament): string
    {
        $launched = $tournament->state()->hasBeenLaunched();

        return match ($this) {
            self::PREPARATION => route('admin.tournaments.wizard.edit', [$tournament, 'step' => '1']),
            self::REGISTRATIONS => route('admin.tournaments.wizard.edit', [$tournament, 'step' => '5']),
            self::MATCH_DAY => $launched
                ? route('admin.tournaments.live-center', $tournament)
                : route('admin.tournaments.wizard.edit', [$tournament, 'step' => '6']),
            self::REPORT => $launched
                ? route('admin.tournaments.live-center', [$tournament, 'activeTab' => 'closure'])
                : route('admin.tournaments.wizard.edit', [$tournament, 'step' => 'report']),
        };
    }
}
