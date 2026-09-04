<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\PublicAgenda\AgendaDay;
use App\Data\PublicAgenda\AgendaEntry;
use App\Data\PublicAgenda\InterclubRhythm;
use App\Data\PublicAgenda\PublicAgenda;
use App\Data\PublicAgenda\RhythmDay;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\MeetingStatusEnum;
use App\Domains\Shared\Enums\MeetingTypeEnum;
use App\Domains\Shared\Enums\TrainingCancellationType;
use App\Domains\Shared\Models\AppSetting;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use Carbon\CarbonImmutable;

/**
 * Builds the dated agenda the public homepage shows.
 *
 * The homepage used to render a weekly *pattern* drawn from TrainingPack —
 * "lundi 19h", every week, for ever. It could not say that next Monday's
 * session is cancelled, and it dropped multi-day packs entirely. This service
 * reads the real dated rows instead, so an exception saved by the club is an
 * exception a visitor sees.
 */
class PublicAgendaService
{
    /** Shortest run of consecutive days that reads as a camp rather than a habit. */
    private const int CAMP_MIN_DAYS = 3;

    /** How far ahead the homepage looks. */
    private const int WINDOW_DAYS = 14;

    /**
     * @param  Season|null  $scheduleSeason  The season whose rhythm is described,
     *                                       already resolved by the caller so the
     *                                       rhythm and its banner always agree.
     */
    public function forHomepage(?Season $scheduleSeason = null): PublicAgenda
    {
        $from = CarbonImmutable::now();
        $days = $this->groupByDay($this->between($from, $from->addDays(self::WINDOW_DAYS)->endOfDay()));

        if ($days !== []) {
            return new PublicAgenda(
                days: $days,
                exceptions: $this->exceptionsIn($days),
                rhythm: $this->rhythm($scheduleSeason),
                interclubRhythm: $this->interclubRhythm($scheduleSeason),
            );
        }

        // Out of season, or over the school holidays, the fortnight holds
        // nothing at all. A visitor is better served by "next up: summer camp,
        // 6-10 July" than by an empty box, so the window reaches forward until
        // it finds something — or stays empty when the club has nothing planned.
        $nextDays = $this->groupByDay($this->between($from, null));

        if ($nextDays === []) {
            return new PublicAgenda(
                days: [],
                rhythm: $this->rhythm($scheduleSeason),
                interclubRhythm: $this->interclubRhythm($scheduleSeason),
            );
        }

        return new PublicAgenda(
            days: [$nextDays[0]],
            exceptions: $this->exceptionsIn([$nextDays[0]]),
            rhythm: $this->rhythm($scheduleSeason),
            interclubRhythm: $this->interclubRhythm($scheduleSeason),
            isExtended: true,
        );
    }

    /**
     * Every public activity between two moments, `$to` null meaning unbounded.
     *
     * @return list<AgendaEntry>
     */
    private function between(CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        return [
            ...$this->trainings($from, $to),
            ...$this->homeInterclubs($from, $to),
            ...$this->generalAssemblies($from, $to),
        ];
    }

    /**
     * How a session was called off, or null while it holds.
     *
     * The two cancelled statuses map onto the enum the club already uses when
     * calling a session off, so the public side never invents a third notion.
     */
    private function cancellationOf(Training $training): ?TrainingCancellationType
    {
        return match ($training->status) {
            'cancelled_free' => TrainingCancellationType::FREE,
            'cancelled_closed' => TrainingCancellationType::CLOSED,
            default => null,
        };
    }

    /**
     * Every cancellation in the window, lifted clear of the days that hold it.
     *
     * On a phone only the first week is unfolded, and an exception in the
     * second week would sit behind a tap — the one thing that must never be a
     * tap away. A cancelled day folded inside a camp is buried deeper still, so
     * it is pulled out of the span too.
     *
     * @param  list<AgendaDay>  $days
     * @return list<AgendaEntry>
     */
    private function exceptionsIn(array $days): array
    {
        $exceptions = [];

        foreach ($days as $day) {
            foreach ($day->entries as $entry) {
                if ($entry->isCancelled()) {
                    $exceptions[] = $entry;
                }

                foreach ($entry->spanExceptions as $exception) {
                    $exceptions[] = $exception;
                }
            }
        }

        usort($exceptions, fn (AgendaEntry $a, AgendaEntry $b): int => $a->startsAt <=> $b->startsAt);

        return $exceptions;
    }

