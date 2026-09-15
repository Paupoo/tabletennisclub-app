<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Notifications\InterclubLineupBroadcastNotification;
use App\Jobs\Concerns\RetriesWhileRateLimited;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Notification;

/**
 * Publishes the lineup to one team member who is not on it.
 *
 * The selected players are carried as ids and read back here: the notification
 * wants them to print the sheet, and a serialised Eloquent collection would tie
 * the job to the roster as it stood when the captain clicked.
 */
class SendInterclubLineupBroadcastJob implements ShouldQueue
{
    use Queueable, RetriesWhileRateLimited;

    /** @param array<int, int> $selectedPlayerIds */
    public function __construct(
        public int $interclubId,
        public int $userId,
        public array $selectedPlayerIds,
        public string $captainMessage = '',
        public bool $isUpdate = false,
    ) {}

    public function handle(): void
    {
        $interclub = Interclub::find($this->interclubId);
        $recipient = User::find($this->userId);

        if ($interclub === null || $recipient === null) {
            return;
        }

        $selectedPlayers = User::whereIn('id', $this->selectedPlayerIds)->get();

        Notification::send($recipient, new InterclubLineupBroadcastNotification(
            $interclub,
            $selectedPlayers,
            $this->captainMessage,
            $this->isUpdate,
        ));
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('convocations')];
    }
}
