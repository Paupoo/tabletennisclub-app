<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingDateProposal;
use App\Domains\Meetings\Models\MeetingMinutes;
use App\Domains\Meetings\Models\MeetingUser;
use App\Domains\Meetings\Notifications\MeetingCancelledNotification;
use App\Domains\Meetings\Notifications\MeetingDatePollNotification;
use App\Domains\Meetings\Notifications\MeetingInvitationNotification;
use App\Domains\Meetings\Notifications\MeetingMinutesNotification;
use App\Domains\Meetings\Notifications\MeetingPostponedNotification;
use App\Domains\Shared\Enums\MeetingFormatEnum;
use App\Domains\Shared\Enums\MeetingStatusEnum;
use App\Domains\Shared\Enums\MeetingTypeEnum;
use App\Domains\Shared\Enums\MeetingUserStatusEnum;
use App\Jobs\SendMeetingInvitationsJob;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs, Toast;

    // Action items editor
    /** @var array<int, array{title: string, description: string, assigned_to_id: string, due_date: string, is_completed: bool}> */
    public array $actionItems = [];

    // Card being edited in place: 'title' | 'details' | 'agenda' | 'meal' | 'quorum' | null
    public ?string $editing = null;

    // ── Card drafts ───────────────────────────────────────────────────
    /** @var array<int, array{id: int|null, title: string, description: string}> */
    public array $agendaDraft = [];

    public string $detailsDescription = '';

    public ?string $detailsEndsAt = null;

    public string $detailsFormat = 'physical';

    public string $detailsLocation = '';

    public string $detailsMeetingLink = '';

    public ?string $detailsRsvpDeadline = null;

    public ?string $detailsScheduledAt = null;

    public bool $mealHasDraft = false;

    public string $mealDescriptionDraft = '';

    public string $mealPriceDraft = '';

    public ?string $newProposalAt = null;

    public ?int $quorumDraft = null;

    public string $titleDraft = '';

    public string $typeDraft = 'committee';

    // Cancel / postpone
    public string $cancellationNote = '';

    #[Locked]
    public int $meetingId;

    // Minutes editor
    /** @var array<int, string> */
    public array $minutesAnnouncements = [];

    /** @var array<int, string> */
    public array $minutesDecisions = [];

    public string $minutesNotes = '';

    public string $postponedNote = '';

    public string $postponedTo = '';

    // Modals
    public bool $showCancelModal = false;

    public bool $showTitleModal = false;

    public bool $showDeleteModal = false;

    public bool $showPostponeModal = false;

    // ── Action items ──────────────────────────────────────────────────
    public function addActionItem(): void
    {
        $this->actionItems[] = [
            'title' => '', 'description' => '',
            'assigned_to_id' => '', 'due_date' => '', 'is_completed' => false,
        ];
    }

    // ── Minutes ───────────────────────────────────────────────────────
    public function addAnnouncement(): void
    {
        $this->minutesAnnouncements[] = '';
    }

    public function addDecision(): void
    {
        $this->minutesDecisions[] = '';
    }

    #[Computed]
    public function allMembers(): Illuminate\Database\Eloquent\Collection
    {
        return User::active()->orderBy('last_name')->get();
    }

    public function archiveMeeting(): void
    {
        abort_unless($this->canManage, 403);

        $meeting = Meeting::findOrFail($this->meetingId);

        if (! $meeting->canBeArchived()) {
            $this->toast(type: 'error', title: __('This meeting cannot be archived yet'));

            return;
        }

        $meeting->archive();

        $this->toast(type: 'success', title: __('Meeting archived'));
        $this->redirectRoute('admin.meetings.index', navigate: true);
    }

    public function cancelMeeting(): void
    {
        abort_unless($this->canManage, 403);
        $this->validate(['cancellationNote' => 'nullable|string|max:1000']);

        $meeting = Meeting::findOrFail($this->meetingId);
        $meeting->update([
            'status' => MeetingStatusEnum::CANCELLED,
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

    #[Computed]
    public function canManage(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ($user->is_admin || $user->is_committee_member);
    }

    #[Computed]
    public function committeeUsers(): Illuminate\Database\Eloquent\Collection
    {
        return User::where(fn ($q) => $q->where('is_admin', true)->orWhere('is_committee_member', true))
            ->orderBy('last_name')->get();
    }

    public function addProposal(): void
    {
        abort_unless($this->canManage, 403);
        $this->validate(['newProposalAt' => 'required|date']);

        $this->meeting->dateProposals()->create(['proposed_at' => $this->newProposalAt]);

        $this->newProposalAt = null;
        $this->toast(type: 'success', title: __('Date option added'));
        unset($this->meeting);
    }

    public function removeProposal(int $proposalId): void
    {
        abort_unless($this->canManage, 403);

        $proposal = $this->meeting->dateProposals()->findOrFail($proposalId);

        if ($proposal->votes()->exists()) {
            $this->toast(type: 'error', title: __('This date already has votes — it cannot be removed'));

            return;
        }

        $proposal->delete();
        unset($this->meeting);
    }

    public function addAgendaDraftItem(): void
    {
        $this->agendaDraft[] = ['id' => null, 'title' => '', 'description' => ''];
    }

    public function cancelEditing(): void
    {
        $this->editing = null;
    }

    // ── Card editing ──────────────────────────────────────────────────
    public function editAgenda(): void
    {
        abort_unless($this->canManage, 403);

        $this->agendaDraft = $this->meeting->agendaItems
            ->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description ?? '',
            ])
            ->values()
            ->toArray();

        if ($this->agendaDraft === []) {
            $this->addAgendaDraftItem();
        }

        $this->editing = 'agenda';
    }

    public function editDetails(): void
    {
        abort_unless($this->canManage, 403);
        $meeting = $this->meeting;

        $this->detailsFormat = $meeting->format->value;
        $this->detailsScheduledAt = $meeting->scheduled_at?->format('Y-m-d\TH:i');
        $this->detailsEndsAt = $meeting->ends_at?->format('Y-m-d\TH:i');
        $this->detailsLocation = $meeting->location ?? '';
        $this->detailsMeetingLink = $meeting->meeting_link ?? '';
        $this->detailsRsvpDeadline = $meeting->rsvp_deadline?->format('Y-m-d');
        $this->detailsDescription = $meeting->description ?? '';

        $this->editing = 'details';
    }

    public function editMeal(): void
    {
        abort_unless($this->canManage, 403);
        $meeting = $this->meeting;

        $this->mealHasDraft = $meeting->has_meal;
        $this->mealDescriptionDraft = $meeting->meal_description ?? '';
        $this->mealPriceDraft = $meeting->meal_price !== null ? (string) $meeting->meal_price : '';

        $this->editing = 'meal';
    }

    public function editQuorum(): void
    {
        abort_unless($this->canManage, 403);
        $this->quorumDraft = $this->meeting->quorum;
        $this->editing = 'quorum';
    }

    public function editTitle(): void
    {
        abort_unless($this->canManage, 403);
        $this->titleDraft = $this->meeting->title;
        $this->typeDraft = $this->meeting->type->value;
        $this->showTitleModal = true;
    }

    public function deleteMeeting(): void
    {
        abort_unless($this->canManage, 403);

        $meeting = Meeting::findOrFail($this->meetingId);

        if (! $meeting->canBeDeleted()) {
            $this->toast(type: 'error', title: __('This meeting cannot be deleted — invitations have already been sent'));

            return;
        }

        $meeting->delete();

        $this->toast(type: 'warning', title: __('Meeting deleted'));
        $this->redirectRoute('admin.meetings.index', navigate: true);
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

    // ── Attendance ────────────────────────────────────────────────────
    public function markAttended(int $userId): void
    {
        abort_unless($this->canManage, 403);
        $this->meeting->users()->updateExistingPivot($userId, [
            'status' => MeetingUserStatusEnum::ATTENDED->value,
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

    /**
     * Meal payments for this meeting, keyed by registration (pivot) id —
     * one query, used to render the per-attendee meal badge.
     *
     * @return Collection<int, Payment>
     */
    #[Computed]
    public function mealPaymentsByRegistration(): Collection
    {
        if (! $this->meeting->has_meal) {
            return collect();
        }

        return Payment::where('payable_type', (new MeetingUser)->getMorphClass())
            ->whereIn('payable_id', $this->meeting->users->pluck('registration.id'))
            ->get()
            ->keyBy('payable_id');
    }

    #[Computed]
    public function meeting(): Meeting
    {
        return Meeting::with([
            'agendaItems',
            'dateProposals.votes.user',
            'eventPost',
            'users',
            'minutes',
            'actionItems.assignedTo',
            'creator',
        ])->findOrFail($this->meetingId);
    }

    public function mount(Meeting $meeting): void
    {
        $this->meetingId = $meeting->id;
        $this->loadMinutes();
        $this->loadActionItems();
    }

    /** True once at least one invitation left the building. */
    #[Computed]
    public function invitationsSent(): bool
    {
        return $this->meeting->users->contains(fn (User $u) => $u->registration->invitation_sent_at !== null);
    }

    /** Invitees who have not answered yet. */
    #[Computed]
    public function pendingInviteesCount(): int
    {
        return $this->meeting->users
            ->filter(fn (User $u) => $u->registration->status === MeetingUserStatusEnum::INVITED)
            ->count();
    }

    /**
     * What still has to be filled in before invitations may be sent.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function sendChecklist(): array
    {
        $meeting = $this->meeting;
        $missing = [];

        if ($meeting->agendaItems->isEmpty()) {
            $missing[] = __('Add at least one agenda item');
        }

        if ($meeting->format === MeetingFormatEnum::PHYSICAL && blank($meeting->location)) {
            $missing[] = __('Set the location');
        }

        if ($meeting->format === MeetingFormatEnum::VIRTUAL && blank($meeting->meeting_link)) {
            $missing[] = __('Set the meeting link');
        }

        return $missing;
    }

    /**
     * The single next action suggested by the meeting lifecycle, or null when nothing is pending.
     *
     * @return array{title: string, description: string|null, action: string|null, label: string|null}|null
     */
    #[Computed]
    public function nextStep(): ?array
    {
        $meeting = $this->meeting;

        if ($meeting->isArchived() || $meeting->status === MeetingStatusEnum::CANCELLED) {
            return null;
        }

        if ($meeting->status === MeetingStatusEnum::POSTPONED) {
            return [
                'title' => __('Meeting postponed — pick a new date'),
                'description' => __('Set the new date from the practical info card.'),
                'action' => null,
                'label' => null,
            ];
        }

        if ($meeting->status === MeetingStatusEnum::PLANNING) {
            if ($meeting->dateProposals->isEmpty()) {
                return [
                    'title' => __('No date yet'),
                    'description' => __('Set a date from the practical info card.'),
                    'action' => null,
                    'label' => null,
                ];
            }

            $votes = $meeting->dateProposals->sum(fn (MeetingDateProposal $p) => $p->votes->count());

            if ($votes === 0) {
                return [
                    'title' => __('Send the date poll to the committee'),
                    'description' => __('Members will vote on their availability for each proposed date.'),
                    'action' => 'sendDatePoll',
                    'label' => __('Send the poll'),
                ];
            }

            return [
                'title' => __(':n votes received — pick the final date', ['n' => $votes]),
                'description' => __('Select the winning date below to confirm the meeting.'),
                'action' => null,
                'label' => null,
            ];
        }

        if ($meeting->status === MeetingStatusEnum::CONFIRMED) {
            if ($meeting->scheduled_at?->isPast()) {
                return [
                    'title' => __('This meeting took place'),
                    'description' => __('Record attendance below, then mark the meeting completed.'),
                    'action' => 'markCompleted',
                    'label' => __('Mark completed'),
                ];
            }

            if (! $this->invitationsSent) {
                if ($this->sendChecklist !== []) {
                    return [
                        'title' => __('Complete the meeting before inviting'),
                        'description' => implode(' · ', $this->sendChecklist),
                        'action' => null,
                        'label' => null,
                    ];
                }

                return [
                    'title' => __('Date confirmed — invite the members'),
                    'description' => __('Official invitations with a calendar file (.ics) will be sent.'),
                    'action' => 'sendInvitations',
                    'label' => __('Send invitations'),
                ];
            }

            if ($this->pendingInviteesCount > 0) {
                return [
                    'title' => trans_choice(':n member has not answered yet|:n members have not answered yet', $this->pendingInviteesCount, ['n' => $this->pendingInviteesCount]),
                    'description' => __('A reminder resends the invitation, at most once every 48 hours.'),
                    'action' => 'remindPendingInvitees',
                    'label' => __('Send a reminder'),
                ];
            }

            return [
                'title' => __('Everyone answered — you are all set'),
                'description' => __(':n confirmed attendees', ['n' => $meeting->confirmedCount()]),
                'action' => null,
                'label' => null,
            ];
        }

        if ($meeting->status === MeetingStatusEnum::COMPLETED && ! $meeting->minutes?->is_published) {
            return [
                'title' => __('Write and publish the minutes'),
                'description' => __('Attendees are waiting for the meeting report.'),
                'action' => null,
                'label' => null,
            ];
        }

        return null;
    }

    public function postponeMeeting(): void
    {
        abort_unless($this->canManage, 403);
        $this->validate([
            'postponedNote' => 'nullable|string|max:1000',
            'postponedTo' => 'nullable|date',
        ]);

        $meeting = Meeting::findOrFail($this->meetingId);
        $meeting->update([
            'status' => MeetingStatusEnum::POSTPONED,
            'postponed_note' => $this->postponedNote ?: null,
            'postponed_to' => filled($this->postponedTo) ? $this->postponedTo : null,
        ]);

        $recipients = $this->resolveNotificationRecipients($meeting);
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new MeetingPostponedNotification($meeting, $this->postponedNote));
        }

        $this->showPostponeModal = false;
        $this->toast(type: 'info', title: __('Meeting postponed'));
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

    /** Resend the invitation to invitees who never responded, at most once every 48 hours. */
    public function remindPendingInvitees(): void
    {
        abort_unless($this->canManage, 403);
        $meeting = $this->meeting;

        $recipients = $meeting->users()
            ->wherePivot('status', MeetingUserStatusEnum::INVITED->value)
            ->wherePivotNotNull('invitation_sent_at')
            ->wherePivot('invitation_sent_at', '<=', now()->subHours(48))
            ->get();

        if ($recipients->isEmpty()) {
            $this->toast(type: 'info', title: __('Nobody to remind — everyone answered or was reminded less than 48h ago'));

            return;
        }

        foreach ($recipients as $recipient) {
            $meeting->users()->updateExistingPivot($recipient->id, ['invitation_sent_at' => now()]);
        }

        Notification::send($recipients, new MeetingInvitationNotification($meeting));

        $this->toast(type: 'success', title: __(':n reminders sent', ['n' => $recipients->count()]));
        unset($this->meeting);
    }

    public function removeAgendaDraftItem(int $i): void
    {
        array_splice($this->agendaDraft, $i, 1);
    }

    /** wire:sort handler on the read view — persists the new agenda order immediately. */
    public function reorderAgenda(int $id, int $position): void
    {
        abort_unless($this->canManage, 403);

        $ids = $this->meeting->agendaItems->pluck('id')->all();
        $from = array_search($id, $ids, true);

        if ($from === false) {
            return;
        }

        array_splice($ids, $from, 1);
        array_splice($ids, $position, 0, [$id]);

        foreach ($ids as $order => $itemId) {
            $this->meeting->agendaItems()->where('id', $itemId)->update(['sort_order' => $order]);
        }

        unset($this->meeting);
    }

    public function saveAgenda(): void
    {
        abort_unless($this->canManage, 403);
        $this->validate([
            'agendaDraft.*.title' => 'required|string|max:255',
            'agendaDraft.*.description' => 'nullable|string',
        ]);

        $meeting = $this->meeting;
        $keptIds = [];

        foreach (array_values($this->agendaDraft) as $order => $item) {
            if (blank($item['title'])) {
                continue;
            }

            $attributes = [
                'sort_order' => $order,
                'title' => $item['title'],
                'description' => filled($item['description']) ? $item['description'] : null,
            ];

            $existing = $item['id'] ? $meeting->agendaItems()->find($item['id']) : null;

            if ($existing) {
                $existing->update($attributes);
                $keptIds[] = $existing->id;
            } else {
                $keptIds[] = $meeting->agendaItems()->create($attributes)->id;
            }
        }

        $meeting->agendaItems()->whereNotIn('id', $keptIds)->delete();

        $this->editing = null;
        $this->toast(type: 'success', title: __('Agenda updated'));
        unset($this->meeting);
    }

    public function saveDetails(): void
    {
        abort_unless($this->canManage, 403);
        $this->validate([
            'detailsFormat' => 'required|string|in:' . implode(',', array_column(MeetingFormatEnum::cases(), 'value')),
            'detailsScheduledAt' => 'nullable|date',
            'detailsEndsAt' => 'nullable|date|after_or_equal:detailsScheduledAt',
            'detailsLocation' => $this->detailsFormat === 'physical' ? 'nullable|string|max:500' : 'nullable|string|max:500',
            'detailsMeetingLink' => 'nullable|url|max:500',
            'detailsRsvpDeadline' => 'nullable|date',
            'detailsDescription' => 'nullable|string',
        ]);

        $meeting = $this->meeting;
        $isPhysical = $this->detailsFormat === 'physical';
        $scheduledAt = filled($this->detailsScheduledAt) ? Carbon::parse($this->detailsScheduledAt) : null;

        $data = [
            'format' => $this->detailsFormat,
            'scheduled_at' => $scheduledAt,
            'ends_at' => $scheduledAt
                ? (filled($this->detailsEndsAt) ? $this->detailsEndsAt : $scheduledAt->copy()->addHours(2))
                : null,
            'location' => $isPhysical && filled($this->detailsLocation) ? $this->detailsLocation : null,
            'meeting_link' => ! $isPhysical && filled($this->detailsMeetingLink) ? $this->detailsMeetingLink : null,
            'rsvp_deadline' => filled($this->detailsRsvpDeadline) ? $this->detailsRsvpDeadline : null,
            'description' => filled($this->detailsDescription) ? $this->detailsDescription : null,
        ];

        if ($scheduledAt && in_array($meeting->status, [MeetingStatusEnum::PLANNING, MeetingStatusEnum::POSTPONED], true)) {
            $data['status'] = MeetingStatusEnum::CONFIRMED;
        }

        $meeting->update($data);

        $this->editing = null;
        $this->toast(type: 'success', title: __('Meeting updated'));
        unset($this->meeting);
    }

    public function saveMeal(): void
    {
        abort_unless($this->canManage, 403);

        if ($this->mealHasDraft) {
            $this->validate([
                'mealDescriptionDraft' => 'required|string|max:255',
                'mealPriceDraft' => 'nullable|numeric|min:0',
            ]);
        }

        $this->meeting->update([
            'has_meal' => $this->mealHasDraft,
            'meal_description' => $this->mealHasDraft && filled($this->mealDescriptionDraft) ? $this->mealDescriptionDraft : null,
            'meal_price_cents' => $this->mealHasDraft && filled($this->mealPriceDraft)
                ? (int) round((float) $this->mealPriceDraft * 100)
                : null,
        ]);

        $this->editing = null;
        $this->toast(type: 'success', title: __('Meeting updated'));
        unset($this->meeting);
    }

    public function saveQuorum(): void
    {
        abort_unless($this->canManage, 403);
        $this->validate(['quorumDraft' => 'nullable|integer|min:1']);

        $this->meeting->update(['quorum' => $this->quorumDraft]);

        $this->editing = null;
        $this->toast(type: 'success', title: __('Meeting updated'));
        unset($this->meeting);
    }

    public function saveTitle(): void
    {
        abort_unless($this->canManage, 403);
        $this->validate([
            'titleDraft' => 'required|string|max:255',
            'typeDraft' => 'required|string|in:' . implode(',', array_column(MeetingTypeEnum::cases(), 'value')),
        ]);

        $data = ['title' => $this->titleDraft];

        // The type drives the invitee audience — freeze it once invitations left.
        if (! $this->invitationsSent) {
            $data['type'] = $this->typeDraft;
        }

        $this->meeting->update($data);

        $this->showTitleModal = false;
        $this->toast(type: 'success', title: __('Meeting updated'));
        unset($this->meeting);
    }

    public function removeActionItem(int $i): void
    {
        array_splice($this->actionItems, $i, 1);
    }

    public function removeAnnouncement(int $i): void
    {
        array_splice($this->minutesAnnouncements, $i, 1);
    }

    public function removeDecision(int $i): void
    {
        array_splice($this->minutesDecisions, $i, 1);
    }

    public function render(): View
    {
        return $this->view();
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
                    'title' => $item['title'],
                    'description' => filled($item['description']) ? $item['description'] : null,
                    'assigned_to_id' => filled($item['assigned_to_id']) ? (int) $item['assigned_to_id'] : null,
                    'due_date' => filled($item['due_date']) ? $item['due_date'] : null,
                    'is_completed' => $item['is_completed'],
                ]);
            }
        }

        $this->toast(type: 'success', title: __('Action items saved'));
        unset($this->meeting);
        $this->loadActionItems();
    }

    public function saveMinutes(): void
    {
        abort_unless($this->canManage, 403);

        $minutes = $this->meeting->minutes ?? new MeetingMinutes(['meeting_id' => $this->meetingId]);
        $minutes->announcements = array_values(array_filter($this->minutesAnnouncements, fn ($v) => filled($v)));
        $minutes->decisions = array_values(array_filter($this->minutesDecisions, fn ($v) => filled($v)));
        $minutes->notes = filled($this->minutesNotes) ? $this->minutesNotes : null;
        $minutes->save();

        $this->toast(type: 'success', title: __('Minutes saved'));
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
            'ends_at' => $proposal->proposed_at->copy()->addHours(2),
            'status' => MeetingStatusEnum::CONFIRMED,
        ]);

        $this->toast(type: 'success', title: __('Date confirmed: :date', [
            'date' => $proposal->proposed_at->translatedFormat('d M Y à H\hi'),
        ]));
        unset($this->meeting);
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

    // ── Invitations ───────────────────────────────────────────────────
    public function sendInvitations(): void
    {
        abort_unless($this->canManage, 403);

        if ($this->meeting->status !== MeetingStatusEnum::CONFIRMED) {
            $this->toast(type: 'error', title: __('Confirm the date before sending invitations'));

            return;
        }

        if ($this->sendChecklist !== []) {
            $this->toast(type: 'error', title: __('Complete the meeting before inviting'));

            return;
        }

        dispatch(new SendMeetingInvitationsJob($this->meetingId));

        $this->toast(type: 'success', title: __('Invitations queued — members will receive them shortly'));
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
            ? User::active()->get()
            : $this->committeeUsers;

        Notification::send($recipients, new MeetingMinutesNotification($meeting));

        $field = $toAll ? 'sent_to_all_at' : 'sent_to_committee_at';
        $minutes->update([$field => now()]);

        $this->toast(type: 'success', title: __('Minutes sent to :n members', ['n' => $recipients->count()]));
        unset($this->meeting);
    }

    public function unarchiveMeeting(): void
    {
        abort_unless($this->canManage, 403);

        Meeting::findOrFail($this->meetingId)->unarchive();

        $this->toast(type: 'success', title: __('Meeting restored'));
        unset($this->meeting);
    }

    #[Computed]
    public function usersForAssignment(): array
    {
        return $this->committeeUsers
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
            ->current(__('Meeting Details'));
    }

    private function loadActionItems(): void
    {
        $this->actionItems = $this->meeting->actionItems
            ->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description ?? '',
                'assigned_to_id' => (string) ($item->assigned_to_id ?? ''),
                'due_date' => $item->due_date?->format('Y-m-d') ?? '',
                'is_completed' => $item->is_completed,
            ])
            ->toArray();
    }

    private function loadMinutes(): void
    {
        $minutes = $this->meeting->minutes;
        if ($minutes) {
            $this->minutesAnnouncements = $minutes->announcements ?? [];
            $this->minutesDecisions = $minutes->decisions ?? [];
            $this->minutesNotes = $minutes->notes ?? '';
        }
    }

    // ── Cancel / postpone ─────────────────────────────────────────────

    /** Resolve recipients: invited users if any, otherwise the target audience. */
    private function resolveNotificationRecipients(Meeting $meeting): Illuminate\Database\Eloquent\Collection
    {
        $invited = $meeting->users()->get();

        if ($invited->isNotEmpty()) {
            return $invited;
        }

        // Nobody invited yet — fall back to target audience
        return $meeting->type === MeetingTypeEnum::GENERAL_ASSEMBLY
            ? User::active()->get()
            : User::where(fn ($q) => $q->where('is_admin', true)->orWhere('is_committee_member', true))->get();
    }
};
