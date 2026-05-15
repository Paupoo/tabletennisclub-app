<?php

declare(strict_types=1);

namespace App\Console\Commands\Payment;

use App\Enums\CommitteeRolesEnum;
use App\Models\ClubAdmin\Payment\Payment;
use App\Models\ClubAdmin\Users\User;
use App\Notifications\Payment\WeeklyRefundReminderNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('payment:send-refund-reminder')]
#[Description('Send a weekly email to the treasurer and secretary listing all payments awaiting refund.')]
class SendWeeklyRefundReminder extends Command
{
    public function handle(): int
    {
        $payments = Payment::with(['payable.user', 'payable.tournament'])
            ->where('status', 'to_refund')
            ->get();

        if ($payments->isEmpty()) {
            $this->info('No pending refunds — nothing to send.');

            return self::SUCCESS;
        }

        $recipients = User::where('is_committee_member', true)
            ->whereIn('committee_role', [
                CommitteeRolesEnum::TREASURER->value,
                CommitteeRolesEnum::SECRETARY->value,
            ])
            ->get();

        if ($recipients->isEmpty()) {
            $this->warn('No treasurer or secretary found — nobody to notify.');

            return self::SUCCESS;
        }

        $notification = new WeeklyRefundReminderNotification($payments);

        $recipients->each->notify($notification);

        $this->info("Refund reminder sent to {$recipients->count()} recipient(s) for {$payments->count()} pending refund(s).");

        return self::SUCCESS;
    }
}
