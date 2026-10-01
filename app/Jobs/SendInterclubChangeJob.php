<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\InterclubChange;
use App\Domains\Competitions\Interclub\Notifications\InterclubChangeNotification;
use App\Jobs\Concerns\RetriesWhileRateLimited;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Notification;

/**
 * Tells one member what the federation changed about their team's fixture.
 *
 * Under the `convocations` limiter, like the lineup it may contradict: the two
 * reach the same people, through the same mail provider.
 */
class SendInterclubChangeJob implements ShouldQueue
{
    use Queueable, RetriesWhileRateLimited;

    /** @param array<int, int> $changeIds */
    public function __construct(
        public array $changeIds,
        public int $userId,
        public bool $forCaptain = false,
        public int $informedCount = 0,
    ) {}

    public function handle(): void
    {
        $recipient = User::find($this->userId);
        $changes = InterclubChange::with(['interclub.visitedTeam.club', 'interclub.visitingTeam.club', 'interclub.room'])
            ->whereKey($this->changeIds)
            ->orderBy('id')
            ->get();

        if ($recipient === null || $changes->isEmpty()) {
            return;
        }

        Notification::send($recipient, new InterclubChangeNotification($changes, $this->forCaptain, $this->informedCount));
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('convocations')];
    }
}