    /**
     * Turn one run into either a single spanning entry or one entry per day.
     *
     * Three days is the threshold because no weekly pack can produce three days
     * in a row, while every camp does — so the shape of the data decides, and
     * nobody has to remember to tick a "this is a camp" box.
     *
     * @param  list<Training>  $run
     * @return list<AgendaEntry>
     */
    private function foldRun(array $run): array
    {
        if (count($run) < self::CAMP_MIN_DAYS) {
            return array_map($this->sessionEntry(...), $run);
        }

        $first = $run[0];
        $last = $run[count($run) - 1];

        $exceptions = array_values(array_map(
            $this->sessionEntry(...),
            array_filter($run, fn (Training $t): bool => $this->cancellationOf($t) !== null),
        ));

        return [new AgendaEntry(
            startsAt: CarbonImmutable::parse($first->start),
            endsAt: CarbonImmutable::parse($first->end),
            title: $first->trainingPack?->name ?? __('Training'),
            location: $first->room?->name,
            spanEndsOn: CarbonImmutable::parse($last->start)->startOfDay(),
            spanExceptions: $exceptions,
        )];
    }

    /**
     * The general assemblies, and nothing else the committee discusses.
     *
     * A general assembly fills the hall and is exactly the kind of evening a
     * visitor turns up to expecting free play. A committee meeting is the
     * club's internal business — it is already kept off members' own calendars,
     * and the public block is no place to start publishing it.
     *
     * @return list<AgendaEntry>
     */
    private function generalAssemblies(CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        return Meeting::where('type', MeetingTypeEnum::GENERAL_ASSEMBLY->value)
            ->where('status', MeetingStatusEnum::CONFIRMED->value)
            ->where('scheduled_at', '>=', $from)
            ->when($to, fn ($query) => $query->where('scheduled_at', '<=', $to))
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (Meeting $meeting): AgendaEntry => new AgendaEntry(
                startsAt: CarbonImmutable::parse($meeting->scheduled_at),
                endsAt: $meeting->ends_at ? CarbonImmutable::parse($meeting->ends_at) : null,
                title: $meeting->title,
                location: $meeting->location,
            ))
            ->all();
    }

    /**
     * @param  list<AgendaEntry>  $entries
     * @return list<AgendaDay>
     */
    private function groupByDay(array $entries): array
    {
        usort($entries, fn (AgendaEntry $a, AgendaEntry $b): int => $a->startsAt <=> $b->startsAt);

        $days = [];

        foreach ($entries as $entry) {
            $days[$entry->startsAt->toDateString()][] = $entry;
        }

        return array_map(
            fn (string $date, array $dayEntries): AgendaDay => new AgendaDay(
                date: CarbonImmutable::parse($date)->startOfDay(),
                entries: $dayEntries,
            ),
            array_keys($days),
            $days,
        );
    }

    /**
     * The home matches, and only those.
     *
     * A visitor reading this block is deciding whether to come to the hall, so
     * an away fixture tells them nothing — and `visited_team` is precisely the
     * side playing at home. Byes are rounds without an opponent: nothing
     * happens, so nothing is announced.
     *
     * @return list<AgendaEntry>
     */
    private function homeInterclubs(CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        return Interclub::with(['visitedTeam', 'visitingTeam.club'])
            ->withoutByes()
            ->where('start_date_time', '>=', $from)
            ->when($to, fn ($query) => $query->where('start_date_time', '<=', $to))
            ->whereHas('visitedTeam.club', fn ($query) => $query->where('is_own_club', true))
            ->orderBy('start_date_time')
            ->get()
            ->map(fn (Interclub $match): AgendaEntry => new AgendaEntry(
                startsAt: CarbonImmutable::parse($match->start_date_time),
                endsAt: null,
                title: __('Interclubs: team :team hosts :opponent', [
                    'team' => $match->visitedTeam?->name ?? '',
                    'opponent' => trim(($match->visitingTeam?->club?->name ?? '') . ' ' . ($match->visitingTeam?->name ?? '')),
                ]),
                location: $match->address,
            ))
            ->all();
    }

    /**
     * The advertised interclub evening, or null when there is none to advertise.
     *
     * Silent without a season for the same reason the training rhythm is: the
     * whole "usual rhythm" line describes one season, and a club with no season
     * on the books has no habit to state.
     */
    private function interclubRhythm(?Season $season): ?InterclubRhythm
    {
        if ($season === null) {
            return null;
        }

        // Read in one go: AppSetting::get() costs a query per key, and four of
        // them for a single line of text is four too many on the busiest page
        // of the site.
        $settings = AppSetting::whereIn('key', [
            'interclub_schedule_enabled',
            'interclub_schedule_day',
            'interclub_schedule_time_start',
            'interclub_schedule_time_end',
        ])->pluck('value', 'key');

        if ($settings->get('interclub_schedule_enabled', '1') !== '1') {
            return null;
        }

        return new InterclubRhythm(
            day: (string) $settings->get('interclub_schedule_day', 'Vendredi'),
            startsAt: (string) $settings->get('interclub_schedule_time_start', '19:00'),
            endsAt: (string) $settings->get('interclub_schedule_time_end', '23:30'),
        );
    }

    /**
     * The club's usual week, merged one line per weekday.
     *
     * Only packs that actually recur weekly count: a camp runs on consecutive
     * days once a year and describes no habit, so it carries `days_of_week`
     * rather than a `day_of_week` and is left out here.
     *
     * Scoped to the season the banner above the block talks about, or the whole
     * catalogue when no season could be resolved. Without that scope a finished
     * season's packs would keep describing a rhythm the club no longer runs.
     *
     * @return list<RhythmDay>
     */
    private function rhythm(?Season $season): array
    {
        $packs = TrainingPack::query()
            ->where('is_active', true)
            ->whereNotNull('day_of_week')
            ->whereNotNull('start_time')
            ->whereNotNull('duration_minutes')
            ->when($season, fn ($query) => $query->where('season_id', $season->id))
            ->where(fn ($query) => $query->whereNull('pack_end_date')->orWhere('pack_end_date', '>=', today()))
            ->get()
            ->groupBy('day_of_week');

        $rhythm = [];

        foreach ($packs as $dayOfWeek => $dayPacks) {
            $starts = $dayPacks->map(fn (TrainingPack $pack): CarbonImmutable => CarbonImmutable::parse($pack->start_time));
            $ends = $dayPacks->map(fn (TrainingPack $pack): CarbonImmutable => CarbonImmutable::parse($pack->start_time)->addMinutes((int) $pack->duration_minutes));

            $rhythm[] = new RhythmDay(
                dayOfWeek: (int) $dayOfWeek,
                startsAt: $starts->min()->format('H:i'),
                endsAt: $ends->max()->format('H:i'),
            );
        }

        usort($rhythm, fn (RhythmDay $a, RhythmDay $b): int => $a->dayOfWeek <=> $b->dayOfWeek);

        return $rhythm;
    }

    /**
     * Split a pack's sessions into runs of back-to-back days.
     *
     * @param  list<Training>  $sessions  Ordered by start.
     * @return list<list<Training>>
     */
    private function runsOfConsecutiveDays(array $sessions): array
    {
        $runs = [];
        $current = [];

        foreach ($sessions as $session) {
            $previous = end($current);

            $follows = $previous !== false
                && CarbonImmutable::parse($previous->start)->startOfDay()->addDay()
                    ->equalTo(CarbonImmutable::parse($session->start)->startOfDay());

            if (! $follows && $current !== []) {
                $runs[] = $current;
                $current = [];
            }

            $current[] = $session;
        }

        if ($current !== []) {
            $runs[] = $current;
        }

        return $runs;
    }

    private function sessionEntry(Training $training): AgendaEntry
    {
        return new AgendaEntry(
            startsAt: CarbonImmutable::parse($training->start),
            endsAt: CarbonImmutable::parse($training->end),
            title: $training->trainingPack?->name ?? __('Training'),
            location: $training->room?->name,
            cancellation: $this->cancellationOf($training),
            cancellationNote: $training->cancellation_note,
        );
    }

    /**
     * @return list<AgendaEntry>
     */
    private function trainings(CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        $sessions = Training::with(['room', 'trainingPack'])
            ->where('start', '>=', $from)
            ->when($to, fn ($query) => $query->where('start', '<=', $to))
            ->orderBy('start')
            ->get();

        $entries = [];

        foreach ($sessions->groupBy('training_pack_id') as $packId => $packSessions) {
            // A session with no pack behind it is a one-off: nothing to fold.
            // `groupBy` casts its key to an array key, so a null pack id arrives
            // as the empty string rather than as null.
            if ($packId === '') {
                $entries = [...$entries, ...$packSessions->map(fn (Training $t): AgendaEntry => $this->sessionEntry($t))->all()];

                continue;
            }

            foreach ($this->runsOfConsecutiveDays($packSessions->all()) as $run) {
                $entries = [...$entries, ...$this->foldRun($run)];
            }
        }

        return $entries;
    }
}
