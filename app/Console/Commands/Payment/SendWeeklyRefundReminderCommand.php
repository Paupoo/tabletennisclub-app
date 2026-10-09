<?php

declare(strict_types=1);

namespace App\Console\Commands\Payment;

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\Fines\Models\Fine;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Notifications\WeeklyRefundReminderNotification;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Subscriptions\Models\SubscriptionTrainingPack;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Meetings\Models\MeetingUser;
use App\Domains\Shared\Enums\Permission;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Signature('payment:send-refund-reminder')]
#[Description('Send a weekly email to the treasurer and secretary listing all payments awaiting refund.')]
class SendWeeklyRefundReminderCommand extends Command
{
    public function handle(): int
    {
        // Only the payables that name a member load one: a non-member's
        // registration or a bar order has no `user`, and asking for it throws.
        $payments = Payment::with(['payable' => fn (MorphTo $payable): MorphTo => $payable->morphWith([
            Subscription::class => ['user'],
            SubscriptionTrainingPack::class => ['user'],
            TournamentRegistration::class => ['user', 'tournament'],
            MeetingUser::class => ['user'],
            ExpenseReport::class => ['user'],
            Fine::class => ['user'],
        ])])
            ->where('status', 'to_refund')
            ->get();

        if ($payments->isEmpty()) {
            $this->info('No pending refunds — nothing to send.');

            return self::SUCCESS;
        }

        $recipients = User::permission(Permission::PaymentsRefund->value)
            ->get();

        if ($recipients->isEmpty()) {
            $this->warn('Nobody holds the refund duty — nobody to notify.');

            return self::SUCCESS;
        }

        $notification = new WeeklyRefundReminderNotification($payments);

        $recipients->each->notify($notification);

        $this->info("Refund reminder sent to {$recipients->count()} recipient(s) for {$payments->count()} pending refund(s).");

        return self::SUCCESS;
    }
}
