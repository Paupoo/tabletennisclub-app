<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    @php
        $meeting = $this->meeting;
        $isPast = $meeting->scheduled_at?->isPast() ?? false;
        $showAttendance = $meeting->users->isNotEmpty();
        // Minutes are preparable as soon as the date is confirmed; publishing stays
        // locked until the meeting actually took place (guard on the minutes page).
        $showMinutes = $meeting->status === \App\Domains\Shared\Enums\MeetingStatusEnum::COMPLETED
            || $meeting->status === \App\Domains\Shared\Enums\MeetingStatusEnum::CONFIRMED
            || $meeting->minutes !== null;
        $showPoll = $meeting->status === \App\Domains\Shared\Enums\MeetingStatusEnum::PLANNING
            && $meeting->dateProposals->isNotEmpty();
    @endphp

    <x-header :title="$meeting->title" separator progress-indicator>
        <x-slot:subtitle>
            <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-base-content/60">
                <x-badge :value="$meeting->status->getLabel()" class="{{ $meeting->status->getBadgeClass() }}" />
                <span>{{ $meeting->type->getLabel() }}</span>
                <span class="text-base-content/30">·</span>
                <span class="flex items-center gap-1">
                    <x-icon name="{{ $meeting->format->getIcon() }}" class="h-4 w-4" />
                    {{ $meeting->format->getLabel() }}
                </span>
            </div>
        </x-slot:subtitle>
        <x-slot:actions>
            @if ($this->canManage)
                <x-dropdown icon="o-ellipsis-vertical" right class="btn-ghost btn-sm">
                    <x-menu-item icon="o-pencil-square" :title="__('Rename')"
                        wire:click="editTitle" />
                    @if (! in_array($meeting->status->value, ['cancelled', 'completed']))
                        <x-menu-item icon="o-arrow-path" :title="__('Postpone')"
                            wire:click="$set('showPostponeModal', true)" />
                        <x-menu-item icon="o-x-circle" :title="__('Cancel')"
                            wire:click="$set('showCancelModal', true)" />
                    @endif
                    @if ($meeting->isArchived())
                        <x-menu-item icon="o-arrow-uturn-left" :title="__('Restore')"
                            wire:click="unarchiveMeeting" />
                    @elseif ($meeting->canBeArchived())
                        <x-menu-item icon="o-archive-box" :title="__('Archive')"
                            wire:click="archiveMeeting" />
                    @endif
                    @if ($meeting->canBeDeleted())
                        <x-menu-item icon="o-trash" :title="__('Delete')"
                            wire:click="$set('showDeleteModal', true)" />
                    @endif
                </x-dropdown>
            @endif
        </x-slot:actions>
    </x-header>

    {{-- ── Cancelled / postponed notice ─────────────────────────────── --}}
    @if ($meeting->status === \App\Domains\Shared\Enums\MeetingStatusEnum::CANCELLED)
        <x-alert icon="o-x-circle" class="alert-error alert-soft mb-6"
            :title="__('Cancelled')"
            :description="$meeting->cancellation_note ?? __('No reason provided.')" />
    @elseif ($meeting->status === \App\Domains\Shared\Enums\MeetingStatusEnum::POSTPONED)
        <x-alert icon="o-arrow-path" class="alert-warning alert-soft mb-6"
            :title="__('Postponed')"
            :description="$meeting->postponed_note ?? __('No reason provided.')" />
    @endif

    {{-- ── Next step banner ─────────────────────────────────────────── --}}
    @if ($this->canManage && $this->nextStep)
        <div class="mb-6 flex flex-col gap-3 rounded-xl border border-primary/30 bg-primary/5 px-4 py-3 sm:flex-row sm:items-center">
            <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold uppercase tracking-widest text-primary">{{ __('Next step') }}</p>
                <p class="mt-0.5 font-semibold">{{ $this->nextStep['title'] }}</p>
                @if ($this->nextStep['description'])
                    <p class="text-sm text-base-content/60">{{ $this->nextStep['description'] }}</p>
                @endif
            </div>
            @if ($this->nextStep['action'])
                <x-button :label="$this->nextStep['label']"
                    class="btn-primary btn-sm shrink-0"
                    wire:click="{{ $this->nextStep['action'] }}"
                    spinner="{{ $this->nextStep['action'] }}" />
            @elseif ($this->nextStep['link'] ?? null)
                <x-button :label="$this->nextStep['label']"
                    class="btn-primary btn-sm shrink-0"
                    link="{{ $this->nextStep['link'] }}" />
            @endif
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- ════════════ Main column ════════════ --}}
        <div class="space-y-6 lg:col-span-2">

            {{-- Practical info --}}
            <x-card :title="__('Practical info')">
                @if ($this->canManage)
                    <x-slot:menu>
                        @if ($editing !== 'details')
                            <x-button icon="o-pencil-square" class="btn-ghost btn-xs btn-circle"
                                :tooltip="__('Edit')" wire:click="editDetails" />
                        @endif
                    </x-slot:menu>
                @endif

                @if ($editing === 'details')
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <x-datetime type="datetime-local" :label="__('Date & time')" wire:model="detailsScheduledAt" />
                            <x-datetime type="datetime-local" :label="__('End time')" wire:model="detailsEndsAt" />
                        </div>
                        <x-select :label="__('Format')" wire:model.live="detailsFormat"
                            :options="\App\Domains\Shared\Enums\MeetingFormatEnum::getOptions()" />
                        @if ($detailsFormat === 'physical')
                            <x-input :label="__('Location')" wire:model="detailsLocation"
                                icon="o-map-pin" :placeholder="__('Club room, bar address…')" />
                        @else
                            <x-input :label="__('Meeting link')" wire:model="detailsMeetingLink"
                                icon="o-video-camera" placeholder="https://meet.google.com/…" />
                        @endif
                        <x-datepicker :label="__('RSVP deadline')" wire:model="detailsRsvpDeadline"
                            :placeholder="__('Optional')" />
                        <x-textarea :label="__('Description')" wire:model="detailsDescription"
                            :placeholder="__('Optional context for attendees')" rows="3" />
                        <div class="flex justify-end gap-2">
                            <x-button :label="__('Cancel')" class="btn-ghost btn-sm" wire:click="cancelEditing" />
                            <x-button :label="__('Save')" icon="o-check" class="btn-primary btn-sm"
                                wire:click="saveDetails" spinner="saveDetails" />
                        </div>
                    </div>
                @else
                <div class="space-y-3 text-sm">
                    @if ($meeting->scheduled_at)
                        <div class="flex items-start gap-3">
                            <x-icon name="o-calendar" class="mt-0.5 h-4 w-4 shrink-0 text-base-content/40" />
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
                            <x-icon name="o-map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-base-content/40" />
                            <span>{{ $meeting->location }}</span>
                        </div>
                    @elseif ($meeting->format === \App\Domains\Shared\Enums\MeetingFormatEnum::VIRTUAL && $meeting->meeting_link)
                        <div class="flex items-start gap-3">
                            <x-icon name="o-video-camera" class="mt-0.5 h-4 w-4 shrink-0 text-base-content/40" />
                            <a href="{{ $meeting->meeting_link }}" target="_blank"
                                class="link link-primary break-all">{{ $meeting->meeting_link }}</a>
                        </div>
                    @endif

                    @if ($meeting->rsvp_deadline)
                        <div class="flex items-center gap-3">
                            <x-icon name="o-clock" class="h-4 w-4 shrink-0 text-base-content/40" />
                            <span>{{ __('RSVP deadline:') }}
                                <span class="font-medium">{{ $meeting->rsvp_deadline->translatedFormat('d M Y') }}</span>
                            </span>
                        </div>
                    @endif

                    @if ($meeting->has_meal)
                        <div class="flex items-start gap-3">
                            <x-icon name="o-cake" class="mt-0.5 h-4 w-4 shrink-0 text-base-content/40" />
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
                @endif
            </x-card>

            {{-- Agenda --}}
            @if ($meeting->agendaItems->isNotEmpty() || $this->canManage)
                <x-card :title="__('Agenda')">
                    @if ($this->canManage)
                        <x-slot:menu>
                            @if ($editing !== 'agenda')
                                <x-button icon="o-pencil-square" class="btn-ghost btn-xs btn-circle"
                                    :tooltip="__('Edit')" wire:click="editAgenda" />
                            @endif
                        </x-slot:menu>
                    @endif

                    @if ($editing === 'agenda')
                        <div class="space-y-3">
                            @foreach ($agendaDraft as $i => $item)
                                <div class="flex items-start gap-2 rounded-lg border border-base-200 p-3"
                                    wire:key="agenda-draft-{{ $i }}">
                                    <div class="flex-1 space-y-2">
                                        <x-input wire:model="agendaDraft.{{ $i }}.title"
                                            :placeholder="__('Point :n', ['n' => $i + 1])" />
                                        <x-textarea wire:model="agendaDraft.{{ $i }}.description"
                                            :placeholder="__('Optional details…')" rows="2" />
                                    </div>
                                    <x-button icon="o-trash" class="btn-ghost btn-xs btn-circle mt-1"
                                        wire:click="removeAgendaDraftItem({{ $i }})" />
                                </div>
                            @endforeach
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <x-button icon="o-plus" :label="__('Add item')"
                                    class="btn-ghost btn-sm" wire:click="addAgendaDraftItem" />
                                <div class="flex gap-2">
                                    <x-button :label="__('Cancel')" class="btn-ghost btn-sm" wire:click="cancelEditing" />
                                    <x-button :label="__('Save')" icon="o-check" class="btn-primary btn-sm"
                                        wire:click="saveAgenda" spinner="saveAgenda" />
                                </div>
                            </div>
                        </div>
                    @elseif ($meeting->agendaItems->isEmpty())
                        <p class="text-sm italic text-base-content/50">
                            {{ __('No agenda yet — add the points to discuss.') }}
                        </p>
                    @else
                        <ol class="space-y-2" @if ($this->canManage) wire:sort="reorderAgenda" @endif>
                            @foreach ($meeting->agendaItems as $i => $item)
                                <li class="flex items-start gap-3" wire:key="agenda-{{ $item->id }}"
                                    @if ($this->canManage) wire:sort:item="{{ $item->id }}" @endif>
                                    @if ($this->canManage)
                                        <span wire:sort:handle
                                            class="mt-0.5 cursor-grab text-base-content/30 hover:text-base-content/60">
                                            <x-icon name="o-bars-3" class="h-4 w-4" />
                                        </span>
                                    @endif
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full
                                        bg-base-200 text-xs font-bold text-base-content/60">{{ $i + 1 }}</span>
                                    <div>
                                        <p class="text-sm font-medium">{{ $item->title }}</p>
                                        @if ($item->description)
                                            <p class="mt-0.5 text-xs text-base-content/60">{{ $item->description }}</p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </x-card>
            @endif

            {{-- ── Date poll (planning phase only) ────────────────────── --}}
            @if ($showPoll)
                <x-card :title="__('Date poll')">
                    @php
                        $bestAvailable = $meeting->dateProposals->max(fn ($p) => $p->availableCount());
                    @endphp
                    <div class="space-y-4">
                        @foreach ($meeting->dateProposals as $proposal)
                            @php
                                $available   = $proposal->availableCount();
                                $maybe       = $proposal->maybeCount();
                                $unavailable = $proposal->unavailableCount();
                                $total       = $available + $maybe + $unavailable;
                                $isLeading   = $bestAvailable > 0 && $available === $bestAvailable;
                            @endphp
                            <div @class([
                                    'rounded-xl border p-4',
                                    'border-primary' => $proposal->is_selected,
                                    'border-primary/40 bg-primary/5' => ! $proposal->is_selected && $isLeading,
                                    'border-base-200' => ! $proposal->is_selected && ! $isLeading,
                                ])
                                wire:key="proposal-{{ $proposal->id }}">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="flex flex-wrap items-center gap-2 font-semibold">
                                            @if ($proposal->is_selected)
                                                <x-icon name="o-check-circle" class="h-5 w-5 text-primary" />
                                            @endif
                                            {{ $proposal->proposed_at->translatedFormat('l d M Y · H\hi') }}
                                            @if ($isLeading && ! $proposal->is_selected)
                                                <span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
                                                    {{ __('Leading option') }}
                                                </span>
                                            @endif
                                        </p>
                                        <div class="mt-2 flex items-center gap-4 text-sm text-base-content/60">
                                            <span class="flex items-center gap-1">
                                                <x-icon name="o-check-circle" class="h-4 w-4" /> {{ $available }}
                                            </span>
                                            <span class="flex items-center gap-1">
                                                <x-icon name="o-question-mark-circle" class="h-4 w-4" /> {{ $maybe }}
                                            </span>
                                            <span class="flex items-center gap-1">
                                                <x-icon name="o-x-circle" class="h-4 w-4" /> {{ $unavailable }}
                                            </span>
                                            <span class="text-base-content/40">({{ $total }} {{ __('votes') }})</span>
                                        </div>
                                    </div>
                                    @if ($this->canManage && ! $proposal->is_selected)
                                        <x-button icon="o-check" :label="__('Select this date')"
                                            class="btn-outline btn-sm"
                                            wire:click="selectDateProposal({{ $proposal->id }})"
                                            spinner="selectDateProposal({{ $proposal->id }})" />
                                    @endif
                                </div>

                                @if ($proposal->votes->isNotEmpty())
                                    <div class="mt-3 flex flex-wrap gap-2 border-t border-base-200 pt-3">
                                        @foreach ($proposal->votes as $vote)
                                            @php $voteEnum = \App\Domains\Shared\Enums\MeetingDateVoteEnum::from($vote->vote->value); @endphp
                                            <div class="flex items-center gap-1.5 rounded-full border border-base-200 px-2 py-0.5 text-xs"
                                                wire:key="vote-{{ $vote->id }}">
                                                <x-icon name="{{ $voteEnum->getIcon() }}" class="h-3 w-3 {{ $voteEnum->getColor() }}" />
                                                {{ $vote->user?->full_name ?? __('Unknown') }}
                                            </div>
                                        @endforeach
                                    </div>
                                @elseif ($this->canManage)
                                    <div class="mt-3 border-t border-base-200 pt-2">
                                        <x-button icon="o-trash" :label="__('Remove this option')"
                                            class="btn-ghost btn-xs"
                                            wire:click="removeProposal({{ $proposal->id }})" />
                                    </div>
                                @endif
                            </div>
                        @endforeach

                        @if ($this->canManage)
                            <div class="flex flex-col gap-2 border-t border-base-200 pt-4 sm:flex-row sm:items-end">
                                <x-datetime type="datetime-local" :label="__('Add date option')"
                                    wire:model="newProposalAt" class="flex-1" />
                                <x-button icon="o-plus" :label="__('Add')"
                                    class="btn-outline btn-sm"
                                    wire:click="addProposal" spinner="addProposal" />
                            </div>
                        @endif
                    </div>
                </x-card>
            @endif

            {{-- ── Responses & attendance ─────────────────────────────── --}}
            @if ($showAttendance)
                <x-card :title="$isPast ? __('Attendance') : __('Responses')">
                    @if ($meeting->has_meal)
                        <div class="mb-4 flex flex-wrap items-center gap-x-6 gap-y-1 rounded-xl border border-base-200 px-4 py-3 text-sm">
                            <span class="flex items-center gap-2 font-semibold">
                                <x-icon name="o-cake" class="h-4 w-4 text-base-content/40" />
                                {{ __('Catering') }}
                            </span>
                            <span><span class="font-semibold">{{ $meeting->mealReservedCount() }}</span> {{ __('reserved') }}</span>
                            @if ($meeting->meal_price_cents)
                                <span class="text-base-content/60">{{ number_format($meeting->mealExpectedCents() / 100, 2, ',', ' ') }} € {{ __('expected') }}</span>
                                <span class="text-base-content/60">{{ number_format($meeting->mealPaidCents() / 100, 2, ',', ' ') }} € {{ __('paid') }}</span>
                            @endif
                        </div>
                    @endif

                    <div class="space-y-2">
                        @foreach ($meeting->users->sortBy('last_name') as $user)
                            @php $reg = $user->registration; @endphp
                            <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-base-200 px-4 py-3"
                                wire:key="att-{{ $user->id }}">
                                <div class="flex min-w-0 items-center gap-3">
                                    @php $avatarSrc = $user->avatar_url ?? ($user->photo ? asset($user->photo) : null); @endphp
                                    @if ($avatarSrc)
                                        <img src="{{ $avatarSrc }}" alt="{{ $user->full_name }}"
                                            class="h-9 w-9 shrink-0 rounded-full object-cover" />
                                    @else
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-base-200 text-sm font-bold">
                                            {{ mb_strtoupper(mb_substr($user->first_name, 0, 1)) . mb_strtoupper(mb_substr($user->last_name, 0, 1)) }}
                                        </div>
                                    @endif
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium">{{ $user->full_name }}</p>
                                        <div class="flex flex-wrap items-center gap-x-2 text-xs text-base-content/40">
                                            @if ($reg->response_at)
                                                <span>{{ $reg->response_at->translatedFormat('d M · H\hi') }}</span>
                                            @endif
                                            @if ($meeting->has_meal)
                                                @php
                                                    $mealPayment = $this->mealPaymentsByRegistration[$reg->id] ?? null;
                                                    $mealPaid    = $mealPayment && ($mealPayment->amount_paid > 0 || $mealPayment->status !== 'pending');
                                                @endphp
                                                <span class="flex items-center gap-1">
                                                    <x-icon name="o-cake" class="h-3 w-3" />
                                                    @if ($reg->meal_reserved === true && $mealPaid)
                                                        {{ __('Meal · paid') }}
                                                    @elseif ($reg->meal_reserved === true)
                                                        {{ __('Meal · pending') }}
                                                    @elseif ($reg->meal_reserved === false)
                                                        {{ __('No meal') }}
                                                    @else
                                                        {{ __('No meal answer yet') }}
                                                    @endif
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <x-badge :value="$reg->status->getLabel()" class="{{ $reg->status->getBadgeClass() }} badge-sm" />
                                    @if ($this->canManage && $isPast && ! in_array($meeting->status->value, ['cancelled'], true))
                                        @if ($reg->status !== \App\Domains\Shared\Enums\MeetingUserStatusEnum::ATTENDED)
                                            <x-button icon="o-check" class="btn-ghost btn-xs btn-circle"
                                                :tooltip="__('Mark attended')"
                                                wire:click="markAttended({{ $user->id }})" />
                                        @endif
                                        @if ($reg->status !== \App\Domains\Shared\Enums\MeetingUserStatusEnum::ABSENT)
                                            <x-button icon="o-x-mark" class="btn-ghost btn-xs btn-circle"
                                                :tooltip="__('Mark absent')"
                                                wire:click="markAbsent({{ $user->id }})" />
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endif

            {{-- ── Minutes ────────────────────────────────────────────── --}}
            @if ($showMinutes)
                @php $minutes = $meeting->minutes; @endphp

                @if ($this->canManage)
                    <x-card :title="__('Minutes')">
                        <x-slot:menu>
                            @if ($minutes?->is_published)
                                <span class="text-xs text-base-content/50">
                                    {{ __('Published on :date', ['date' => $minutes->published_at?->translatedFormat('d M Y')]) }}
                                </span>
                            @endif
                        </x-slot:menu>

                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-sm text-base-content/60">
                                @if ($minutes)
                                    {{ __(':a announcements · :d decisions · :t action items', [
                                        'a' => count($minutes->announcements ?? []),
                                        'd' => count($minutes->decisions ?? []),
                                        't' => $meeting->actionItems->count(),
                                    ]) }}
                                @else
                                    {{ __('Nothing written yet.') }}
                                @endif
                            </p>
                            <x-button icon="o-document-text"
                                :label="$minutes ? __('Open the minutes') : ($isPast ? __('Write the minutes') : __('Prepare the minutes'))"
                                class="btn-outline btn-sm shrink-0"
                                link="{{ route('admin.meetings.minutes', $meeting) }}" />
                        </div>
                    </x-card>
                @elseif ($minutes?->is_published)
                    <x-card :title="__('Minutes')">
                        <div class="space-y-4 text-sm">
                            @if (filled($minutes->announcements))
                                <div>
                                    <p class="mb-1 font-semibold">{{ __('Announcements') }}</p>
                                    <ul class="list-inside list-disc space-y-1 text-base-content/70">
                                        @foreach ($minutes->announcements as $ann)
                                            <li>{{ $ann }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            @if (filled($minutes->decisions))
                                <div>
                                    <p class="mb-1 font-semibold">{{ __('Decisions') }}</p>
                                    <ul class="list-inside list-disc space-y-1 text-base-content/70">
                                        @foreach ($minutes->decisions as $dec)
                                            <li>{{ $dec }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            @if ($minutes->notes)
                                <div>
                                    <p class="mb-1 font-semibold">{{ __('Additional notes') }}</p>
                                    <p class="text-base-content/70">{{ $minutes->notes }}</p>
                                </div>
                            @endif
                        </div>
                    </x-card>
                @endif
            @endif

        </div>

        {{-- ════════════ Side column ════════════ --}}
        <div class="space-y-6">
            {{-- Quorum (AG only) --}}
            @if ($meeting->quorum || ($this->canManage && $meeting->type === \App\Domains\Shared\Enums\MeetingTypeEnum::GENERAL_ASSEMBLY))
                <x-card :title="__('Quorum')">
                    <x-slot:menu>
                        @if ($meeting->quorum && ! $meeting->isQuorumReached())
                            <x-badge :value="__('Not reached')" class="badge-warning badge-soft badge-sm" />
                        @endif
                        @if ($this->canManage && $editing !== 'quorum')
                            <x-button icon="o-pencil-square" class="btn-ghost btn-xs btn-circle"
                                :tooltip="__('Edit')" wire:click="editQuorum" />
                        @endif
                    </x-slot:menu>

                    @if ($editing === 'quorum')
                        <div class="space-y-3">
                            <x-input type="number" :label="__('Quorum (optional)')" wire:model="quorumDraft"
                                min="1" icon="o-users"
                                :hint="__('Minimum number of confirmed members required')" />
                            <div class="flex justify-end gap-2">
                                <x-button :label="__('Cancel')" class="btn-ghost btn-sm" wire:click="cancelEditing" />
                                <x-button :label="__('Save')" icon="o-check" class="btn-primary btn-sm"
                                    wire:click="saveQuorum" spinner="saveQuorum" />
                            </div>
                        </div>
                    @elseif ($meeting->quorum)
                        @php
                            $confirmed = $meeting->confirmedCount();
                            $quorum    = $meeting->quorum;
                            $pct       = $meeting->quorumPercentage();
                            $reached   = $meeting->isQuorumReached();
                        @endphp
                        <div class="space-y-3">
                            <div class="flex items-baseline gap-2">
                                <span class="text-3xl font-bold">{{ $confirmed }}</span>
                                <span class="text-base-content/50">/ {{ $quorum }}</span>
                                <span class="ml-auto text-sm text-base-content/40">{{ $pct }}%</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-base-200">
                                <div class="h-full rounded-full {{ $reached ? 'bg-primary' : 'bg-warning' }} transition-all duration-500"
                                    style="width: {{ $pct }}%"></div>
                            </div>
                            @unless ($reached)
                                <p class="text-xs text-base-content/40">
                                    {{ __(':n more needed', ['n' => max(0, $quorum - $confirmed)]) }}
                                </p>
                            @endunless
                        </div>
                    @else
                        <p class="text-sm italic text-base-content/50">{{ __('No quorum set') }}</p>
                    @endif
                </x-card>
            @endif

            {{-- Meal --}}
            @if ($this->canManage)
                <x-card :title="__('Meal')">
                    <x-slot:menu>
                        @if ($editing !== 'meal')
                            <x-button icon="o-pencil-square" class="btn-ghost btn-xs btn-circle"
                                :tooltip="__('Edit')" wire:click="editMeal" />
                        @endif
                    </x-slot:menu>

                    @if ($editing === 'meal')
                        <div class="space-y-3">
                            <x-toggle :label="__('This meeting includes a meal')"
                                wire:model.live="mealHasDraft" />
                            @if ($mealHasDraft)
                                <x-input :label="__('Meal description')" wire:model="mealDescriptionDraft"
                                    :placeholder="__('e.g. Pizzas, 3-course menu…')" />
                                <x-input type="number" :label="__('Price per person (€)')"
                                    wire:model="mealPriceDraft" min="0" step="0.50" />
                            @endif
                            <div class="flex justify-end gap-2">
                                <x-button :label="__('Cancel')" class="btn-ghost btn-sm" wire:click="cancelEditing" />
                                <x-button :label="__('Save')" icon="o-check" class="btn-primary btn-sm"
                                    wire:click="saveMeal" spinner="saveMeal" />
                            </div>
                        </div>
                    @elseif ($meeting->has_meal)
                        <p class="text-sm">{{ $meeting->meal_description }}
                            @if ($meeting->meal_price)
                                <span class="text-base-content/60">— {{ number_format($meeting->meal_price, 2) }} €/{{ __('person') }}</span>
                            @endif
                        </p>
                    @else
                        <p class="text-sm italic text-base-content/50">{{ __('No meal planned') }}</p>
                    @endif
                </x-card>
            @endif

            {{-- RSVP summary --}}
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
                                    <span class="font-medium tabular-nums">{{ $grp->count() }}</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </x-card>
            @endif

            {{-- Website promotion --}}
            @if ($this->canManage)
                <x-card :title="__('Website')" separator>
                    <x-slot:menu>
                        @if ($meeting->eventPost?->status === \App\Domains\Shared\Enums\EventPostStatusEnum::PUBLISHED)
                            <x-badge class="badge-success badge-soft badge-sm" icon="o-globe-alt" value="{{ __('Published') }}" />
                        @elseif ($meeting->eventPost)
                            <span class="text-xs text-base-content/50">{{ __('Draft') }}</span>
                        @endif
                    </x-slot:menu>

                    @if ($meeting->scheduled_at)
                        <livewire:admin.shared.event-post-button
                            :model-class="\App\Domains\Meetings\Models\Meeting::class"
                            :model-id="$meeting->id"
                            event-type="MEETING"
                            icon="📋"
                            :event-date="$meeting->scheduled_at?->toDateString()"
                            :start-time="$meeting->scheduled_at?->format('H:i:s')"
                            :end-time="$meeting->ends_at?->format('H:i:s')"
                            :default-title="$meeting->title"
                            :default-location="$meeting->format === \App\Domains\Shared\Enums\MeetingFormatEnum::PHYSICAL ? $meeting->location : $meeting->meeting_link"
                            :default-description="$meeting->description"
                            :can-publish="true"
                            wire:key="ep-show-meeting-{{ $meeting->id }}" />
                    @else
                        <p class="text-sm italic text-base-content/50">
                            {{ __('Set a confirmed date before publishing this meeting on the website.') }}
                        </p>
                    @endif
                </x-card>
            @endif
        </div>
    </div>

    {{-- ── Cancel modal ──────────────────────────────────────────────── --}}
    <x-app-modal wire:model="showCancelModal" :title="__('Cancel meeting')" class="backdrop-blur">
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
    </x-app-modal>

    {{-- ── Postpone modal ────────────────────────────────────────────── --}}
    <x-app-modal wire:model="showPostponeModal" :title="__('Postpone meeting')" class="backdrop-blur">
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
    </x-app-modal>

    {{-- ── Rename modal ──────────────────────────────────────────────── --}}
    <x-app-modal wire:model="showTitleModal" :title="__('Rename')" class="backdrop-blur">
        <div class="space-y-4">
            <x-input :label="__('Title')" wire:model="titleDraft" required />
            @if (! $this->invitationsSent)
                <x-select :label="__('Type')" wire:model="typeDraft"
                    :options="\App\Domains\Shared\Enums\MeetingTypeEnum::getOptions()" />
            @else
                <p class="text-xs text-base-content/50">
                    {{ __('The type can no longer change — invitations were already sent.') }}
                </p>
            @endif
        </div>
        <x-slot:actions>
            <x-button :label="__('Cancel')" wire:click="$set('showTitleModal', false)" />
            <x-button :label="__('Save')" icon="o-check"
                class="btn-primary" wire:click="saveTitle" spinner="saveTitle" />
        </x-slot:actions>
    </x-app-modal>

    {{-- ── Delete modal ──────────────────────────────────────────────── --}}
    <x-confirm-modal model="showDeleteModal" :title="__('Delete meeting')"
        :confirmLabel="__('Confirm deletion')" confirmAction="deleteMeeting">
        <p>{{ __('This meeting will be permanently deleted. This action is irreversible.') }}</p>
    </x-confirm-modal>
</div>
