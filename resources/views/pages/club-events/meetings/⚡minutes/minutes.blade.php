<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    @php
        $meeting = $this->meeting;
        $minutes = $meeting->minutes;
    @endphp

    <x-header :title="__('Minutes')" :subtitle="$meeting->title" separator progress-indicator>
        <x-slot:actions>
            <div class="flex items-center gap-3">
                @if ($savedAt)
                    <span class="flex items-center gap-1 text-xs text-base-content/40">
                        <x-icon name="o-check" class="h-3.5 w-3.5" />
                        {{ __('Saved at :time', ['time' => $savedAt]) }}
                    </span>
                @endif
                <x-button :label="__('Back to meeting')" icon="o-arrow-left"
                    class="btn-ghost btn-sm"
                    link="{{ route('admin.meetings.show', $meeting) }}" />
            </div>
        </x-slot:actions>
    </x-header>

    {{-- Everyone polls: viewers pull the latest draft, the holder finds out when the pen changes hands. --}}
    <div class="mx-auto max-w-2xl space-y-6 pb-24" wire:poll.3s="syncDraft">
        @if ($minutes?->is_published)
            <x-alert icon="o-check-circle" class="alert-success alert-soft"
                :title="__('Published on :date', ['date' => $minutes->published_at?->translatedFormat('d M Y')])" />
        @endif

        {{-- ── Note-taking lock ──────────────────────────────────────── --}}
        @if ($this->holdsLock)
            <div class="flex items-center gap-2 rounded-xl border border-primary/30 bg-primary/5 px-4 py-2.5 text-sm">
                <x-icon name="o-pencil" class="h-4 w-4 text-primary" />
                {{ __('You are taking the notes') }}
            </div>
        @elseif ($this->lockHolder)
            <div class="flex flex-col gap-3 rounded-xl border border-base-300 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2 text-sm">
                    <x-icon name="o-pencil" class="h-4 w-4 text-base-content/50" />
                    <span class="font-medium">{{ __(':name is taking notes', ['name' => $this->lockHolder->full_name]) }}</span>
                    <span class="text-base-content/50">— {{ __('you are in read-only mode') }}</span>
                </div>
                <x-button icon="o-hand-raised" :label="__('Take over the notes')"
                    class="btn-outline btn-sm shrink-0"
                    wire:click="takeOver" spinner="takeOver" />
            </div>
        @endif

        {{-- ── Agenda checklist (live) ───────────────────────────────── --}}
        @if ($meeting->agendaItems->isNotEmpty())
            <x-card :title="__('Agenda')" :subtitle="__('Tick each point as the meeting moves along.')">
                <ul class="space-y-1.5">
                    @foreach ($meeting->agendaItems as $i => $item)
                        <li wire:key="agenda-live-{{ $item->id }}">
                            <button type="button"
                                class="flex w-full items-start gap-3 rounded-lg px-2 py-1.5 text-left transition-colors hover:bg-base-200/50 disabled:pointer-events-none"
                                wire:click="toggleDiscussed({{ $item->id }})"
                                @disabled(! $this->holdsLock)>
                                <x-icon :name="$item->discussed_at ? 'o-check-circle' : 'o-minus-circle'"
                                    @class(['mt-0.5 h-5 w-5 shrink-0', 'text-primary' => $item->discussed_at, 'text-base-content/25' => ! $item->discussed_at]) />
                                <span @class(['text-sm', 'text-base-content/40 line-through' => $item->discussed_at])>
                                    {{ $i + 1 }}. {{ $item->title }}
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif

        <fieldset class="contents" @disabled(! $this->holdsLock)>

        {{-- ── 1. Attendance ─────────────────────────────────────────── --}}
        @if ($meeting->users->isNotEmpty())
            <x-card :title="__('Attendance')" :subtitle="__('Check who was actually there.')">
                <div class="space-y-2">
                    @foreach ($meeting->users->sortBy('last_name') as $user)
                        @php $reg = $user->registration; @endphp
                        <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-base-200 px-4 py-2.5"
                            wire:key="att-{{ $user->id }}">
                            <p class="text-sm font-medium">{{ $user->full_name }}</p>
                            <div class="flex items-center gap-2">
                                <x-badge :value="$reg->status->getLabel()" class="{{ $reg->status->getBadgeClass() }} badge-sm" />
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
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-card>
        @endif

        {{-- ── 2. Announcements ──────────────────────────────────────── --}}
        <x-card :title="__('Announcements')">
            <div class="space-y-2">
                @foreach ($announcements as $i => $ann)
                    <div class="flex items-start gap-2" wire:key="ann-{{ $i }}">
                        <x-textarea wire:model.blur="announcements.{{ $i }}"
                            :placeholder="__('Announcement :n', ['n' => $i + 1])"
                            rows="2" class="flex-1" />
                        <x-button icon="o-trash" class="btn-ghost btn-xs btn-circle mt-2"
                            wire:click="removeAnnouncement({{ $i }})" />
                    </div>
                @endforeach
                <x-button icon="o-plus" :label="__('Add announcement')"
                    class="btn-ghost btn-sm" wire:click="addAnnouncement" />
            </div>
        </x-card>

        {{-- ── 3. Decisions ──────────────────────────────────────────── --}}
        <x-card :title="__('Decisions')">
            <div class="space-y-2">
                @foreach ($decisions as $i => $dec)
                    <div class="flex items-start gap-2" wire:key="dec-{{ $i }}">
                        <x-textarea wire:model.blur="decisions.{{ $i }}"
                            :placeholder="__('Decision :n', ['n' => $i + 1])"
                            rows="2" class="flex-1" />
                        <x-button icon="o-trash" class="btn-ghost btn-xs btn-circle mt-2"
                            wire:click="removeDecision({{ $i }})" />
                    </div>
                @endforeach
                <x-button icon="o-plus" :label="__('Add decision')"
                    class="btn-ghost btn-sm" wire:click="addDecision" />
            </div>
        </x-card>

        {{-- ── 4. Action items ───────────────────────────────────────── --}}
        <x-card :title="__('Action items')" :subtitle="__('Tasks that need follow-up after the meeting.')">
            <div class="space-y-3">
                @foreach ($actionItems as $i => $item)
                    <div class="rounded-xl border border-base-200 p-4" wire:key="action-{{ $i }}">
                        <div class="flex items-start gap-2">
                            <x-checkbox wire:model.live="actionItems.{{ $i }}.is_completed" class="mt-1" />
                            <div class="flex-1 space-y-2">
                                <x-input wire:model.blur="actionItems.{{ $i }}.title"
                                    :placeholder="__('Action to take…')"
                                    @class(['line-through opacity-60' => $item['is_completed']]) />
                                <x-textarea wire:model.blur="actionItems.{{ $i }}.description"
                                    rows="2" :placeholder="__('Details…')" />
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    {{-- .live: discrete pickers lose focus on selection — a .blur value
                                         still pending when the poll ticks would be wiped by the morph. --}}
                                    <x-select :label="__('Assigned to')"
                                        :options="$this->usersForAssignment"
                                        wire:model.live="actionItems.{{ $i }}.assigned_to_id"
                                        :placeholder="__('Nobody')" />
                                    <x-datepicker :label="__('Due date')"
                                        wire:model.live="actionItems.{{ $i }}.due_date" />
                                </div>
                            </div>
                            <x-button icon="o-trash" class="btn-ghost btn-xs btn-circle mt-1"
                                wire:click="removeActionItem({{ $i }})" />
                        </div>
                    </div>
                @endforeach
                <x-button icon="o-plus" :label="__('Add action item')"
                    class="btn-ghost btn-sm" wire:click="addActionItem" />
            </div>
        </x-card>

        {{-- ── 5. Notes ──────────────────────────────────────────────── --}}
        <x-card :title="__('Additional notes')">
            <x-textarea wire:model.blur="notes" rows="5"
                :placeholder="__('Free-form notes, observations…')" />
        </x-card>

        </fieldset>

        {{-- ── Publish / send ────────────────────────────────────────── --}}
        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-base-200 pt-4">
            @if (! $meeting->scheduled_at?->isPast())
                <p class="text-sm italic text-base-content/50">
                    {{ __('This meeting has not taken place yet — publish once it is over') }}
                </p>
            @elseif (! $minutes?->is_published)
                <x-button icon="o-eye" :label="__('Publish minutes')"
                    class="btn-primary" wire:click="publishMinutes" spinner="publishMinutes"
                    :disabled="! $this->holdsLock" />
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
    </div>
</div>
