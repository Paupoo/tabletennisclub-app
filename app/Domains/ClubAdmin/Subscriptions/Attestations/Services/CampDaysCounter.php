<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Subscriptions\Attestations\Services;

use App\Data\Attestation\CampDays;
use App\Domains\ClubAdmin\Subscriptions\Models\SubscriptionTrainingPack;
use App\Domains\Trainings\Models\Training;
use Illuminate\Support\Carbon;

/**
 * Counts the days of a camp one member was enrolled on.
 *
 * Sessions called off do not count: nobody took part in a day that did not
 * happen. A member who joined late or left early is bounded by the dates on
 * their line, which is what « enrolled on » means.
 */
final readonly class CampDaysCounter
{
    public function for(SubscriptionTrainingPack $line): CampDays
    {
        $sessions = Training::query()
            ->where('training_pack_id', $line->training_pack_id)
            ->where('status', 'scheduled')
            ->when($line->starts_on, fn ($query, string $from) => $query->whereDate('start', '>=', $from))
            ->when($line->ends_on, fn ($query, string $to) => $query->whereDate('start', '<=', $to))
            ->orderBy('start')
            ->get(['start', 'end']);

        $dates = $sessions
            ->groupBy(fn (Training $session): string => $session->start->toDateString())
            ->map(function ($day, string $date): array {
                $from = $day->min(fn (Training $session): Carbon => $session->start);
                $to = $day->max(fn (Training $session): Carbon => $session->end ?? $session->start);

                return [
                    'date' => Carbon::parse($date),
                    'half' => $from->diffInMinutes($to) < CampDays::HALF_DAY_BELOW_MINUTES,
                ];
            })
            ->values()
            ->all();

        return new CampDays($dates);
    }
}
