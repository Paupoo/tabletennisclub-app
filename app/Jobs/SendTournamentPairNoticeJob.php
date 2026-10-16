<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Notifications\TournamentPairNotification;
use App\Jobs\Concerns\RetriesWhileRateLimited;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Notification;

/**
 * Tell one player who they play with, once the committee has published the pairs.
 *
 * The partner is read when the mail leaves, not when the job is queued: if the
 * committee reworks a pair twice in a row, the player learns where it landed.
 */
class SendTournamentPairNoticeJob implements ShouldQueue
{
    use Queueable, RetriesWhileRateLimited;

    public function __construct(
        public int $tournamentId,
        public int $userId,
    ) {}

    public function handle(): void
    {
        $tournament = Tournament::find($this->tournamentId);
        $player = User::find($this->userId);

        if ($tournament === null || $player === null) {
            return;
        }

        $pair = $tournament->pairs()
            ->where(fn ($query) => $query->where('player1_id', $player->id)->orWhere('player2_id', $player->id))
            ->first();

        Notification::sendNow($player, new TournamentPairNotification($tournament, $pair?->partnerOf($player)));
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('invitations')];
    }
}
