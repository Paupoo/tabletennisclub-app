<?php

declare(strict_types=1);

namespace App\Console\Commands\Queue;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use App\Mail\QueueStalledMail;
use App\Support\QueueHealth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CheckQueueHealthCommand extends Command
{
    protected $description = 'Email the admins directly (bypassing the queue) when the queue worker looks stalled.';

    protected $signature = 'queue:check-health';

    public function handle(): int
    {
        if (! QueueHealth::isStalled()) {
            $this->info('Queue healthy — nothing to report.');

            return self::SUCCESS;
        }

        $admins = User::role(Role::ADMINISTRATOR->value)->get()
            ->map(fn (User $admin): ?string => $admin->contactEmail())
            ->filter()
            ->values()
            ->all();

        if ($admins === []) {
            return self::SUCCESS;
        }

        // sendNow: the scheduler still runs when the worker is dead, and the
        // dead worker is precisely what this mail reports.
        Mail::to($admins)->sendNow(new QueueStalledMail(
            pendingCount: QueueHealth::pendingCount(),
            oldestMinutes: QueueHealth::oldestPendingMinutes() ?? 0,
            failedCount: QueueHealth::failedCount(),
        ));

        $this->warn('Stalled queue detected — alert sent to the admins.');

        return self::SUCCESS;
    }
}
