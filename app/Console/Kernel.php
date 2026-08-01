<?php

declare(strict_types=1);

namespace App\Console;

use App\Domains\Shared\Enums\Feature;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Every task is gated on its domain's feature flag. Without this, a domain
        // switched off in production would keep mailing members about a feature
        // they can no longer see — which is worse than leaving it on.
        //
        // withoutOverlapping() everywhere: the two hourly deadline commands walk
        // waiting lists and promote the next player, so a run still going when
        // the next hour fires would promote and mail the same person twice.
        // onOneServer() is not used — it needs a locking cache driver and buys
        // nothing on a single machine.

        // Expire waitlist confirmation (48h) + unpaid registrations past their
        // payment deadline (the registration-close date, or 3 days for a late
        // sign-up) + send payment reminders.
        $schedule->command('tournament:process-deadlines')
            ->hourly()
            ->withoutOverlapping()
            ->when(Feature::Tournaments->enabled(...));

        // Expire unconfirmed training waitlist offers (48h) and promote next waiter.
        $schedule->command('training:process-deadlines')
            ->hourly()
            ->withoutOverlapping()
            ->when(Feature::Trainings->enabled(...));

        // Close registrations for tournaments whose deadline has passed.
        $schedule->command('tournament:close-registrations')
            ->dailyAt('00:05')
            ->withoutOverlapping()
            ->when(Feature::Tournaments->enabled(...));

        // Send weekly refund reminder to treasurer + secretary every Monday at 08:00.
        $schedule->command('payment:send-refund-reminder')
            ->weeklyOn(1, '08:00')
            ->withoutOverlapping()
            ->when(Feature::Treasury->enabled(...));

        // On July 1st, ensure the upcoming two seasons are provisioned (+1 and +2).
        // Safe to run any time — idempotent, creates only what is missing.
        $schedule->command('season:provision')
            ->yearlyOn(7, 1, '06:00')
            ->withoutOverlapping();

        // Alert the admins by synchronous email when the queue worker looks
        // down (the scheduler keeps running even when the worker is dead).
        $schedule->command('queue:check-health')
            ->hourly()
            ->withoutOverlapping()
            ->when(Feature::Supervision->enabled(...));
    }
}
