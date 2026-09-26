<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\Bar\Models\BarRestocking;
use App\Domains\Bar\Notifications\BarRestockingDigestNotification;
use App\Domains\Bar\Services\RestockingList;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Jobs\Concerns\RetriesWhileRateLimited;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;

/**
 * Mails one store keeper the bar's shopping list. The list is computed here,
 * not in the command: a bar filled while the queue drained sends nothing.
 */
class SendBarRestockingDigestJob implements ShouldQueue
{
    use Queueable, RetriesWhileRateLimited;

    public function __construct(public int $userId) {}

    public function handle(RestockingList $restockingList): void
    {
        $user = User::find($this->userId);

        if ($user === null || $user->email === null) {
            return;
        }

        $toBuy = $restockingList->current()['to_buy'];

        if ($toBuy === []) {
            return;
        }

        $user->notify(new BarRestockingDigestNotification($toBuy, BarRestocking::inProgress()?->load('shopper')));
    }

    /**
     * A broadcast shares the `invitations` limiter rather than adding a key.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('invitations')];
    }
}
