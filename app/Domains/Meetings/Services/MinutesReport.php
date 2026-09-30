<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Services;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingActionItem;
use App\Domains\Meetings\Models\MeetingAgendaItem;
use App\Domains\Meetings\Models\MeetingMinutes;
use App\Domains\Meetings\Models\MeetingUser;
use App\Domains\Shared\Enums\MeetingTypeEnum;
use App\Domains\Shared\Enums\MeetingUserStatusEnum;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Published minutes, laid out for reading: the page, the PDF and the mail all
 * read this, so the three never disagree on what is overdue or who was there.
 *
 * The order is the reader's, not the note taker's: what was decided and what
 * must be done come first, the course of the meeting after.
 *
 * Attendance follows the audience. A committee's minutes stay in the
 * committee, so every name is listed; a general assembly's go to every member,
 * so the absent are only counted — who was there is recorded, who stayed home
 * is nobody else's business. When nobody recorded attendance, the confirmed
 * replies stand in, and the report says so.
 */
final readonly class MinutesReport
{
    /** Overdue first, done last. */
    private const array URGENCY = ['overdue' => 0, 'todo' => 1, 'done' => 2];

    /**
     * @param  list<string>  $decisions  markdown, in the order they were taken
     * @param  list<string>  $announcements  markdown
     * @param  Collection<int, MinutesAction>  $actions
     * @param  Collection<int, MeetingAgendaItem>  $agenda
     * @param  Collection<int, User>  $present
     * @param  Collection<int, User>  $excused
     * @param  Collection<int, User>  $absent  empty for a general assembly, see $absentCount
     */
    private function __construct(
        public Meeting $meeting,
        public MeetingMinutes $minutes,
        public array $decisions,
        public array $announcements,
        public ?string $notes,
        public Collection $actions,
        public Collection $agenda,
        public Collection $present,
        public Collection $excused,
        public Collection $absent,
        public int $absentCount,
        public bool $attendanceRecorded,
        public Carbon $asOf,
    ) {}

    /**
     * @param  User|null  $reader  whose actions are flagged "yours"
     */
    public static function for(Meeting $meeting, ?User $reader = null): self
    {
        $meeting->loadMissing(['minutes.publisher', 'agendaItems', 'actionItems.assignedTo', 'users']);

        $minutes = $meeting->minutes;
        abort_if($minutes === null, 404);

        $today = now()->startOfDay();
        $byStatus = $meeting->users->groupBy(fn (User $user): string => self::registrationOf($user)->status->value);
        $of = fn (MeetingUserStatusEnum ...$statuses): Collection => collect($statuses)
            ->flatMap(fn (MeetingUserStatusEnum $status): Collection => $byStatus->get($status->value, collect()))
            ->sortBy(fn (User $user): string => $user->last_name . ' ' . $user->first_name)
            ->values();

        $attendanceRecorded = $of(MeetingUserStatusEnum::ATTENDED, MeetingUserStatusEnum::ABSENT)->isNotEmpty();
        $present = $attendanceRecorded
            ? $of(MeetingUserStatusEnum::ATTENDED)
            : $of(MeetingUserStatusEnum::CONFIRMED, MeetingUserStatusEnum::ATTENDED);
        $absent = $attendanceRecorded
            ? $of(MeetingUserStatusEnum::ABSENT, MeetingUserStatusEnum::CONFIRMED, MeetingUserStatusEnum::INVITED)
            : collect();

        $actions = $meeting->actionItems
            ->map(function (MeetingActionItem $item) use ($today, $reader): MinutesAction {
                $days = $item->due_date === null ? null : (int) $today->diffInDays($item->due_date->copy()->startOfDay(), false);

                return new MinutesAction(
                    item: $item,
                    status: match (true) {
                        $item->is_completed => 'done',
                        $days !== null && $days < 0 => 'overdue',
                        default => 'todo',
                    },
                    days: $days,
                    mine: $reader !== null && $item->assigned_to_id === $reader->id,
                );
            })
            ->sortBy([
                fn (MinutesAction $a, MinutesAction $b): int => self::URGENCY[$a->status] <=> self::URGENCY[$b->status],
                fn (MinutesAction $a, MinutesAction $b): int => ($a->days ?? PHP_INT_MAX) <=> ($b->days ?? PHP_INT_MAX),
            ])
            ->values();

        $isAssembly = $meeting->type === MeetingTypeEnum::GENERAL_ASSEMBLY;

        return new self(
            meeting: $meeting,
            minutes: $minutes,
            decisions: array_values(array_filter($minutes->decisions ?? [], filled(...))),
            announcements: array_values(array_filter($minutes->announcements ?? [], filled(...))),
            notes: filled($minutes->notes) ? $minutes->notes : null,
            actions: $actions,
            agenda: $meeting->agendaItems,
            present: $present,
            excused: $of(MeetingUserStatusEnum::DECLINED),
            absent: $isAssembly ? collect() : $absent,
            absentCount: $absent->count(),
            attendanceRecorded: $attendanceRecorded,
            asOf: now(),
        );
    }

    public function isAssembly(): bool
    {
        return $this->meeting->type === MeetingTypeEnum::GENERAL_ASSEMBLY;
    }

    /** @return Collection<int, MinutesAction> */
    public function myActions(): Collection
    {
        return $this->actions->filter(fn (MinutesAction $action): bool => $action->mine)->values();
    }

    public function overdueCount(): int
    {
        return $this->actions->filter(fn (MinutesAction $action): bool => $action->status === 'overdue')->count();
    }

    /** `PV-AG-2026-03-12.pdf` / `PV-comite-2026-03-12.pdf` */
    public function pdfFilename(): string
    {
        return sprintf(
            'PV-%s-%s.pdf',
            $this->isAssembly() ? 'AG' : 'comite',
            $this->meeting->scheduled_at?->format('Y-m-d') ?? $this->minutes->published_at?->format('Y-m-d') ?? 'sans-date',
        );
    }

    /** Null when no quorum was set; otherwise whether the people present reach it. */
    public function quorumReached(): ?bool
    {
        return $this->meeting->quorum === null ? null : $this->present->count() >= $this->meeting->quorum;
    }

    /** The pivot row `Meeting::users()` exposes as `registration`. */
    private static function registrationOf(User $user): MeetingUser
    {
        /** @var MeetingUser $registration */
        $registration = $user->getRelation('registration');

        return $registration;
    }
}
