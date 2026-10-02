<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\MembershipStatus;
use App\Jobs\SendRenewalReminderJob;
use Illuminate\Support\Facades\Bus;

/**
 * Remind last season's members who have not registered again.
 *
 * Only the members to follow up are written to: a selection taken from the
 * list mixes them with renewed members, departures and newcomers, and none of
 * those should hear that the club is still waiting for them.
 *
 * A member reminded less than a week ago is left out. Two secretaries working
 * through the same list, or one who clicks twice, would otherwise send the same
 * family the same message on the same morning.
 *
 * The date is stamped when the reminder is queued, not when it leaves: a round
 * spread over the limiter takes the best part of an hour, and the week has to
 * hold from the click on, or a second click in that hour sends everything again.
 */
class SendRenewalRemindersAction
{
    public const int REMINDER_INTERVAL_DAYS = 7;

    /**
     * @param  array<int, int|string>  $memberIds
     * @return array{queued: int, recentlyReminded: int, notToFollowUp: int, unreachable: int}
     */
    public static function handle(array $memberIds): array
    {
        $members = User::query()
            ->withMembershipFacts()
            ->with('guardians')
            ->whereIn('id', $memberIds)
            ->get();

        $toFollowUp = $members->filter(fn (User $member): bool => $member->membershipStatus() === MembershipStatus::ToFollowUp);

        $cutoff = now()->subDays(self::REMINDER_INTERVAL_DAYS);
        $recentlyReminded = $toFollowUp->filter(fn (User $member): bool => $member->renewal_reminded_at?->greaterThan($cutoff) ?? false);

        $due = $toFollowUp->diff($recentlyReminded);
        $unreachable = $due->filter(fn (User $member): bool => $member->contactEmails() === []);
        $targets = $due->diff($unreachable);

        if ($targets->isNotEmpty()) {
            User::query()->whereIn('id', $targets->modelKeys())->update(['renewal_reminded_at' => now()]);

            Bus::batch(
                $targets
                    ->map(fn (User $member): SendRenewalReminderJob => new SendRenewalReminderJob($member->id))
                    ->values()
                    ->all()
            )->name('renewal-reminders')->dispatch();
        }

        return [
            'queued' => $targets->count(),
            'recentlyReminded' => $recentlyReminded->count(),
            'notToFollowUp' => $members->count() - $toFollowUp->count(),
            'unreachable' => $unreachable->count(),
        ];
    }
}
