<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Users\Data;

use Illuminate\Support\Collection;

/**
 * What declaring a departure handed back, for the screen to say it.
 *
 * The office records the departure; the people who now have a gap to fill — a
 * team without a captain, a lineup one name short — learn it from the toast,
 * or from the mail sent to the captain.
 */
final readonly class MemberDepartureOutcome
{
    /**
     * @param  list<string>  $teamsWithoutCaptain  the names of the teams the member captained
     * @param  int  $fixturesLeft  the upcoming interclub matches the member was taken off
     * @param  int  $lineupsLeft  among them, those whose lineup the team had already received
     * @param  bool  $captainTold  whether a captain other than the member was mailed about those lineups
     */
    public function __construct(
        public array $teamsWithoutCaptain = [],
        public int $fixturesLeft = 0,
        public int $lineupsLeft = 0,
        public bool $captainTold = false,
    ) {}

    /**
     * Several departures recorded in one gesture, read as one.
     *
     * @param  Collection<int, self>  $outcomes
     */
    public static function merge(Collection $outcomes): self
    {
        return new self(
            teamsWithoutCaptain: $outcomes->flatMap(fn (self $outcome): array => $outcome->teamsWithoutCaptain)->unique()->sort()->values()->all(),
            fixturesLeft: (int) $outcomes->sum('fixturesLeft'),
            lineupsLeft: (int) $outcomes->sum('lineupsLeft'),
            captainTold: $outcomes->contains(fn (self $outcome): bool => $outcome->captainTold),
        );
    }

    /**
     * The interclub half of the toast, empty when the member was on no
     * upcoming match. Begins with a space, to follow the rest of the message.
     */
    public function fixturesNotice(): string
    {
        if ($this->fixturesLeft === 0) {
            return '';
        }

        $notice = ' ' . trans_choice(
            '{1} Place freed in :count upcoming interclub match.|[2,*] Places freed in :count upcoming interclub matches.',
            $this->fixturesLeft,
            ['count' => $this->fixturesLeft],
        );

        if ($this->lineupsLeft > 0) {
            $notice .= ' ' . ($this->captainTold ? __('Captain and interclubs manager told.') : __('Interclubs manager told.'));
        }

        return $notice;
    }
}
