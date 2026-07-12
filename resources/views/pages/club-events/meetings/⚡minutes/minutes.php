<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
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

        return $user instanceof User && ($user->is_admin || $user->is_committee_member);
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

        // Take the pen when it is free (or the previous holder went stale).
        $meeting->acquireMinutesLock(auth()->user());

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

        if (! $this->holdsLock) {
            $this->toast(type: 'error', title: __('Take over the notes before publishing'));

            return;
        }

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
            : User::where(fn ($q) => $q->where('is_admin', true)->orWhere('is_committee_member', true))->get();

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

        if (! $this->holdsLock) {
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
        return User::where(fn ($q) => $q->where('is_admin', true)->orWhere('is_committee_member', true))
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

    private function persistDraft(): MeetingMinutes
    {
        $meeting = $this->meeting;

        // Only the note taker writes; everyone else is read-only until takeover.
        if (! $this->holdsLock) {
            $this->toast(type: 'warning', title: __(':name is taking notes', ['name' => $this->lockHolder?->full_name ?? '']));

            return $meeting->minutes ?? new MeetingMinutes(['meeting_id' => $this->meetingId]);
        }

        $meeting->acquireMinutesLock(auth()->user());

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
