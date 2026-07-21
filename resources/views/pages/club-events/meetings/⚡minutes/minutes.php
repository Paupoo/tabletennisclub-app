<?php

declare(strict_types=1);

use App\Domains\Shared\Enums\Permission;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingMinutes;
use App\Domains\Meetings\Notifications\MeetingMinutesNotification;
use App\Domains\Shared\Enums\MeetingTypeEnum;
use App\Domains\Shared\Enums\MeetingUserStatusEnum;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs, Toast;

    /** @var array<int, array{title: string, description: string, assigned_to_id: string, due_date: string, is_completed: bool}> */
    public array $actionItems = [];

    /** @var array<int, string> */
    public array $announcements = [];

    /** @var array<int, string> */
    public array $decisions = [];

    #[Locked]
    public int $meetingId;

    public string $notes = '';

    public ?string $savedAt = null;

    public function addActionItem(): void
    {
        $this->actionItems[] = [
            'title' => '', 'description' => '',
            'assigned_to_id' => '', 'due_date' => '', 'is_completed' => false,
        ];
    }

    public function addAnnouncement(): void
    {
        $this->announcements[] = '';
    }

    public function addDecision(): void
    {
        $this->decisions[] = '';
    }

    #[Computed]
    public function canManage(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can(Permission::MeetingsManage->value);
    }

    public function markAbsent(int $userId): void
    {
        abort_unless($this->canManage, 403);
        $this->meeting->users()->updateExistingPivot($userId, [
            'status' => MeetingUserStatusEnum::ABSENT->value,
            'response_at' => now(),
        ]);
        unset($this->meeting);
    }

    public function markAttended(int $userId): void
    {
        abort_unless($this->canManage, 403);
        $this->meeting->users()->updateExistingPivot($userId, [
            'status' => MeetingUserStatusEnum::ATTENDED->value,
            'response_at' => now(),
        ]);
        unset($this->meeting);
    }

    #[Computed]
    public function meeting(): Meeting
    {
        return Meeting::with(['users', 'minutes', 'actionItems.assignedTo', 'agendaItems', 'minutesEditor'])->findOrFail($this->meetingId);
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

    public function mount(Meeting $meeting): void
    {
        abort_unless($this->canManage, 403);

        $this->meetingId = $meeting->id;

        // Opening the page must never take the pen — a reader would dispossess the
        // note taker. The pen is claimed on the first edit instead (see claimPen).
        $this->hydrateDraft($meeting);
    }

    /** Poll target for read-only viewers: pull the note taker's latest draft from the database. */
    public function syncDraft(): void
    {
        if ($this->holdsLock) {
            return;
        }

        $this->hydrateDraft($this->meeting);
    }

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
        unset($this->meeting);

        $this->validate(['actionItems.*.title' => 'nullable|string|max:255']);

        $minutes = $this->persistDraft();
        $minutes->update([
            'is_published' => true,
            'published_at' => now(),
            'published_by' => auth()->id(),
        ]);

        $this->toast(type: 'success', title: __('Minutes published'));
        unset($this->meeting);
    }

    public function removeActionItem(int $i): void
    {
        array_splice($this->actionItems, $i, 1);
        $this->persistDraft();
    }

    public function removeAnnouncement(int $i): void
    {
        array_splice($this->announcements, $i, 1);
        $this->persistDraft();
    }

    public function removeDecision(int $i): void
    {
        array_splice($this->decisions, $i, 1);
        $this->persistDraft();
    }

    public function render(): View
    {
        return $this->view();
    }

    public function sendMinutes(bool $toAll = false): void
    {
        abort_unless($this->canManage, 403);
        $meeting = $this->meeting;

        if (! $meeting->minutes?->is_published) {
            $this->toast(type: 'error', title: __('Publish the minutes first'));

            return;
        }

        $recipients = $toAll
            ? User::active()->get()
            : User::role([Role::ADMINISTRATOR->value, Role::COMMITTEE->value])->get();

        Notification::send($recipients, new MeetingMinutesNotification($meeting));

        $field = $toAll ? 'sent_to_all_at' : 'sent_to_committee_at';
        $meeting->minutes->update([$field => now()]);

        $this->toast(type: 'success', title: __('Minutes sent to :n members', ['n' => $recipients->count()]));
        unset($this->meeting);
    }

    /** Explicit takeover: wrestle the pen from the current holder. */
    public function takeOver(): void
    {
        abort_unless($this->canManage, 403);

        $this->meeting->acquireMinutesLock(auth()->user(), force: true);
        unset($this->meeting);

        $this->toast(type: 'success', title: __('You are taking the notes now'));
    }

    /** Tick or untick an agenda item as discussed during the meeting. */
    public function toggleDiscussed(int $itemId): void
    {
        abort_unless($this->canManage, 403);

        // Ticking an item off is note-taking too: claim the pen (I4).
        if (! $this->claimPen()) {
            return;
        }

        $item = $this->meeting->agendaItems()->findOrFail($itemId);
        $item->update(['discussed_at' => $item->discussed_at ? null : now()]);
        unset($this->meeting);
    }

    /** Autosave: any edit to the draft fields persists immediately (wire:model.blur). */
    public function updated(string $name): void
    {
        if (preg_match('/^(announcements|decisions|notes|actionItems)/', $name)) {
            $this->persistDraft();
        }
    }

    #[Computed]
    public function usersForAssignment(): array
    {
        return User::role([Role::ADMINISTRATOR->value, Role::COMMITTEE->value])
            ->orderBy('last_name')->get()
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->full_name])
            ->toArray();
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->add(__('Meeting Details'), route('admin.meetings.show', $this->meetingId))
            ->current(__('Minutes'));
    }

    private function hydrateDraft(Meeting $meeting): void
    {
        $minutes = $meeting->minutes;
        $this->announcements = $minutes->announcements ?? [];
        $this->decisions = $minutes->decisions ?? [];
        $this->notes = $minutes->notes ?? '';

        $this->actionItems = $meeting->actionItems
            ->map(fn ($item) => [
                'title' => $item->title,
                'description' => $item->description ?? '',
                'assigned_to_id' => (string) ($item->assigned_to_id ?? ''),
                'due_date' => $item->due_date?->format('Y-m-d') ?? '',
                'is_completed' => $item->is_completed,
            ])
            ->toArray();
    }

    /**
     * Claim the note-taking pen for the current user on a write. Succeeds when the
     * pen is free/stale or already theirs; fails (with a warning) only when another
     * committee member holds it live. This is where the pen is taken — never on
     * mount (I4) — so simply opening the page leaves the note taker undisturbed.
     */
    private function claimPen(): bool
    {
        if ($this->meeting->acquireMinutesLock(auth()->user())) {
            unset($this->meeting);

            return true;
        }

        $this->toast(type: 'warning', title: __(':name is taking notes', ['name' => $this->lockHolder?->full_name ?? '']));

        return false;
    }

    private function persistDraft(): MeetingMinutes
    {
        // The first edit claims the pen; another live holder keeps everyone else
        // read-only until an explicit takeover.
        if (! $this->claimPen()) {
            return $this->meeting->minutes ?? new MeetingMinutes(['meeting_id' => $this->meetingId]);
        }

        $meeting = $this->meeting;
        $minutes = $meeting->minutes ?? new MeetingMinutes(['meeting_id' => $this->meetingId]);
        $minutes->announcements = array_values(array_filter($this->announcements, fn ($v) => filled($v)));
        $minutes->decisions = array_values(array_filter($this->decisions, fn ($v) => filled($v)));
        $minutes->notes = filled($this->notes) ? $this->notes : null;
        $minutes->save();

        $meeting->actionItems()->delete();
        foreach ($this->actionItems as $item) {
            if (filled($item['title'])) {
                $meeting->actionItems()->create([
                    'title' => $item['title'],
                    'description' => filled($item['description']) ? $item['description'] : null,
                    'assigned_to_id' => filled($item['assigned_to_id']) ? (int) $item['assigned_to_id'] : null,
                    'due_date' => filled($item['due_date']) ? $item['due_date'] : null,
                    'is_completed' => $item['is_completed'],
                ]);
            }
        }

        $this->savedAt = now()->format('H:i:s');
        unset($this->meeting);

        return $minutes;
    }
};
