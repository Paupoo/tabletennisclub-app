<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Users\Data;

use Illuminate\Support\Collection;

/**
 * What declaring a departure handed back, for the screen to say it.
 *
 * The office records the departure; the people who now have a gap to fill — a
 * team without a captain, a team one player short — learn it from the toast,
 * or from the mail sent to the captain.
 */
final readonly class MemberDepartureOutcome
{
    /**
     * @param  list<string>  $teamsWithoutCaptain  the names of the teams the member captained
     * @param  int  $fixturesLeft  the upcoming interclub matches the member was taken off
     * @param  list<int>  $captainsTold  the ids of the captains mailed about the departure
     */
    public function __construct(
        public array $teamsWithoutCaptain = [],
        public int $fixturesLeft = 0,
        public array $captainsTold = [],
    ) {}

    /**
     * Several departures recorded in one gesture, read as one.
     *
     * A captain is counted once however many of their players left, and not
     * at all when they left in the same gesture: their mail is never sent.
     *
     * @param  Collection<int, self>  $outcomes
     * @param  array<int, int>  $leaverIds  the members declared gone in that gesture
     */
    public static function merge(Collection $outcomes, array $leaverIds = []): self
    {
        return new self(
            teamsWithoutCaptain: $outcomes->flatMap(fn (self $outcome): array => $outcome->teamsWithoutCaptain)->unique()->sort()->values()->all(),
            fixturesLeft: (int) $outcomes->sum('fixturesLeft'),
            captainsTold: $outcomes->flatMap(fn (self $outcome): array => $outcome->captainsTold)
                ->unique()
                ->diff($leaverIds)
                ->sort()
                ->values()
                ->all(),
        );
    }

    /**
     * The interclub half of the toast: the matches the member was taken off,
     * then the captains told. Empty when there is neither; begins with a
     * space, to follow the rest of the message.
     */
    public function fixturesNotice(): string
    {
        $notice = '';

        if ($this->fixturesLeft > 0) {
            $notice .= ' ' . trans_choice(
                '{1} Place freed in :count upcoming interclub match.|[2,*] Places freed in :count upcoming interclub matches.',
                $this->fixturesLeft,
                ['count' => $this->fixturesLeft],
            );
        }

        if ($this->captainsTold !== []) {
            $notice .= ' ' . trans_choice('{1} Captain told.|[2,*] Captains told.', count($this->captainsTold));
        }

        return $notice;
    }
}
