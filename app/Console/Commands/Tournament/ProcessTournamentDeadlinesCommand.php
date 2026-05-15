<?php

declare(strict_types=1);

namespace App\Console\Commands\Tournament;

use App\Services\TournamentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tournament:process-deadlines')]
#[Description('Expire unconfirmed waitlist promotions and unpaid registrations, then send payment reminders.')]
class ProcessTournamentDeadlinesCommand extends Command
{
    public function handle(TournamentService $service): int
    {
        $this->info('Expiring confirmation deadlines (waitlist)...');
        $service->expireConfirmationDeadlines();

        $this->info('Expiring payment deadlines...');
        $service->expirePaymentDeadlines();

        $this->info('Sending payment reminders...');
        $service->sendPaymentReminders();

        $this->info('Done.');

        return self::SUCCESS;
    }
}
