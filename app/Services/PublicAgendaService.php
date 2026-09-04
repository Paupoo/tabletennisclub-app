<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\PublicAgenda\AgendaDay;
use App\Data\PublicAgenda\AgendaEntry;
use App\Data\PublicAgenda\PublicAgenda;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\AgendaFamily;
use App\Domains\Shared\Enums\MeetingStatusEnum;
use App\Domains\Shared\Enums\MeetingTypeEnum;
use App\Domains\Shared\Enums\TrainingCancellationType;
use App\Domains\Shared\Enums\TrainingType;
use App\Domains\Trainings\Models\Training;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

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

    /**
     * How many whole weeks the grid draws, starting on the Monday of the
     * current week.
     *
     * Five rows is what makes the rhythm visible: four near-identical lines
     * prove the club runs every week, where a fortnight only shows it twice.
     * The window rolls rather than following calendar months, so it is never
     * mostly behind us — a strict month read on the 28th would be spent.
     */
    private const int WINDOW_WEEKS = 5;

    /**
     * @param  Season|null  $scheduleSeason  The season whose rhythm is described,
     *                                       already resolved by the caller so the
     *                                       rhythm and its banner always agree.
     */
    public function forHomepage(?Season $scheduleSeason = null): PublicAgenda
    {
        $from = CarbonImmutable::now()->startOfWeek();
        $to = $from->addWeeks(self::WINDOW_WEEKS)->subDay()->endOfDay();

        $days = $this->fillGrid($from, $to, $this->groupEntriesByDate($this->between($from, $to)));

        return new PublicAgenda(
            days: $days,
            exceptions: $this->exceptionsIn($days),
        );
    }

    /**
     * The pack's name as a visitor should read it.
     *
     * The club names its packs "Lundi — Entraînement supervisé", which reads
     * fine in an admin list sorted by nothing in particular. In a calendar the
     * square already says LUNDI 07, so the prefix states the day a second time
     * on every single line.
     */
    private function activityName(Training $training): string
    {
        $name = $training->trainingPack?->name;

        if ($name === null || $name === '') {
            return __('Training');
        }

        return (string) preg_replace(
            '/^(lundi|mardi|mercredi|jeudi|vendredi|samedi|dimanche)\s+[—–-]\s+/iu',
            '',
            $name,
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
            ...$this->tournaments($from, $to),
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
     * One AgendaDay per calendar day of the window, empty squares included.
     *
     * @param  array<string, list<AgendaEntry>>  $byDate
     * @return list<AgendaDay>
     */
    private function fillGrid(CarbonImmutable $from, CarbonImmutable $to, array $byDate): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $days = [];

        for ($date = $from; $date->lte($to); $date = $date->addDay()) {
            $days[] = new AgendaDay(
                date: $date->startOfDay(),
                entries: $byDate[$date->toDateString()] ?? [],
                isToday: $date->isSameDay($today),
                isPast: $date->startOfDay()->lt($today),
            );
        }

        return $days;
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
            family: AgendaFamily::TRAINING,
            title: $this->activityName($first),
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
                family: AgendaFamily::CLUB_LIFE,
                title: $meeting->title,
                location: $meeting->location,
            ))
            ->all();
    }

    /**
     * @param  list<AgendaEntry>  $entries
     * @return array<string, list<AgendaEntry>>
     */
    private function groupEntriesByDate(array $entries): array
    {
        usort($entries, fn (AgendaEntry $a, AgendaEntry $b): int => $a->startsAt <=> $b->startsAt);

        $byDate = [];

        foreach ($entries as $entry) {
            $byDate[$entry->startsAt->toDateString()][] = $entry;
        }

        return $byDate;
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
        $matches = Interclub::with(['visitedTeam', 'visitingTeam.club'])
            ->withoutByes()
            ->where('start_date_time', '>=', $from)
            ->when($to, fn ($query) => $query->where('start_date_time', '<=', $to))
            ->whereHas('visitedTeam.club', fn ($query) => $query->where('is_own_club', true))
            ->orderBy('start_date_time')
            ->get();

        $entries = [];

        // Nine teams share one hall: two or three of them receiving on the same
        // Saturday is the ordinary case, not the exception. Listed one by one
        // they fill the square and crowd out everything else, so a busy day
        // becomes a single entry that says how many.
        foreach ($matches->groupBy(fn (Interclub $match): string => $match->start_date_time->toDateString()) as $sameDay) {
            $first = $sameDay->first();

            $entries[] = new AgendaEntry(
                startsAt: CarbonImmutable::parse($first->start_date_time),
                endsAt: null,
                family: AgendaFamily::COMPETITION,
                title: $sameDay->count() === 1
                    ? __('Interclubs · :team v. :opponent', [
                        'team' => $first->visitedTeam?->name ?? '',
                        'opponent' => $first->visitingTeam?->club?->name ?? '',
                    ])
                    : __('Interclubs · :count home matches', ['count' => $sameDay->count()]),
                location: $first->address,
            );
        }

        return $entries;
    }

    /**
     * One "entrée libre" per day, however many rooms are open.
     *
     * The club opens two rooms on a Monday evening and models them as two
     * packs. That is right in the back office — they have their own capacity —
     * but on the public grid it reads as two different offers, half an hour
     * apart, with the same name. One line says it better.
     *
     * @param  Collection<int, Training>  $freePlay
     * @return list<AgendaEntry>
     */
    private function mergedFreePlay(Collection $freePlay): array
    {
        $entries = [];

        foreach ($freePlay->groupBy(fn (Training $training): string => $training->start->toDateString()) as $sameDay) {
            $earliest = $sameDay->sortBy('start')->first();

            $entries[] = $sameDay->count() === 1
                ? $this->sessionEntry($earliest)
                : new AgendaEntry(
                    startsAt: CarbonImmutable::parse($earliest->start),
                    endsAt: CarbonImmutable::parse($sameDay->max('end')),
                    family: AgendaFamily::TRAINING,
                    title: __('Free play'),
                    location: null,
                    cancellation: $this->cancellationOf($earliest),
                    cancellationNote: $earliest->cancellation_note,
                );
        }

        return $entries;
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
            family: AgendaFamily::TRAINING,
            title: $this->activityName($training),
            location: $training->room?->name,
            cancellation: $this->cancellationOf($training),
            cancellationNote: $training->cancellation_note,
        );
    }

    /**
     * The tournaments the club has actually announced.
     *
     * `onTheCalendar()` is the club's own answer to "is this happening": it
     * already excludes drafts, which nobody has been told about, and
     * cancellations, which are precisely not happening.
     *
     * @return list<AgendaEntry>
     */
    private function tournaments(CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        return Tournament::onTheCalendar()
            ->whereNotNull('start_date')
            ->where('start_date', '>=', $from->toDateString())
            ->when($to, fn ($query) => $query->where('start_date', '<=', $to->toDateString()))
            ->orderBy('start_date')
            ->get()
            ->map(fn (Tournament $tournament): AgendaEntry => new AgendaEntry(
                startsAt: CarbonImmutable::parse($tournament->startsAt()),
                endsAt: $tournament->end_date ? CarbonImmutable::parse($tournament->end_date) : null,
                family: AgendaFamily::COMPETITION,
                title: $tournament->name,
                location: $tournament->location,
            ))
            ->all();
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

        [$freePlay, $sessions] = $sessions->partition(
            fn (Training $training): bool => $training->type === TrainingType::FREE->value,
        );

        $entries = $this->mergedFreePlay($freePlay);

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
