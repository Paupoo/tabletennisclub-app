<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Services;

use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingAgendaItem;
use App\Domains\Shared\Enums\MeetingStatusEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The points a meeting did not reach, offered again to the next one of its kind.
 *
 * Only the last meeting held counts: a point carried over and missed again is
 * offered from that meeting, and an older one nobody took up has gone stale. A
 * committee's points come back to a committee, an assembly's to an assembly —
 * an assembly point needs a formal convocation, it is not moved to a committee.
 */
final class AgendaCarryOver
{
    /**
     * Keep the ticked points — added at the end of the agenda, tied to where
     * they come from — and let the others go for good.
     *
     * @param  list<int>  $keptIds
     */
    public static function apply(Meeting $meeting, array $keptIds): void
    {
        $candidates = self::candidatesFor($meeting);

        DB::transaction(function () use ($meeting, $candidates, $keptIds): void {
            $position = (int) $meeting->agendaItems()->max('sort_order');

            foreach ($candidates as $point) {
                if (in_array($point->id, $keptIds, true)) {
                    $meeting->agendaItems()->create([
                        'sort_order' => ++$position,
                        'title' => $point->title,
                        'description' => $point->description,
                        'carried_from_id' => $point->id,
                    ]);
                } else {
                    $point->update(['carry_over_dismissed_at' => now()]);
                }
            }
        });
    }

    /**
     * What this meeting may take up again: nothing once it has taken place.
     *
     * @return Collection<int, MeetingAgendaItem>
     */
    public static function candidatesFor(Meeting $meeting): Collection
    {
        if (! in_array($meeting->status, [MeetingStatusEnum::PLANNING, MeetingStatusEnum::CONFIRMED], true)) {
            return new Collection;
        }

        $source = Meeting::query()
            ->whereKeyNot($meeting->id)
            ->where('type', $meeting->type)
            ->where('status', '!=', MeetingStatusEnum::CANCELLED)
            ->where('scheduled_at', '<', $meeting->scheduled_at ?? now())
            ->has('minutes')
            ->latest('scheduled_at')
            ->orderByDesc('id')
            ->first();

        if ($source === null) {
            return new Collection;
        }

        return MeetingAgendaItem::query()
            ->where('meeting_id', $source->id)
            ->whereNull('discussed_at')
            ->whereNull('carry_over_dismissed_at')
            ->whereDoesntHave('carriedInto', fn (Builder $taken) => $taken->whereHas('meeting', fn (Builder $meeting) => $meeting->where('status', '!=', MeetingStatusEnum::CANCELLED)))
            ->with('meeting')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}
