<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingActionItem;
use App\Domains\Meetings\Models\MeetingAgendaItem;
use App\Domains\Meetings\Models\MeetingDecision;
use App\Domains\Meetings\Models\MeetingMinutes;
use App\Domains\Meetings\Models\MeetingUser;
use App\Domains\Shared\Enums\MeetingTypeEnum;
use App\Domains\Shared\Enums\MeetingUserStatusEnum;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Enums\Role;
use App\Jobs\SendMeetingMinutesJob;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Mary\Traits\Toast;

/*
 * The note taker's desk: minutes taken live, point by point.
 *
 * Each agenda point gathers what was said on it, the decisions taken and the
 * actions handed out; what came up outside the agenda has a block of its own.
 * Every change is written at once, row by row — a decision, an action, a
 * point's discussion — so an action keeps its id while the assignee ticks it
 * done from the reading page, and nothing waits for a "save".
 *
 * One pen: whoever writes first takes it; the others read live (poll) until
 * they take it over. Opening the page never takes it.
 */
new class extends Component
{
    use HasBreadcrumbs, Toast;

    /** The key of the "outside the agenda" block in the quick-add fields. */
    private const string OUTSIDE = 'outside';

    /** @var array<int, array{title: string, description: string, assigned_to_id: string, due_date: string}> keyed by action id */
    public array $actions = [];

    /** @var array<int, string> */
    public array $announcements = [];

    public string $attendanceSearch = '';

    /** @var array<int, string> decision id => markdown */
    public array $decisionBodies = [];

    /** @var array<int, string> agenda item id => markdown */
    public array $discussions = [];

    #[Locked]
    public int $meetingId;

    /** @var array<string, string> block key (agenda item id or "outside") => the action being typed */
    public array $newAction = [];

    /** @var array<string, string> block key => the decision being typed */
    public array $newDecision = [];

    /** What was said outside the agenda (the former "additional notes"). */
    public string $notes = '';

    public ?string $savedAt = null;

    public string $walkInSearch = '';

    // ── Attendance ────────────────────────────────────────────────────

    /** A member who came without answering the invitation, or without being invited. */
    public function addWalkIn(int $userId): void
    {
        abort_unless($this->canManage, 403);

        if (! $this->claimPen()) {
            return;
        }

        $member = User::active()->whereKey($userId)->firstOrFail();

        $this->meeting->users()->syncWithoutDetaching([
            $member->id => ['status' => MeetingUserStatusEnum::ATTENDED->value, 'response_at' => now()],
        ]);

        $this->walkInSearch = '';
        $this->saved();
    }

    /**
     * The attendees, filtered by the search, those who said yes first.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function attendees(): Collection
    {
        $search = mb_strtolower(trim($this->attendanceSearch));
        $order = [
            MeetingUserStatusEnum::CONFIRMED->value => 0,
            MeetingUserStatusEnum::ATTENDED->value => 0,
            MeetingUserStatusEnum::INVITED->value => 1,
            MeetingUserStatusEnum::ABSENT->value => 1,
            MeetingUserStatusEnum::DECLINED->value => 2,
        ];

        return $this->meeting->users
            ->filter(fn (User $user): bool => $search === '' || str_contains(mb_strtolower($user->full_name), $search))
            ->sortBy([
                fn (User $a, User $b): int => ($order[$this->statusOf($a)->value] ?? 3) <=> ($order[$this->statusOf($b)->value] ?? 3),
                fn (User $a, User $b): int => strcmp($a->last_name . $a->first_name, $b->last_name . $b->first_name),
            ])
            ->values();
    }

    /** @return array{present: int, excused: int, absent: int, pending: int} */
    #[Computed]
    public function attendanceCounts(): array
    {
        $statuses = $this->meeting->users->map(fn (User $user): MeetingUserStatusEnum => $this->statusOf($user));

        return [
            'present' => $statuses->filter(fn ($status): bool => $status === MeetingUserStatusEnum::ATTENDED)->count(),
            'excused' => $statuses->filter(fn ($status): bool => $status === MeetingUserStatusEnum::DECLINED)->count(),
            'absent' => $statuses->filter(fn ($status): bool => $status === MeetingUserStatusEnum::ABSENT)->count(),
            'pending' => $statuses->filter(fn ($status): bool => in_array($status, [MeetingUserStatusEnum::CONFIRMED, MeetingUserStatusEnum::INVITED], true))->count(),
        ];
    }

    /**
     * One tap moves a member along: present → absent → back to their answer.
     *
     * The answer is not kept apart from the attendance, so "back" means what
     * the reply most likely was: confirmed if they answered, invited if not.
     */
    public function cycleAttendance(int $userId): void
    {
        abort_unless($this->canManage, 403);

        if (! $this->claimPen()) {
            return;
        }

        $attendee = $this->meeting->users->firstWhere('id', $userId);
        abort_if($attendee === null, 404);
        $registration = $this->registrationOf($attendee);

        $next = match ($registration->status) {
            MeetingUserStatusEnum::ATTENDED => MeetingUserStatusEnum::ABSENT,
            MeetingUserStatusEnum::ABSENT => $registration->response_at !== null ? MeetingUserStatusEnum::CONFIRMED : MeetingUserStatusEnum::INVITED,
            default => MeetingUserStatusEnum::ATTENDED,
        };

        $this->meeting->users()->updateExistingPivot($userId, ['status' => $next->value]);
        $this->saved();
    }

    /** Those who said yes came: the usual case, corrected one by one afterwards. */
    public function markAllConfirmedPresent(): void
    {
        abort_unless($this->canManage, 403);

        if (! $this->claimPen()) {
            return;
        }

        $confirmed = $this->meeting->users
            ->filter(fn (User $user): bool => $this->statusOf($user) === MeetingUserStatusEnum::CONFIRMED)
            ->modelKeys();

        foreach ($confirmed as $userId) {
            $this->meeting->users()->updateExistingPivot($userId, ['status' => MeetingUserStatusEnum::ATTENDED->value]);
        }

        $this->saved();
    }

    /**
     * Active members not on the list yet, matching the walk-in search.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function walkInCandidates(): Collection
    {
        $search = trim($this->walkInSearch);

        if (mb_strlen($search) < 2) {
            return collect();
        }

        return User::active()
            ->whereNotIn('users.id', $this->meeting->users->modelKeys())
            ->where(fn ($query) => $query
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%"))
            ->orderBy('last_name')
            ->orderBy('users.id')
            ->limit(8)
            ->get();
    }

    // ── Agenda points ─────────────────────────────────────────────────

    /**
     * The point being discussed: the first one not ticked off yet.
     */
    #[Computed]
    public function currentPointId(): ?int
    {
        return $this->meeting->agendaItems->first(fn (MeetingAgendaItem $item): bool => $item->discussed_at === null)?->id;
    }

    /**
     * Tick a point discussed, or back. Returns the point to open next, so
     * ticking one off moves the page along with the meeting.
     */
    public function toggleDiscussed(int $itemId): ?int
    {
        abort_unless($this->canManage, 403);

        // Ticking a point off is note-taking too: claim the pen.
        if (! $this->claimPen()) {
            return $itemId;
        }

        $item = $this->meeting->agendaItems()->findOrFail($itemId);
        $item->update(['discussed_at' => $item->discussed_at ? null : now()]);
        $this->saved();

        return $item->discussed_at === null ? $item->id : $this->currentPointId;
    }

    // ── Decisions ─────────────────────────────────────────────────────

    /** Record the decision typed in a block, on Enter. */
    public function addDecision(string $block): void
    {
        abort_unless($this->canManage, 403);
        $body = trim($this->newDecision[$block] ?? '');

        if ($body === '' || ! $this->claimPen()) {
            return;
        }

        $decision = $this->meeting->decisions()->create([
            'agenda_item_id' => $this->agendaItemIdFor($block),
            'body' => $body,
            'sort_order' => (int) $this->meeting->decisions()->max('sort_order') + 1,
        ]);

        $this->decisionBodies[$decision->id] = $body;
        $this->newDecision[$block] = '';
        $this->saved();
    }

    public function removeDecision(int $decisionId): void
    {
        abort_unless($this->canManage, 403);

        if (! $this->claimPen()) {
            return;
        }

        $this->meeting->decisions()->whereKey($decisionId)->delete();
        unset($this->decisionBodies[$decisionId]);
        $this->saved();
    }

    // ── Actions ───────────────────────────────────────────────────────

    /** Record the action typed in a block, on Enter; who and when come after. */
    public function addAction(string $block): void
    {
        abort_unless($this->canManage, 403);
        $title = trim($this->newAction[$block] ?? '');

        if ($title === '' || ! $this->claimPen()) {
            return;
        }

        $action = $this->meeting->actionItems()->create([
            'agenda_item_id' => $this->agendaItemIdFor($block),
            'title' => mb_substr($title, 0, 255),
            'is_completed' => false,
        ]);

        $this->actions[$action->id] = ['title' => $action->title, 'description' => '', 'assigned_to_id' => '', 'due_date' => ''];
        $this->newAction[$block] = '';
        $this->saved();
    }

    public function removeAction(int $actionId): void
    {
        abort_unless($this->canManage, 403);

        if (! $this->claimPen()) {
            return;
        }

        $this->meeting->actionItems()->whereKey($actionId)->delete();
        unset($this->actions[$actionId]);
        $this->saved();
    }

    /** "In a week", "in two weeks", "end of the month": the due dates a meeting actually gives. */
    public function setDue(int $actionId, string $preset): void
    {
        abort_unless($this->canManage, 403);

        $date = match ($preset) {
            'week' => now()->addWeek(),
            'two_weeks' => now()->addWeeks(2),
            'month_end' => now()->endOfMonth(),
            default => abort(422),
        };

        $this->actions[$actionId]['due_date'] = $date->format('Y-m-d');
        $this->updated("actions.{$actionId}.due_date");
    }

    public function toggleAction(int $actionId): void
    {
        abort_unless($this->canManage, 403);

        if (! $this->claimPen()) {
            return;
        }

        $action = $this->actionOrFail($actionId);
        $action->update(['is_completed' => ! $action->is_completed]);
        $this->saved();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    #[Computed]
    public function usersForAssignment(): array
    {
        return User::role([Role::ADMINISTRATOR->value, Role::COMMITTEE->value])
            ->orderBy('last_name')->orderBy('users.id')->get()
            ->map(fn (User $u): array => ['id' => $u->id, 'name' => $u->full_name])
            ->values()
            ->all();
    }

    // ── Autosave ──────────────────────────────────────────────────────

    /**
     * Every field is written as it changes: on blur for text (and for the
     * editors, which send on blur), at once for the pickers.
     */
    public function updated(string $name): void
    {
        abort_unless($this->canManage, 403);

        $segments = explode('.', $name);

        if (! in_array($segments[0], ['announcements', 'notes', 'discussions', 'decisionBodies', 'actions'], true)) {
            return;
        }

        if (! $this->claimPen()) {
            $this->hydrateDraft($this->meeting);

            return;
        }

        match ($segments[0]) {
            'announcements', 'notes' => $this->ensureMinutes()->update([
                'announcements' => array_values(array_filter($this->announcements, filled(...))),
                'notes' => filled($this->notes) ? $this->notes : null,
            ]),
            'discussions' => $this->meeting->agendaItems()->findOrFail((int) $segments[1])
                ->update(['discussion' => filled($this->discussions[(int) $segments[1]] ?? '') ? $this->discussions[(int) $segments[1]] : null]),
            'decisionBodies' => $this->saveDecisionBody((int) $segments[1]),
            'actions' => $this->saveActionField((int) $segments[1], $segments[2] ?? ''),
        };

        $this->saved();
    }

    public function addAnnouncement(): void
    {
        abort_unless($this->canManage, 403);

        if ($this->claimPen()) {
            $this->announcements[] = '';
        }
    }

    public function removeAnnouncement(int $i): void
    {
        abort_unless($this->canManage, 403);

        array_splice($this->announcements, $i, 1);
        $this->updated('announcements');
    }

    // ── The pen ───────────────────────────────────────────────────────

    #[Computed]
    public function canManage(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can(Permission::MeetingsManage->value);
    }

    /** Whether the current user holds the note-taking lock. */
    #[Computed]
    public function holdsLock(): bool
    {
        return $this->meeting->minutesLockHolder()?->id === auth()->id();
    }

    /** Who currently takes notes (null when the lock is free or stale). */
    #[Computed]
    public function lockHolder(): ?User
    {
        return $this->meeting->minutesLockHolder();
    }

    /** Somebody else holds the pen: everything is read-only here. */
    #[Computed]
    public function readOnly(): bool
    {
        return $this->lockHolder instanceof User && ! $this->holdsLock;
    }

    /**
     * Poll target for read-only viewers: pull the note taker's latest writes.
     * With a free or held pen there is nobody else writing, and a render would
     * only disturb the fields being typed in.
     */
    public function syncDraft(): void
    {
        if (! $this->readOnly) {
            $this->skipRender();

            return;
        }

        unset($this->meeting);
        $this->hydrateDraft($this->meeting);
    }

    /** Explicit takeover: wrestle the pen from the current holder. */
    public function takeOver(): void
    {
        abort_unless($this->canManage, 403);

        $this->meeting->acquireMinutesLock(auth()->user(), force: true);
        unset($this->meeting, $this->holdsLock, $this->lockHolder, $this->readOnly);

        $this->toast(type: 'success', title: __('You are taking the notes now'));
    }

    // ── Publishing ────────────────────────────────────────────────────

    public function publishMinutes(): void
    {
        abort_unless($this->canManage, 403);

        if (! $this->meeting->scheduled_at?->isPast()) {
            $this->toast(type: 'error', title: __('This meeting has not taken place yet — publish once it is over'));

            return;
        }

        if (! $this->meeting->acquireMinutesLock(auth()->user())) {
            $this->toast(type: 'error', title: __('Take over the notes before publishing'));

            return;
        }

        $this->ensureMinutes()->update([
            'is_published' => true,
            'published_at' => now(),
            'published_by' => auth()->id(),
        ]);

        unset($this->meeting);
        $this->toast(type: 'success', title: __('Minutes published'));
    }

    public function sendMinutes(bool $toAll = false): void
    {
        abort_unless($this->canManage, 403);
        $meeting = $this->meeting;

        if (! $meeting->minutes?->is_published) {
            $this->toast(type: 'error', title: __('Publish the minutes first'));

            return;
        }

        // A committee meeting's minutes never leave the committee: they can name
        // a member in debt or a conflict. Only a general assembly's go to all.
        abort_if($toAll && $meeting->type !== MeetingTypeEnum::GENERAL_ASSEMBLY, 403);

        $recipients = $toAll
            ? User::active()->get()
            : User::role([Role::ADMINISTRATOR->value, Role::COMMITTEE->value])->get();

        // Recorded before the mails leave: sending to all is what opens a general
        // assembly's minutes, and the link must work from the first mail.
        $field = $toAll ? 'sent_to_all_at' : 'sent_to_committee_at';
        $meeting->minutes->update([$field => now()]);

        foreach ($recipients as $recipient) {
            SendMeetingMinutesJob::dispatch($meeting->id, $recipient->id);
        }

        $this->toast(type: 'success', title: __('Minutes sent to :n members', ['n' => $recipients->count()]));
        unset($this->meeting);
    }

    // ── Page ──────────────────────────────────────────────────────────

    #[Computed]
    public function meeting(): Meeting
    {
        return Meeting::with([
            'users',
            'minutes',
            'agendaItems',
            'decisions',
            'actionItems.assignedTo',
            'minutesEditor',
        ])->findOrFail($this->meetingId);
    }

    public function mount(Meeting $meeting): void
    {
        abort_unless($this->canManage, 403);

        $this->meetingId = $meeting->id;

        // Opening the page must never take the pen — a reader would dispossess
        // the note taker. It is claimed on the first write (claimPen).
        $this->hydrateDraft($meeting);
    }

    public function render(): View
    {
        return $this->view();
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'outside' => self::OUTSIDE,
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->add(__('Meeting Details'), route('admin.meetings.show', $this->meetingId))
            ->current(__('Minutes'));
    }

    private function actionOrFail(int $actionId): MeetingActionItem
    {
        return MeetingActionItem::query()->where('meeting_id', $this->meetingId)->findOrFail($actionId);
    }

    /** The agenda item a quick-add block writes to; null for "outside the agenda". */
    private function agendaItemIdFor(string $block): ?int
    {
        if ($block === self::OUTSIDE) {
            return null;
        }

        return $this->meeting->agendaItems()->findOrFail((int) $block)->id;
    }

    /**
     * Take the pen on a write. Succeeds when it is free, stale or already ours;
     * fails, with a warning, only while another member holds it live.
     */
    private function claimPen(): bool
    {
        if ($this->meeting->acquireMinutesLock(auth()->user())) {
            unset($this->meeting, $this->holdsLock, $this->lockHolder, $this->readOnly);

            return true;
        }

        $this->toast(type: 'warning', title: __(':name is taking notes', ['name' => $this->lockHolder?->full_name ?? '']));

        return false;
    }

    private function ensureMinutes(): MeetingMinutes
    {
        return $this->meeting->minutes()->firstOrCreate([]);
    }

    private function hydrateDraft(Meeting $meeting): void
    {
        $this->announcements = $meeting->minutes->announcements ?? [];
        $this->notes = $meeting->minutes->notes ?? '';
        $this->discussions = $meeting->agendaItems->mapWithKeys(fn (MeetingAgendaItem $item): array => [$item->id => $item->discussion ?? ''])->all();
        $this->decisionBodies = $meeting->decisions->mapWithKeys(fn (MeetingDecision $decision): array => [$decision->id => $decision->body])->all();
        $this->actions = $meeting->actionItems->mapWithKeys(fn (MeetingActionItem $item): array => [$item->id => [
            'title' => $item->title,
            'description' => $item->description ?? '',
            'assigned_to_id' => (string) ($item->assigned_to_id ?? ''),
            'due_date' => $item->due_date?->format('Y-m-d') ?? '',
        ]])->all();
    }

    /** The pivot row `Meeting::users()` exposes as `registration`. */
    private function registrationOf(User $user): MeetingUser
    {
        /** @var MeetingUser $registration */
        $registration = $user->getRelation('registration');

        return $registration;
    }

    private function statusOf(User $user): MeetingUserStatusEnum
    {
        return $this->registrationOf($user)->status;
    }

    private function saveActionField(int $actionId, string $field): void
    {
        $action = $this->actionOrFail($actionId);
        $value = $this->actions[$actionId][$field] ?? '';

        match ($field) {
            // A title cannot be emptied: the row would vanish from the minutes.
            'title' => filled(trim($value))
                ? $action->update(['title' => mb_substr(trim($value), 0, 255)])
                : $this->actions[$actionId]['title'] = $action->title,
            'description' => $action->update(['description' => filled($value) ? $value : null]),
            'assigned_to_id' => $action->update(['assigned_to_id' => filled($value) && collect($this->usersForAssignment)->contains('id', (int) $value) ? (int) $value : null]),
            'due_date' => $action->update(['due_date' => filled($value) && strtotime($value) !== false ? $value : null]),
            default => null,
        };
    }

    private function saveDecisionBody(int $decisionId): void
    {
        $decision = $this->meeting->decisions()->findOrFail($decisionId);
        $body = trim($this->decisionBodies[$decisionId] ?? '');

        // Emptied in the editor: keep the decision rather than lose it silently.
        if ($body === '') {
            $this->decisionBodies[$decisionId] = $decision->body;

            return;
        }

        $decision->update(['body' => $body]);
    }

    /**
     * Stamp the write. Once the minutes have been sent, a change is a
     * correction, and the reading page and the PDF say so.
     */
    private function saved(): void
    {
        $minutes = $this->meeting->minutes;

        if ($minutes !== null && ($minutes->sent_to_committee_at !== null || $minutes->sent_to_all_at !== null)) {
            $minutes->update(['corrected_at' => now()]);
        }

        $this->savedAt = now()->format('H:i');
        unset($this->meeting, $this->attendees, $this->attendanceCounts, $this->currentPointId, $this->walkInCandidates);
    }
};
