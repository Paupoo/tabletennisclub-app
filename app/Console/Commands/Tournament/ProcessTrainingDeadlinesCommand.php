<?php

declare(strict_types=1);

namespace App\Console\Commands\Tournament;

use App\Actions\ClubAdmin\Subscriptions\PromoteFromTrainingWaitlistAction;
use App\Models\ClubEvents\Training\TrainingPack;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('training:process-deadlines')]
#[Description('Expire unconfirmed waitlist offers for training packs and promote the next waitlisted user.')]
class ProcessTrainingDeadlinesCommand extends Command
{
    public function handle(PromoteFromTrainingWaitlistAction $promote): int
    {
        $expired = DB::table('subscription_training_pack')
            ->where('status', 'offered')
            ->where('confirmation_deadline', '<', now())
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired training waitlist offers.');

            return self::SUCCESS;
        }

        $this->info("Expiring {$expired->count()} training waitlist offer(s)...");

        $packIds = $expired->pluck('training_pack_id')->unique();

        // Remove expired offers
        DB::table('subscription_training_pack')
            ->where('status', 'offered')
            ->where('confirmation_deadline', '<', now())
            ->delete();

        // Promote next for each affected pack
        TrainingPack::whereIn('id', $packIds)->each(function (TrainingPack $pack) use ($promote): void {
            $promote($pack);
        });

        $this->info('Done.');

        return self::SUCCESS;
    }
}
