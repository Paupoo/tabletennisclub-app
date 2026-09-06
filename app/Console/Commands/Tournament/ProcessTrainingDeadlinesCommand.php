<?php

declare(strict_types=1);

namespace App\Console\Commands\Tournament;

use App\Domains\Trainings\Services\TrainingWaitlistService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('training:process-deadlines')]
#[Description('Expire unconfirmed waitlist offers for training packs and promote the next waitlisted user.')]
class ProcessTrainingDeadlinesCommand extends Command
{
    public function handle(TrainingWaitlistService $waitlist): int
    {
        $expired = $waitlist->expireOffers();

        if ($expired === 0) {
            $this->info('No expired training waitlist offers.');

            return self::SUCCESS;
        }

        $this->info("Expired {$expired} training waitlist offer(s) and called the next in line.");

        return self::SUCCESS;
    }
}
