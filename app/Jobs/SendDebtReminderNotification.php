<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Notifications\TournamentDebtReminderNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendDebtReminderNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $paymentId,
        public int $userId,
        public int $tournamentId,
    ) {}

    public function handle(): void
    {
        $payment = Payment::find($this->paymentId);
        $user = User::find($this->userId);
        $tournament = Tournament::find($this->tournamentId);

        if (! $payment || ! $user || ! $tournament) {
            return;
        }

        // Only send if still unpaid
        if ($payment->status !== 'pending') {
            return;
        }

        $user->notify(new TournamentDebtReminderNotification($tournament, $payment));
    }
}
