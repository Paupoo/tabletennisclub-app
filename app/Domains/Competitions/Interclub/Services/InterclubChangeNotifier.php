<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Services;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\InterclubChange;
use App\Domains\Competitions\Interclub\Notifications\InterclubChangesHeldNotification;
use App\Domains\Competitions\Interclub\Notifications\InterclubOwnForfeitAlertNotification;
use App\Domains\Shared\Enums\InterclubChangeKind;
use App\Domains\Shared\Enums\InterclubChangeStatus;
use App\Domains\Shared\Enums\Role;
use App\Jobs\SendInterclubChangeJob;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Tells the team what the federation changed.
 *
 * The audience is the lineup mail's: the players selected on the fixture,
 * reinforcements from another team included, and the whole roster of our side.
 * The captain is taken out of it and told separately that the team has been
 * told — the message they would otherwise have had to write themselves.
 *
 * Our own forfeit also goes to whoever holds the interclubs duty: it is
 * usually wanted, but when the federation filed it on the wrong side, that is
 * the person who can say so before the fine follows.
 */
class InterclubChangeNotifier
{
    /**
     * More messages than this in one run, and none is sent until somebody looks.
     *
     * A forfeit or a postponement touches one or two fixtures; a batch larger
     * than this is a federation error, or a correction that will be followed
     * by its own — two waves of contradicting mails to the whole club.
     * Decided on 2026-10-01.
     */
    public const int HOLD_ABOVE = 5;

    /**
     * Send what one sync noticed, unless there is too much of it to trust.
     *
     * Counted in messages, not fixtures: a withdrawal is one piece of news,
     * however many evenings it cancels.
     *
     * @param  EloquentCollection<int, InterclubChange>  $changes
     * @return bool Whether the changes were held for review instead of sent.
     */
    public function deliver(EloquentCollection $changes): bool
    {
        if ($this->groups($changes)->count() <= self::HOLD_ABOVE) {
            $this->notify($changes);

            return false;
        }

        InterclubChange::whereKey($changes->modelKeys())->update(['status' => InterclubChangeStatus::HELD]);

        Notification::send($this->interclubsDuty(), new InterclubChangesHeldNotification($changes->count()));

        return true;
    }

    /**
     * The changes as the team will receive them: one message each, except the
     * fixtures a single withdrawal cancels, which travel together.
     *
     * @param  Collection<int, InterclubChange>  $changes
     * @return Collection<string, Collection<int, InterclubChange>>
     */
    public function groups(Collection $changes): Collection
    {
        return $changes
            ->toBase()
            ->sortBy('id')
            ->groupBy(fn (InterclubChange $change): string => $change->group_key ?? 'change:' . $change->id);
    }

    /**
     * Whoever holds the interclubs duty, or the administrators when nobody does:
     * an alert that reaches nobody is the one case worse than no alert.
     *
     * The members to leave out — those leaving the club, who would never
     * receive it — are left out before falling back: when they held the duty
     * alone, the administrators hear of it in their place.
     *
     * @param  array<int, int>  $exceptIds  the members who cannot be reached
     * @return Collection<int, User>
     */
    public function interclubsDuty(array $exceptIds = []): Collection
    {
        $holders = User::role(Role::INTERCLUBS->value)->whereNotIn('users.id', $exceptIds)->get();

        return $holders->isNotEmpty()
            ? $holders
            : User::role(Role::ADMINISTRATOR->value)->whereNotIn('users.id', $exceptIds)->get();
    }

    /**
     * Send the given changes and mark them sent.
     *
     * @param  EloquentCollection<int, InterclubChange>  $changes
     */
    public function notify(EloquentCollection $changes, ?User $by = null): void
    {
        $changes->loadMissing(['interclub.visitedTeam.club', 'interclub.visitingTeam.club']);

        foreach ($this->groups($changes) as $group) {
            $this->notifyGroup($group);

            InterclubChange::whereKey($group->pluck('id'))->update([
                'status' => InterclubChangeStatus::SENT,
                'notified_at' => now(),
                'notified_by' => $by?->id,
            ]);
        }
    }

    /**
     * @param  Collection<int, InterclubChange>  $group
     */
    private function notifyGroup(Collection $group): void
    {
        $fixtures = $group->map(fn (InterclubChange $change): Interclub => $change->interclub);
        $team = $fixtures->first()->ourTeam();

        if ($team === null) {
            return;
        }

        $selected = $fixtures->flatMap(fn (Interclub $fixture): Collection => $fixture->users()
            ->wherePivot('is_selected', true)
            ->pluck('users.id'));

        $recipientIds = $team->users()->pluck('users.id')
            ->merge($selected)
            ->unique()
            ->reject(fn (int $id): bool => $id === $team->captain_id)
            ->values();

        $changeIds = $group->pluck('id')->all();

        foreach ($recipientIds as $userId) {
            SendInterclubChangeJob::dispatch($changeIds, $userId);
        }

        if ($team->captain_id !== null) {
            SendInterclubChangeJob::dispatch($changeIds, $team->captain_id, forCaptain: true, informedCount: $recipientIds->count());
        }

        $first = $group->first();

        if ($first->kind === InterclubChangeKind::FORFEIT && $first->forfeit?->isOurs()) {
            Notification::send($this->interclubsDuty(), new InterclubOwnForfeitAlertNotification($group->pluck('id')->all()));
        }
    }
}
