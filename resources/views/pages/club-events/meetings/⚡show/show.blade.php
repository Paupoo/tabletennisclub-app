<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    @php $meeting = $this->meeting; @endphp

    <x-header :title="$meeting->title" separator progress-indicator>
        <x-slot:subtitle>
            <div class="flex flex-wrap items-center gap-2 mt-1">
                <x-badge :value="$meeting->type->getLabel()" class="badge-ghost" />
                <x-badge :value="$meeting->status->getLabel()" class="{{ $meeting->status->getBadgeClass() }}" />
                <div class="flex items-center gap-1 text-sm text-base-content/50">
                    <x-icon name="{{ $meeting->format->getIcon() }}" class="h-4 w-4" />
                    {{ $meeting->format->getLabel() }}
                </div>
            </div>
        </x-slot:subtitle>
        <x-slot:actions>
            @if ($this->canManage)
                @if ($meeting->status === \App\Domains\Shared\Enums\MeetingStatusEnum::CONFIRMED && $meeting->scheduled_at?->isPast())
                    <x-button :label="__('Mark completed')" icon="o-check-circle"
                        class="btn-success btn-sm"
                        wire:click="markCompleted" spinner="markCompleted" />
                @endif
                @if (! in_array($meeting->status->value, ['cancelled', 'completed']))
                    <x-button :label="__('Postpone')" icon="o-arrow-path"
                        class="btn-warning btn-outline btn-sm"
                        wire:click="$set('showPostponeModal', true)" />
                    <x-button :label="__('Cancel')" icon="o-x-circle"
                        class="btn-error btn-outline btn-sm"
                        wire:click="$set('showCancelModal', true)" />
                @endif
                <x-button :label="__('Edit')" icon="o-pencil-square"
                    class="btn-primary btn-sm"
                    link="{{ route('admin.meetings.edit', $meeting) }}" />
            @endif
        </x-slot:actions>
    </x-header>

    {{-- ── Tabs ─────────────────────────────────────────────────────── --}}
    <div class="tabs tabs-border mb-6 overflow-x-auto">
        @foreach ([
            ['key' => 'overview',    'label' => __('Overview'),    'icon' => 'o-information-circle'],
            ['key' => 'poll',        'label' => __('Date poll'),   'icon' => 'o-calendar'],
            ['key' => 'attendance',  'label' => __('Attendance'),  'icon' => 'o-users'],
            ['key' => 'minutes',     'label' => __('Minutes'),     'icon' => 'o-document-text'],
            ['key' => 'actions',     'label' => __('Actions'),     'icon' => 'o-check-badge'],
        ] as $tab)
            <button wire:click="$set('activeTab', '{{ $tab['key'] }}')"
                @class(['tab', 'tab-active' => $activeTab === $tab['key']])>
                <x-icon name="{{ $tab['icon'] }}" class="h-4 w-4 mr-1.5" />
                {{ $tab['label'] }}
            </button>
        @endforeach
    </div>

    {{-- ──────────────── OVERVIEW ────────────────────────────────────── --}}
    @if ($activeTab === 'overview')
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-4">
            {{-- Info card --}}
            <x-card>
                <div class="space-y-3 text-sm">
                    @if ($meeting->scheduled_at)
                        <div class="flex items-start gap-3">
                            <x-icon name="o-calendar" class="h-4 w-4 mt-0.5 text-base-content/40 shrink-0" />
                            <div>
                                <span class="font-medium">{{ $meeting->scheduled_at->translatedFormat('l d M Y') }}</span>
                                <span class="text-base-content/60"> · {{ $meeting->scheduled_at->format('H:i') }}
                                    @if ($meeting->ends_at) – {{ $meeting->ends_at->format('H:i') }} @endif
                                </span>
                            </div>
                        </div>
                    @else
                        <div class="flex items-center gap-3 text-base-content/40">
                            <x-icon name="o-calendar" class="h-4 w-4 shrink-0" />
                            <span class="italic">{{ __('Date not yet confirmed') }}</span>
                        </div>
                    @endif

                    @if ($meeting->format === \App\Domains\Shared\Enums\MeetingFormatEnum::PHYSICAL && $meeting->location)
                        <div class="flex items-start gap-3">
                            <x-icon name="o-map-pin" class="h-4 w-4 mt-0.5 text-base-content/40 shrink-0" />
                            <span>{{ $meeting->location }}</span>
                        </div>
                    @elseif ($meeting->format === \App\Domains\Shared\Enums\MeetingFormatEnum::VIRTUAL && $meeting->meeting_link)
                        <div class="flex items-start gap-3">
                            <x-icon name="o-video-camera" class="h-4 w-4 mt-0.5 text-base-content/40 shrink-0" />
                            <a href="{{ $meeting->meeting_link }}" target="_blank"
                                class="link link-primary break-all">{{ $meeting->meeting_link }}</a>
                        </div>
                    @endif

                    @if ($meeting->rsvp_deadline)
                        <div class="flex items-center gap-3">
                            <x-icon name="o-clock" class="h-4 w-4 text-base-content/40 shrink-0" />
                            <span>{{ __('RSVP deadline:') }}
                                <span class="font-medium">{{ $meeting->rsvp_deadline->translatedFormat('d M Y') }}</span>
                            </span>
                        </div>
                    @endif

                    @if ($meeting->has_meal)
                        <div class="flex items-start gap-3">
                            <x-icon name="o-cake" class="h-4 w-4 mt-0.5 text-base-content/40 shrink-0" />
                            <span>{{ $meeting->meal_description }}
                                @if ($meeting->meal_price) — {{ number_format($meeting->meal_price, 2) }} €/{{ __('person') }} @endif
                            </span>
                        </div>
                    @endif

                    @if ($meeting->description)
                        <div class="border-t border-base-200 pt-3 text-base-content/70">
                            {{ $meeting->description }}
                        </div>
                    @endif
                </div>
            </x-card>

            {{-- Agenda --}}
            @if ($meeting->agendaItems->isNotEmpty())
            <x-card :title="__('Agenda')">
                <ol class="space-y-2">
                    @foreach ($meeting->agendaItems as $i => $item)
                        <li class="flex gap-3">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full
                                bg-primary/10 text-xs font-bold text-primary">{{ $i + 1 }}</span>
                            <div>
                                <p class="font-medium text-sm">{{ $item->title }}</p>
                                @if ($item->description)
                                    <p class="text-xs text-base-content/60 mt-0.5">{{ $item->description }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </x-card>
            @endif

            {{-- Postponed / cancelled notice --}}
            @if ($meeting->status === \App\Domains\Shared\Enums\MeetingStatusEnum::CANCELLED && $meeting->cancellation_note)
                <x-alert icon="o-x-circle" class="alert-error alert-soft"
                    :title="__('Cancelled')" :description="$meeting->cancellation_note" />
            @endif
            @if ($meeting->status === \App\Domains\Shared\Enums\MeetingStatusEnum::POSTPONED)
                <x-alert icon="o-arrow-path" class="alert-warning alert-soft"
                    :title="__('Postponed')"
                    :description="$meeting->postponed_note ?? __('No reason provided.')" />
            @endif
        </div>

        {{-- Right column --}}
        <div class="space-y-4">
            {{-- Quick actions --}}
            @if ($this->canManage && ! in_array($meeting->status->value, ['cancelled', 'completed']))
            <x-card :title="__('Actions')">
                <div class="space-y-2">
                    @if ($meeting->isInPollPhase())
                        <x-button icon="o-paper-airplane" :label="__('Send date poll')"
                            class="btn-block btn-outline btn-sm"
                            wire:click="sendDatePoll" spinner="sendDatePoll" />
                    @endif
                    @if ($meeting->status === \App\Domains\Shared\Enums\MeetingStatusEnum::CONFIRMED)
                        <x-button icon="o-envelope" :label="__('Send invitations')"
                            class="btn-block btn-primary btn-sm"
                            wire:click="sendInvitations" spinner="sendInvitations" />
                    @endif
                </div>
            </x-card>
            @endif

            {{-- Quorum card (AG only) --}}
            @if ($meeting->quorum)
            @php
                $confirmed   = $meeting->confirmedCount();
                $quorum      = $meeting->quorum;
                $pct         = $meeting->quorumPercentage();
                $reached     = $meeting->isQuorumReached();
            @endphp
            <x-card>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <p class="font-semibold text-sm">{{ __('Quorum') }}</p>
                        <x-badge
                            :value="$reached ? __('Reached') : __('Not reached')"
                            class="{{ $reached ? 'badge-success badge-soft' : 'badge-warning badge-soft' }}" />
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-bold {{ $reached ? 'text-success' : 'text-warning' }}">
                            {{ $confirmed }}
                        </span>
                        <span class="text-base-content/50">/ {{ $quorum }}</span>
                        <span class="ml-auto text-sm text-base-content/40">{{ $pct }}%</span>
                    </div>
                    <div class="h-2 rounded-full bg-base-200 overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-500
                            {{ $reached ? 'bg-success' : 'bg-warning' }}"
                            style="width: {{ $pct }}%"></div>
                    </div>
                    <p class="text-xs text-base-content/40">
                        {{ __(':n more needed', ['n' => max(0, $quorum - $confirmed)]) }}
                    </p>
                </div>
            </x-card>
            @endif

            {{-- RSVPs summary --}}
            @if ($meeting->users->isNotEmpty())
            <x-card :title="__('RSVPs')">
                @php
                    $byStatus = $meeting->users->groupBy(fn ($u) => $u->registration->status->value);
                    $order = ['attended', 'confirmed', 'invited', 'declined', 'absent'];
                @endphp
                <div class="space-y-1.5">
                    @foreach ($order as $statusVal)
                        @php $grp = $byStatus[$statusVal] ?? collect(); @endphp
                        @if ($grp->isNotEmpty())
                            <div class="flex justify-between text-sm">
                                <span class="text-base-content/60">
                                    {{ \App\Domains\Shared\Enums\MeetingUserStatusEnum::from($statusVal)->getLabel() }}
                                </span>
                                <x-badge :value="$grp->count()"
                                    class="{{ \App\Domains\Shared\Enums\MeetingUserStatusEnum::from($statusVal)->getBadgeClass() }} badge-sm" />
                            </div>
                        @endif
                    @endforeach
                </div>
            </x-card>
            @endif
        </div>
    </div>

    {{-- ──────────────── DATE POLL ────────────────────────────────────── --}}
    @elseif ($activeTab === 'poll')
    @if ($meeting->dateProposals->isEmpty())
        <x-empty-state icon="o-calendar"
            :heading="__('No date proposals')"
            :message="__('Add date proposals in the meeting editor.')" />
    @else
    <div class="space-y-4">
        @foreach ($meeting->dateProposals as $proposal)
        @php
            $available   = $proposal->availableCount();
            $maybe       = $proposal->maybeCount();
            $unavailable = $proposal->unavailableCount();
            $total       = $available + $maybe + $unavailable;
            $pct         = $total > 0 ? round($available / $total * 100) : 0;
        @endphp
        <x-card @class(['border-success border-2' => $proposal->is_selected])>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        @if ($proposal->is_selected)
                            <x-icon name="o-check-circle" class="h-5 w-5 text-success" />
                        @endif
                        <p class="font-semibold">
                            {{ $proposal->proposed_at->translatedFormat('l d M Y · H\hi') }}
                        </p>
                    </div>
                    <div class="mt-2 flex items-center gap-4 text-sm">
                        <span class="flex items-center gap-1 text-success">
                            <x-icon name="o-check-circle" class="h-4 w-4" /> {{ $available }}
                        </span>
                        <span class="flex items-center gap-1 text-warning">
                            <x-icon name="o-question-mark-circle" class="h-4 w-4" /> {{ $maybe }}
                        </span>
                        <span class="flex items-center gap-1 text-error">
                            <x-icon name="o-x-circle" class="h-4 w-4" /> {{ $unavailable }}
                        </span>
                        <span class="text-base-content/40">({{ $total }} {{ __('votes') }})</span>
                    </div>
                    @if ($total > 0)
                        <div class="mt-2 flex h-2 w-48 overflow-hidden rounded-full bg-base-200">
                            <div class="bg-success" style="width: {{ $pct }}%"></div>
                        </div>
                    @endif
                </div>
                @if ($this->canManage && ! $proposal->is_selected && $meeting->status !== \App\Domains\Shared\Enums\MeetingStatusEnum::CANCELLED)
                    <x-button icon="o-check" :label="__('Select this date')"
                        class="btn-success btn-sm"
                        wire:click="selectDateProposal({{ $proposal->id }})"
                        spinner="selectDateProposal({{ $proposal->id }})" />
                @endif
            </div>

            {{-- Voter list --}}
            @if ($proposal->votes->isNotEmpty())
            <div class="mt-4 border-t border-base-200 pt-3">
                <div class="flex flex-wrap gap-2">
                    @foreach ($proposal->votes as $vote)
                        @php $voteEnum = \App\Domains\Shared\Enums\MeetingDateVoteEnum::from($vote->vote->value); @endphp
                        <div class="flex items-center gap-1.5 rounded-full border border-base-200 px-2 py-0.5 text-xs">
                            <x-icon name="{{ $voteEnum->getIcon() }}" class="h-3 w-3 {{ $voteEnum->getColor() }}" />
                            {{ $vote->user?->full_name ?? __('Unknown') }}
                        </div>
                    @endforeach
                </div>
            </div>
            @endif
        </x-card>
        @endforeach

        @if ($this->canManage)
        <div class="flex gap-2">
            <x-button icon="o-paper-airplane" :label="__('Send poll to committee')"
                class="btn-primary btn-sm"
                wire:click="sendDatePoll" spinner="sendDatePoll" />
        </div>
        @endif
    </div>
    @endif

    {{-- ──────────────── ATTENDANCE ───────────────────────────────────── --}}
    @elseif ($activeTab === 'attendance')
    @if ($meeting->users->isEmpty())
        <x-empty-state icon="o-users"
            :heading="__('No invitations sent yet')"
            :message="__('Send invitations from the Overview tab once the date is confirmed.')" />
    @else
    <div class="space-y-3">
        @foreach ($meeting->users->sortBy('last_name') as $user)
        @php $reg = $user->registration; @endphp
        <div class="flex items-center justify-between rounded-xl border border-base-200 bg-base-100 px-4 py-3"
            wire:key="att-{{ $user->id }}">
            <div class="flex items-center gap-3">
                @php $avatarSrc = $user->avatar_url ?? ($user->photo ? asset($user->photo) : null); @endphp
                @if ($avatarSrc)
                    <img src="{{ $avatarSrc }}" alt="{{ $user->full_name }}"
                        class="h-9 w-9 rounded-full object-cover" />
                @else
                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-base-200 font-bold text-sm">
                        {{ mb_strtoupper(mb_substr($user->first_name, 0, 1)) . mb_strtoupper(mb_substr($user->last_name, 0, 1)) }}
                    </div>
                @endif
                <div>
                    <p class="font-medium text-sm">{{ $user->full_name }}</p>
                    @if ($reg->response_at)
                        <p class="text-xs text-base-content/40">{{ $reg->response_at->translatedFormat('d M · H\hi') }}</p>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-2">
                <x-badge :value="$reg->status->getLabel()" class="{{ $reg->status->getBadgeClass() }} badge-sm" />
                @if ($this->canManage && $meeting->status === \App\Domains\Shared\Enums\MeetingStatusEnum::CONFIRMED && $meeting->scheduled_at?->isPast())
                    @if ($reg->status !== \App\Domains\Shared\Enums\MeetingUserStatusEnum::ATTENDED)
                        <x-button icon="o-check" class="btn-ghost btn-xs btn-circle text-success"
                            :tooltip="__('Mark attended')"
                            wire:click="markAttended({{ $user->id }})" />
                    @endif
                    @if ($reg->status !== \App\Domains\Shared\Enums\MeetingUserStatusEnum::ABSENT)
                        <x-button icon="o-x-mark" class="btn-ghost btn-xs btn-circle text-error"
                            :tooltip="__('Mark absent')"
                            wire:click="markAbsent({{ $user->id }})" />
                    @endif
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- ──────────────── MINUTES ──────────────────────────────────────── --}}
    @elseif ($activeTab === 'minutes')
    @php $minutes = $meeting->minutes; @endphp
    <div class="mx-auto max-w-2xl space-y-6">
        @if ($minutes?->is_published)
            <x-alert icon="o-check-circle" class="alert-success alert-soft"
                :title="__('Published on :date', ['date' => $minutes->published_at?->translatedFormat('d M Y')])" />
        @endif

        {{-- Announcements --}}
        <x-card :title="__('Announcements')">
            <div class="space-y-2">
                @foreach ($minutesAnnouncements as $i => $ann)
                    <div class="flex items-start gap-2" wire:key="ann-{{ $i }}">
                        <x-textarea wire:model="minutesAnnouncements.{{ $i }}"
                            :placeholder="__('Announcement :n', ['n' => $i + 1])"
                            rows="2" class="flex-1" />
                        <button type="button" wire:click="removeAnnouncement({{ $i }})"
                            class="btn btn-ghost btn-xs btn-circle text-error mt-2">
                            <x-icon name="o-trash" class="h-4 w-4" />
                        </button>
                    </div>
                @endforeach
                @if ($this->canManage)
                    <x-button icon="o-plus" :label="__('Add announcement')"
                        class="btn-ghost btn-sm" wire:click="addAnnouncement" />
                @endif
            </div>
        </x-card>

        {{-- Decisions --}}
        <x-card :title="__('Decisions')">
            <div class="space-y-2">
                @foreach ($minutesDecisions as $i => $dec)
                    <div class="flex items-start gap-2" wire:key="dec-{{ $i }}">
                        <x-textarea wire:model="minutesDecisions.{{ $i }}"
                            :placeholder="__('Decision :n', ['n' => $i + 1])"
                            rows="2" class="flex-1" />
                        <button type="button" wire:click="removeDecision({{ $i }})"
                            class="btn btn-ghost btn-xs btn-circle text-error mt-2">
                            <x-icon name="o-trash" class="h-4 w-4" />
                        </button>
                    </div>
                @endforeach
                @if ($this->canManage)
                    <x-button icon="o-plus" :label="__('Add decision')"
                        class="btn-ghost btn-sm" wire:click="addDecision" />
                @endif
            </div>
        </x-card>

        {{-- Free notes --}}
        <x-card :title="__('Additional notes')">
            <x-textarea wire:model="minutesNotes" rows="5"
                :placeholder="__('Free-form notes, observations…')" />
        </x-card>

        @if ($this->canManage)
        <div class="flex flex-wrap gap-2">
            <x-button icon="o-archive-box" :label="__('Save draft')"
                class="btn-ghost btn-sm" wire:click="saveMinutes" spinner="saveMinutes" />
            @if (! $minutes?->is_published)
                <x-button icon="o-eye" :label="__('Publish minutes')"
                    class="btn-primary btn-sm" wire:click="publishMinutes" spinner="publishMinutes" />
            @else
                <x-button icon="o-paper-airplane"
                    :label="__('Send to committee')"
                    class="btn-outline btn-sm"
                    wire:click="sendMinutes(false)" spinner="sendMinutes(false)"
                    :disabled="(bool) $minutes?->sent_to_committee_at" />
                @if ($meeting->type === \App\Domains\Shared\Enums\MeetingTypeEnum::GENERAL_ASSEMBLY)
                    <x-button icon="o-paper-airplane"
                        :label="__('Send to all members')"
                        class="btn-outline btn-sm"
                        wire:click="sendMinutes(true)" spinner="sendMinutes(true)"
                        :disabled="(bool) $minutes?->sent_to_all_at" />
                @endif
            @endif
        </div>
        @endif
    </div>

    {{-- ──────────────── ACTION ITEMS ─────────────────────────────────── --}}
    @elseif ($activeTab === 'actions')
    <div class="mx-auto max-w-2xl space-y-3">
        @forelse ($actionItems as $i => $item)
        <div class="rounded-xl border border-base-200 bg-base-100 p-4 space-y-3"
            wire:key="action-{{ $i }}">
            <div class="flex items-start gap-2">
                <x-checkbox wire:model="actionItems.{{ $i }}.is_completed" class="mt-1" />
                <div class="flex-1 space-y-2">
                    <x-input wire:model="actionItems.{{ $i }}.title"
                        :placeholder="__('Action to take…')"
                        @class(['line-through opacity-60' => $item['is_completed']]) />
                    <x-textarea wire:model="actionItems.{{ $i }}.description"
                        rows="2" :placeholder="__('Details…')" />
                    <div class="grid grid-cols-2 gap-3">
                        <x-select :label="__('Assigned to')"
                            :options="$this->usersForAssignment"
                            wire:model="actionItems.{{ $i }}.assigned_to_id"
                            :placeholder="__('Nobody')" />
                        <x-datepicker :label="__('Due date')"
                            wire:model="actionItems.{{ $i }}.due_date" />
                    </div>
                </div>
                <button type="button" wire:click="removeActionItem({{ $i }})"
                    class="btn btn-ghost btn-xs btn-circle text-error mt-1">
                    <x-icon name="o-trash" class="h-4 w-4" />
                </button>
            </div>
        </div>
        @empty
            <x-empty-state icon="o-check-badge"
                :heading="__('No action items')"
                :message="__('Add tasks that need follow-up after the meeting.')" />
        @endforelse

        @if ($this->canManage)
        <div class="flex gap-2">
            <x-button icon="o-plus" :label="__('Add action item')"
                class="btn-ghost btn-sm" wire:click="addActionItem" />
            @if (count($actionItems) > 0)
                <x-button icon="o-archive-box" :label="__('Save')"
                    class="btn-primary btn-sm" wire:click="saveActionItems" spinner="saveActionItems" />
            @endif
        </div>
        @endif
    </div>
    @endif

    {{-- ── Cancel modal ──────────────────────────────────────────────── --}}
    <x-modal wire:model="showCancelModal" :title="__('Cancel meeting')" class="backdrop-blur">
        <div class="space-y-4">
            <x-alert icon="o-exclamation-triangle" class="alert-error alert-soft"
                :title="__('All invited members will be notified.')" />
            <x-textarea :label="__('Reason / message (optional)')"
                wire:model="cancellationNote" rows="3" />
        </div>
        <x-slot:actions>
            <x-button :label="__('Back')" wire:click="$set('showCancelModal', false)" />
            <x-button :label="__('Yes, cancel')" icon="o-x-circle"
                class="btn-error" wire:click="cancelMeeting" spinner="cancelMeeting" />
        </x-slot:actions>
    </x-modal>

    {{-- ── Postpone modal ────────────────────────────────────────────── --}}
    <x-modal wire:model="showPostponeModal" :title="__('Postpone meeting')" class="backdrop-blur">
        <div class="space-y-4">
            <x-datetime type="datetime-local" :label="__('New proposed date (optional)')" wire:model="postponedTo" />
            <x-textarea :label="__('Reason / message (optional)')"
                wire:model="postponedNote" rows="3" />
        </div>
        <x-slot:actions>
            <x-button :label="__('Back')" wire:click="$set('showPostponeModal', false)" />
            <x-button :label="__('Postpone')" icon="o-arrow-path"
                class="btn-warning" wire:click="postponeMeeting" spinner="postponeMeeting" />
        </x-slot:actions>
    </x-modal>
</div>
