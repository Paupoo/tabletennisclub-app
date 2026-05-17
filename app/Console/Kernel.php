<?php

declare(strict_types=1);

namespace App\Console;

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
        // Expire waitlist confirmation (48h) + payment deadlines (72h) + send daily reminders.
        $schedule->command('tournament:process-deadlines')->hourly();

        // Close registrations for tournaments whose deadline has passed.
        $schedule->command('tournament:close-registrations')->dailyAt('00:05');

        // Send weekly refund reminder to treasurer + secretary every Monday at 08:00.
        $schedule->command('payment:send-refund-reminder')->weeklyOn(1, '08:00');

        // On July 1st, ensure the upcoming two seasons are provisioned (+1 and +2).
        // Safe to run any time — idempotent, creates only what is missing.
        $schedule->command('season:provision')->yearlyOn(7, 1, '06:00');
    }
}
