<?php

declare(strict_types=1);

use App\Domains\Shared\Enums\MeetingDateVoteEnum;
use App\Domains\Shared\Enums\MeetingStatusEnum;
use App\Domains\Shared\Enums\MeetingTypeEnum;
use App\Domains\Shared\Enums\MeetingUserStatusEnum;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Meeting\Meeting;
use App\Models\ClubEvents\Meeting\MeetingDateProposal;
use App\Models\ClubEvents\Meeting\MeetingDateVote;
use App\Models\ClubEvents\Meeting\MeetingMinutes;
use App\Jobs\SendMeetingInvitationsJob;
use App\Domains\Meetings\Notifications\MeetingCancelledNotification;
use App\Domains\Meetings\Notifications\MeetingDatePollNotification;
use App\Domains\Meetings\Notifications\MeetingMinutesNotification;
use App\Domains\Meetings\Notifications\MeetingPostponedNotification;
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
    use Toast, HasBreadcrumbs;

    #[Locked]
    public int $meetingId;

    public string $activeTab = 'overview';

    // Modals
    public bool $showCancelModal    = false;
    public bool $showPostponeModal  = false;
    public bool $showInviteModal    = false;
    public bool $showPollModal      = false;

    // Cancel / postpone
    public string $cancellationNote = '';
    public string $postponedNote    = '';
    public string $postponedTo      = '';

    // Minutes editor
    /** @var array<int, string> */
    public array $minutesAnnouncements = [];
    /** @var array<int, string> */
    public array $minutesDecisions     = [];
    public string $minutesNotes        = '';

    // Action items editor
    /** @var array<int, array{title: string, description: string, assigned_to_id: string, due_date: string, is_completed: bool}> */
    public array $actionItems = [];

    public function mount(Meeting $meeting): void
    {
        $this->meetingId = $meeting->id;
        $this->loadMinutes();
        $this->loadActionItems();
    }

    private function loadMinutes(): void
    {
        $minutes = $this->meeting->minutes;
        if ($minutes) {
            $this->minutesAnnouncements = $minutes->announcements ?? [];
            $this->minutesDecisions     = $minutes->decisions ?? [];
            $this->minutesNotes         = $minutes->notes ?? '';
        }
    }

    private function loadActionItems(): void
    {
        $this->actionItems = $this->meeting->actionItems
            ->map(fn ($item) => [
                'id'             => $item->id,
                'title'          => $item->title,
                'description'    => $item->description ?? '',
                'assigned_to_id' => (string) ($item->assigned_to_id ?? ''),
                'due_date'       => $item->due_date?->format('Y-m-d') ?? '',
                'is_completed'   => $item->is_completed,
            ])
            ->toArray();
    }

    #[Computed]
    public function meeting(): Meeting
    {
        return Meeting::with([
            'agendaItems',
            'dateProposals.votes.user',
            'users',
            'minutes',
            'actionItems.assignedTo',
            'creator',
        ])->findOrFail($this->meetingId);
    }

    #[Computed]
    public function canManage(): bool
    {
        $user = auth()->user();
        return $user instanceof User && ($user->is_admin || $user->is_committee_member);
    }

    #[Computed]
    public function committeeUsers(): \Illuminate\Database\Eloquent\Collection
    {
        return User::where(fn ($q) => $q->where('is_admin', true)->orWhere('is_committee_member', true))
            ->orderBy('last_name')->get();
    }

    #[Computed]
    public function allMembers(): \Illuminate\Database\Eloquent\Collection
    {
        return User::where('is_active', true)->orderBy('last_name')->get();
    }

    #[Computed]
    public function usersForAssignment(): array
    {
        return $this->committeeUsers
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->full_name])
            ->toArray();
    }

    // ── Date poll ─────────────────────────────────────────────────────
    public function sendDatePoll(): void
    {
        abort_unless($this->canManage, 403);
        $meeting = $this->meeting;

        if ($meeting->dateProposals->isEmpty()) {
            $this->toast(type: 'error', title: __('Add date proposals first'));
            return;
        }

        $recipients = $this->committeeUsers;

        Notification::send($recipients, new MeetingDatePollNotification($meeting));

        $this->toast(type: 'success', title: __('Poll sent to :n committee members', ['n' => $recipients->count()]));
        unset($this->meeting);
    }

    public function selectDateProposal(int $proposalId): void
    {
        abort_unless($this->canManage, 403);
        $meeting = $this->meeting;

        $meeting->dateProposals()->update(['is_selected' => false]);
        $proposal = MeetingDateProposal::findOrFail($proposalId);
        $proposal->update(['is_selected' => true]);

        $meeting->update([
            'scheduled_at' => $proposal->proposed_at,
            'ends_at'      => $proposal->proposed_at->copy()->addHours(2),
            'status'       => MeetingStatusEnum::CONFIRMED,
        ]);

        $this->toast(type: 'success', title: __('Date confirmed: :date', [
            'date' => $proposal->proposed_at->translatedFormat('d M Y à H\hi'),
        ]));
        unset($this->meeting);
    }

    // ── Invitations ───────────────────────────────────────────────────
    public function sendInvitations(): void
    {
        abort_unless($this->canManage, 403);

        if ($this->meeting->status !== MeetingStatusEnum::CONFIRMED) {
            $this->toast(type: 'error', title: __('Confirm the date before sending invitations'));
            return;
        }

        dispatch(new SendMeetingInvitationsJob($this->meetingId));

        $this->toast(type: 'success', title: __('Invitations queued — members will receive them shortly'));
        unset($this->meeting);
    }

    // ── Attendance ────────────────────────────────────────────────────
    public function markAttended(int $userId): void
    {
        abort_unless($this->canManage, 403);
        $this->meeting->users()->updateExistingPivot($userId, [
            'status'      => MeetingUserStatusEnum::ATTENDED->value,
            'response_at' => now(),
        ]);
        unset($this->meeting);
    }

    public function markAbsent(int $userId): void
    {
        abort_unless($this->canManage, 403);
        $this->meeting->users()->updateExistingPivot($userId, [
            'status'      => MeetingUserStatusEnum::ABSENT->value,
            'response_at' => now(),
        ]);
        unset($this->meeting);
    }

    public function markCompleted(): void
    {
        abort_unless($this->canManage, 403);
        $this->meeting->update(['status' => MeetingStatusEnum::COMPLETED]);
        $this->toast(type: 'success', title: __('Meeting marked as completed'));
        unset($this->meeting);
    }

    // ── Cancel / postpone ─────────────────────────────────────────────

    /** Resolve recipients: invited users if any, otherwise the target audience. */
    private function resolveNotificationRecipients(Meeting $meeting): \Illuminate\Database\Eloquent\Collection
    {
        $invited = $meeting->users()->get();

        if ($invited->isNotEmpty()) {
            return $invited;
        }

        // Nobody invited yet — fall back to target audience
        return $meeting->type === MeetingTypeEnum::GENERAL_ASSEMBLY
            ? User::where('is_active', true)->get()
            : User::where(fn ($q) => $q->where('is_admin', true)->orWhere('is_committee_member', true))->get();
    }

    public function cancelMeeting(): void
    {
        abort_unless($this->canManage, 403);
        $this->validate(['cancellationNote' => 'nullable|string|max:1000']);

        $meeting = Meeting::findOrFail($this->meetingId);
        $meeting->update([
            'status'            => MeetingStatusEnum::CANCELLED,
            'cancellation_note' => $this->cancellationNote ?: null,
        ]);

        $recipients = $this->resolveNotificationRecipients($meeting);
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new MeetingCancelledNotification($meeting, $this->cancellationNote));
        }

        $this->showCancelModal = false;
        $this->toast(type: 'warning', title: __('Meeting cancelled'));
        unset($this->meeting);
    }

    public function postponeMeeting(): void
    {
        abort_unless($this->canManage, 403);
        $this->validate([
            'postponedNote' => 'nullable|string|max:1000',
            'postponedTo'   => 'nullable|date',
        ]);

        $meeting = Meeting::findOrFail($this->meetingId);
        $meeting->update([
            'status'         => MeetingStatusEnum::POSTPONED,
            'postponed_note' => $this->postponedNote ?: null,
            'postponed_to'   => filled($this->postponedTo) ? $this->postponedTo : null,
        ]);

        $recipients = $this->resolveNotificationRecipients($meeting);
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new MeetingPostponedNotification($meeting, $this->postponedNote));
        }

        $this->showPostponeModal = false;
        $this->toast(type: 'info', title: __('Meeting postponed'));
        unset($this->meeting);
    }

    // ── Minutes ───────────────────────────────────────────────────────
    public function addAnnouncement(): void  { $this->minutesAnnouncements[] = ''; }
    public function removeAnnouncement(int $i): void { array_splice($this->minutesAnnouncements, $i, 1); }
    public function addDecision(): void      { $this->minutesDecisions[] = ''; }
    public function removeDecision(int $i): void { array_splice($this->minutesDecisions, $i, 1); }

    public function saveMinutes(): void
    {
        abort_unless($this->canManage, 403);

        $minutes = $this->meeting->minutes ?? new MeetingMinutes(['meeting_id' => $this->meetingId]);
        $minutes->announcements = array_values(array_filter($this->minutesAnnouncements, fn ($v) => filled($v)));
        $minutes->decisions     = array_values(array_filter($this->minutesDecisions, fn ($v) => filled($v)));
        $minutes->notes         = filled($this->minutesNotes) ? $this->minutesNotes : null;
        $minutes->save();

        $this->toast(type: 'success', title: __('Minutes saved'));
        unset($this->meeting);
    }

    public function publishMinutes(): void
    {
        abort_unless($this->canManage, 403);
        $this->saveMinutes();

        $minutes = $this->meeting->minutes;
        $minutes->update([
            'is_published' => true,
            'published_at' => now(),
            'published_by' => auth()->id(),
        ]);

        $this->toast(type: 'success', title: __('Minutes published'));
        unset($this->meeting);
    }

    public function sendMinutes(bool $toAll = false): void
    {
        abort_unless($this->canManage, 403);
        $meeting = $this->meeting;
        $minutes = $meeting->minutes;

        if (! $minutes?->is_published) {
            $this->toast(type: 'error', title: __('Publish the minutes first'));
            return;
        }

        $recipients = $toAll
            ? User::where('is_active', true)->get()
            : $this->committeeUsers;

        Notification::send($recipients, new MeetingMinutesNotification($meeting));

        $field = $toAll ? 'sent_to_all_at' : 'sent_to_committee_at';
        $minutes->update([$field => now()]);

        $this->toast(type: 'success', title: __('Minutes sent to :n members', ['n' => $recipients->count()]));
        unset($this->meeting);
    }

    // ── Action items ──────────────────────────────────────────────────
    public function addActionItem(): void
    {
        $this->actionItems[] = [
            'title' => '', 'description' => '',
            'assigned_to_id' => '', 'due_date' => '', 'is_completed' => false,
        ];
    }

    public function removeActionItem(int $i): void
    {
        array_splice($this->actionItems, $i, 1);
    }

    public function saveActionItems(): void
    {
        abort_unless($this->canManage, 403);
        $this->validate([
            'actionItems.*.title' => 'required|string|max:255',
        ]);

        $meeting = $this->meeting;
        $meeting->actionItems()->delete();

        foreach ($this->actionItems as $item) {
            if (filled($item['title'])) {
                $meeting->actionItems()->create([
                    'title'          => $item['title'],
                    'description'    => filled($item['description']) ? $item['description'] : null,
                    'assigned_to_id' => filled($item['assigned_to_id']) ? (int) $item['assigned_to_id'] : null,
                    'due_date'       => filled($item['due_date']) ? $item['due_date'] : null,
                    'is_completed'   => $item['is_completed'],
                ]);
            }
        }

        $this->toast(type: 'success', title: __('Action items saved'));
        unset($this->meeting);
        $this->loadActionItems();
    }


    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__("Meeting Details"));
    }

        public function render(): View
    {
        return $this->view();
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
        ];
    }
};
