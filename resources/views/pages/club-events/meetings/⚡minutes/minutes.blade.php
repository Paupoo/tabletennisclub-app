<div x-data="{
        openPoint: @js($this->currentPointId),
        navOpen: false,
        go(id) {
            this.openPoint = id;
            this.navOpen = false;
            this.$nextTick(() => document.getElementById('point-' + id)?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
        },
        tick(id) {
            this.$wire.toggleDiscussed(id).then((next) => { if (next !== null) this.go(next); });
        },
    }">
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    @php
        $meeting = $this->meeting;
        $minutes = $meeting->minutes;
        $readOnly = $this->readOnly;
        $current = $this->currentPointId;
        $counts = $this->attendanceCounts;
        $numbers = $meeting->decisions->values()->mapWithKeys(fn ($decision, $i) => [$decision->id => 'D' . ($i + 1)])->all();
        $decisionsOf = fn (?int $itemId) => $meeting->decisions->where('agenda_item_id', $itemId)->values();
        $actionsOf = fn (?int $itemId) => $meeting->actionItems->where('agenda_item_id', $itemId)->values();
        $editorKey = $readOnly ? 'read' : 'write';
        $isAssembly = $meeting->type === \App\Domains\Shared\Enums\MeetingTypeEnum::GENERAL_ASSEMBLY;
        $total = $meeting->agendaItems->count();
        $currentIndex = $meeting->agendaItems->search(fn ($item) => $item->id === $current);
    @endphp

    <x-header :title="__('Minutes')" :subtitle="$meeting->title" separator progress-indicator>
        <x-slot:actions>
            <x-button :label="__('Back to meeting')" icon="o-arrow-left" class="btn-ghost btn-sm"
                link="{{ route('admin.meetings.show', $meeting) }}" />
        </x-slot:actions>
    </x-header>

    {{-- Everyone polls: readers pull the note taker's writes, the holder finds out when the pen changes hands. --}}
    <div wire:poll.3s="syncDraft" class="grid gap-6 pb-24 lg:grid-cols-[17rem_minmax(0,1fr)]">

        {{-- ════════════ Outline (desktop) ════════════ --}}
        <aside class="hidden lg:block">
            <div class="sticky top-4 space-y-4">
                <x-admin.club-events.meetings.minutes-outline :meeting="$meeting" :current="$current" :counts="$counts"
                    :outside="$outside" :read-only="$readOnly" :saved-at="$savedAt" />
            </div>
        </aside>

        {{-- ════════════ Outline (phone) ════════════ --}}
        <div class="sticky top-16 z-20 -mx-1 lg:hidden">
            <button type="button" @click="navOpen = !navOpen" :aria-expanded="navOpen"
                class="flex w-full items-center justify-between gap-2 rounded-xl border border-base-300 bg-base-100/95 px-4 py-2.5 text-sm font-semibold shadow-sm backdrop-blur">
                <span class="truncate">
                    @if ($current !== null)
                        {{ __('Point :n/:total', ['n' => $currentIndex + 1, 'total' => $total]) }} · {{ $meeting->agendaItems[$currentIndex]->title }}
                    @else
                        {{ __('Outline') }}
                    @endif
                </span>
                <x-icon name="o-chevron-down" class="h-4 w-4 shrink-0 transition" x-bind:class="navOpen && 'rotate-180'" />
            </button>
            <div x-show="navOpen" x-cloak x-transition.opacity @click.outside="navOpen = false"
                class="mt-2 max-h-[70vh] overflow-y-auto rounded-xl border border-base-300 bg-base-100 p-4 shadow-lg">
                <x-admin.club-events.meetings.minutes-outline :meeting="$meeting" :current="$current" :counts="$counts"
                    :outside="$outside" :read-only="$readOnly" :saved-at="$savedAt" />
            </div>
        </div>

        {{-- ════════════ Content ════════════ --}}
        <div class="min-w-0 space-y-4">
            @if ($minutes?->is_published)
                <x-alert icon="o-check-circle" class="alert-success alert-soft"
                    :title="$minutes->sent_to_committee_at || $minutes->sent_to_all_at
                        ? __('Published and sent — every change shows to the readers, marked as a correction.')
                        : __('Published — every change shows to the readers.')" />
            @endif

            {{-- ── The pen ───────────────────────────────────────── --}}
            @if ($this->holdsLock)
                <div class="flex items-center gap-2 rounded-xl border border-primary/30 bg-primary/5 px-4 py-2.5 text-sm">
                    <x-icon name="o-pencil" class="h-4 w-4 text-primary" />
                    {{ __('You are taking the notes') }}
                </div>
            @elseif ($readOnly)
                <div class="flex flex-col gap-3 rounded-xl border border-base-300 bg-base-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-2 text-sm">
                        <x-icon name="o-pencil" class="h-4 w-4 text-muted" />
                        <span class="font-medium">{{ __(':name is taking notes', ['name' => $this->lockHolder->full_name]) }}</span>
                        <span class="text-muted">— {{ __('you are in read-only mode') }}</span>
                    </div>
                    <x-button icon="o-hand-raised" :label="__('Take over the notes')" class="btn-outline btn-sm shrink-0"
                        wire:click="takeOver" spinner="takeOver" />
                </div>
            @endif

            {{-- ── Attendance ────────────────────────────────────── --}}
            @if ($meeting->users->isNotEmpty() || $isAssembly)
                <details id="attendance" data-minutes-block="attendance" class="group scroll-mt-20 rounded-xl border border-base-300 bg-base-100"
                    @if ($counts['present'] === 0 && ! $readOnly) open @endif>
                    <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-2 p-4">
                        <span class="flex items-center gap-2 font-bold">
                            <x-icon name="o-user-group" class="h-5 w-5 text-muted" /> {{ __('Attendance list') }}
                        </span>
                        <span class="flex items-center gap-2 text-sm text-muted">
                            {{ __(':present present · :excused excused · :absent absent', ['present' => $counts['present'], 'excused' => $counts['excused'], 'absent' => $counts['absent']]) }}@if ($counts['pending'] > 0) · {{ trans_choice('{1}1 to check in|[2,*]:count to check in', $counts['pending'], ['count' => $counts['pending']]) }}@endif
                            @if ($meeting->quorum !== null)
                                <span @class(['badge badge-soft badge-sm', 'badge-success' => $counts['present'] >= $meeting->quorum, 'badge-warning' => $counts['present'] < $meeting->quorum])>
                                    {{ __('Quorum :present/:quorum', ['present' => $counts['present'], 'quorum' => $meeting->quorum]) }}
                                </span>
                            @endif
                            <x-icon name="o-chevron-down" class="h-4 w-4 transition group-open:rotate-180" />
                        </span>
                    </summary>

                    <div class="space-y-3 border-t border-base-300 p-4">
                        <div class="flex flex-wrap items-center gap-2">
                            @unless ($readOnly)
                                <x-button icon="o-check" :label="__('Mark every confirmed member present')" class="btn-sm btn-primary btn-soft"
                                    wire:click="markAllConfirmedPresent" spinner="markAllConfirmedPresent" />
                            @endunless
                            <label class="input input-sm ms-auto w-full sm:w-56">
                                <x-icon name="o-magnifying-glass" class="h-4 w-4 text-muted" />
                                <input type="search" wire:model.live.debounce.250ms="attendanceSearch" placeholder="{{ __('Search') }}"
                                    aria-label="{{ __('Search an attendee') }}" class="grow">
                            </label>
                        </div>

                        <p class="text-xs text-muted">{{ __('Tap a name: present, then absent, then back to their answer.') }}</p>

                        <ul class="flex flex-wrap gap-1.5">
                            @foreach ($this->attendees as $attendee)
                                @php $status = $attendee->registration->status; @endphp
                                <li wire:key="attendee-{{ $attendee->id }}">
                                    <button type="button" wire:click="cycleAttendance({{ $attendee->id }})" @disabled($readOnly)
                                        @class([
                                            'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm transition',
                                            'border-success/50 bg-success/10 font-semibold' => $status === \App\Domains\Shared\Enums\MeetingUserStatusEnum::ATTENDED,
                                            'border-error/40 bg-error/5 text-muted line-through' => $status === \App\Domains\Shared\Enums\MeetingUserStatusEnum::ABSENT,
                                            'border-base-300 text-muted' => $status === \App\Domains\Shared\Enums\MeetingUserStatusEnum::DECLINED,
                                            'border-base-300' => in_array($status, [\App\Domains\Shared\Enums\MeetingUserStatusEnum::CONFIRMED, \App\Domains\Shared\Enums\MeetingUserStatusEnum::INVITED], true),
                                        ])>
                                        <x-icon :name="match ($status) {
                                            \App\Domains\Shared\Enums\MeetingUserStatusEnum::ATTENDED => 's-check-circle',
                                            \App\Domains\Shared\Enums\MeetingUserStatusEnum::ABSENT => 'o-x-circle',
                                            \App\Domains\Shared\Enums\MeetingUserStatusEnum::DECLINED => 'o-hand-raised',
                                            default => 'o-question-mark-circle',
                                        }" @class(['h-4 w-4', 'text-success' => $status === \App\Domains\Shared\Enums\MeetingUserStatusEnum::ATTENDED]) />
                                        {{ $attendee->full_name }}
                                        <span class="sr-only">— {{ $status->getLabel() }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>

                        @unless ($readOnly)
                            <div class="border-t border-base-300 pt-3">
                                <p class="mb-1.5 text-xs font-bold uppercase tracking-widest text-muted">{{ __('Came without being on the list') }}</p>
                                <label class="input input-sm w-full sm:w-72">
                                    <x-icon name="o-user-plus" class="h-4 w-4 text-muted" />
                                    <input type="search" wire:model.live.debounce.300ms="walkInSearch" placeholder="{{ __('Search an active member') }}"
                                        aria-label="{{ __('Search an active member') }}" class="grow">
                                </label>
                                @if ($this->walkInCandidates->isNotEmpty())
                                    <ul class="mt-2 flex flex-wrap gap-1.5">
                                        @foreach ($this->walkInCandidates as $candidate)
                                            <li wire:key="walk-in-{{ $candidate->id }}">
                                                <button type="button" wire:click="addWalkIn({{ $candidate->id }})"
                                                    class="inline-flex items-center gap-1 rounded-full border border-dashed border-primary/50 px-3 py-1 text-sm text-primary">
                                                    <x-icon name="o-plus" class="h-3.5 w-3.5" /> {{ $candidate->full_name }}
                                                </button>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        @endunless
                    </div>
                </details>
            @endif

            {{-- ── Announcements ─────────────────────────────────── --}}
            <section id="announcements" data-minutes-block="announcements" class="scroll-mt-20 rounded-xl border border-base-300 bg-base-100 p-4">
                <h2 class="mb-3 flex items-center gap-2 font-bold">
                    <x-icon name="o-megaphone" class="h-5 w-5 text-muted" /> {{ __('Announcements') }}
                </h2>
                <div class="space-y-2">
                    @foreach ($announcements as $i => $ann)
                        <div class="flex items-start gap-2" wire:key="ann-{{ $i }}">
                            <x-markdown-editor model="announcements.{{ $i }}" compact commit-on-blur class="flex-1"
                                :label="__('Announcement :n', ['n' => $i + 1])" :sticky-toolbar="false"
                                :editable="! $readOnly" wire:key="ann-editor-{{ $i }}-{{ $editorKey }}" />
                            @unless ($readOnly)
                                <x-button icon="o-trash" class="btn-ghost btn-xs btn-circle mt-7 text-error"
                                    wire:click="removeAnnouncement({{ $i }})" :aria-label="__('Delete')" />
                            @endunless
                        </div>
                    @endforeach
                    @unless ($readOnly)
                        <x-button icon="o-plus" :label="__('Add announcement')" class="btn-ghost btn-sm" wire:click="addAnnouncement" />
                    @endunless
                </div>
            </section>

            {{-- ── One block per agenda point ────────────────────── --}}
            @foreach ($meeting->agendaItems as $i => $item)
                @php
                    $pointDecisions = $decisionsOf($item->id);
                    $pointActions = $actionsOf($item->id);
                @endphp
                <section id="point-{{ $item->id }}" data-minutes-block="point" wire:key="point-{{ $item->id }}"
                    class="scroll-mt-20 rounded-xl border bg-base-100 transition"
                    x-bind:class="openPoint === {{ $item->id }} ? 'border-primary/40 shadow-sm' : 'border-base-300'">
                    <header class="flex items-center gap-3 p-4">
                        <button type="button" @click="openPoint = openPoint === {{ $item->id }} ? null : {{ $item->id }}"
                            class="flex min-w-0 flex-1 items-center gap-3 text-left" x-bind:aria-expanded="openPoint === {{ $item->id }}">
                            <span @class([
                                'flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                                'bg-success/15 text-success' => $item->discussed_at,
                                'bg-primary text-primary-content' => ! $item->discussed_at && $item->id === $current,
                                'bg-base-200 text-muted' => ! $item->discussed_at && $item->id !== $current,
                            ])>
                                @if ($item->discussed_at)
                                    <x-icon name="s-check" class="h-4 w-4" />
                                    <span class="sr-only">{{ $i + 1 }}</span>
                                @else
                                    {{ $i + 1 }}
                                @endif
                            </span>
                            <span class="min-w-0">
                                <span class="line-clamp-2 block font-bold">{{ $item->title }}</span>
                                <span class="block text-xs text-muted">
                                    @if ($item->discussed_at)
                                        {{ __('Discussed') }}
                                    @elseif ($item->id === $current)
                                        <span class="font-semibold text-primary">{{ __('In progress') }}</span>
                                    @else
                                        {{ __('Coming up') }}
                                    @endif
                                    @if ($pointDecisions->isNotEmpty() || $pointActions->isNotEmpty())
                                        · {{ trans_choice('{0}no decision|{1}1 decision|[2,*]:count decisions', $pointDecisions->count(), ['count' => $pointDecisions->count()]) }}
                                        · {{ trans_choice('{0}no action|{1}1 action|[2,*]:count actions', $pointActions->count(), ['count' => $pointActions->count()]) }}
                                    @endif
                                </span>
                            </span>
                        </button>
                        @unless ($readOnly)
                            <button type="button" @click="tick({{ $item->id }})"
                                aria-label="{{ $item->discussed_at ? __('Discussed') : __('Mark discussed') }}"
                                @class(['btn btn-sm shrink-0', 'btn-success btn-soft' => $item->discussed_at, 'btn-outline' => ! $item->discussed_at])>
                                <x-icon name="o-check" class="h-4 w-4" />
                                {{-- A phone keeps the width for the point's title. --}}
                                <span class="hidden sm:inline">{{ $item->discussed_at ? __('Discussed') : __('Mark discussed') }}</span>
                            </button>
                        @endunless
                    </header>

                    <div x-show="openPoint === {{ $item->id }}" x-cloak class="space-y-4 border-t border-base-300 p-4">
                        @if (filled($item->description))
                            <details class="rounded-lg bg-base-200/60 px-3 py-2 text-sm">
                                <summary class="cursor-pointer text-xs font-semibold text-muted">{{ __('Planned details') }}</summary>
                                <div class="prose prose-sm mt-1 max-w-none text-muted [&>:first-child]:mt-0 [&>:last-child]:mb-0">
                                    {!! \App\Support\Markdown::safe($item->description) !!}
                                </div>
                            </details>
                        @endif

                        <x-markdown-editor model="discussions.{{ $item->id }}" compact commit-on-blur
                            :label="__('Discussion')" :sticky-toolbar="false" :editable="! $readOnly"
                            wire:key="discussion-{{ $item->id }}-{{ $editorKey }}" />

                        <x-admin.club-events.meetings.minutes-block :block="(string) $item->id"
                            :decisions="$pointDecisions" :actions="$pointActions" :numbers="$numbers"
                            :assignees="$this->usersForAssignment" :read-only="$readOnly" />
                    </div>
                </section>
            @endforeach

            {{-- ── Outside the agenda ────────────────────────────── --}}
            <section id="point-{{ $outside }}" data-minutes-block="outside" class="scroll-mt-20 space-y-4 rounded-xl border border-base-300 bg-base-100 p-4">
                <h2 class="flex items-center gap-2 font-bold">
                    <x-icon name="o-ellipsis-horizontal-circle" class="h-5 w-5 text-muted" /> {{ __('Outside the agenda') }}
                </h2>
                <x-markdown-editor model="notes" compact commit-on-blur :label="__('Discussion')" :sticky-toolbar="false"
                    :editable="! $readOnly" wire:key="minutes-notes-{{ $editorKey }}" />
                <x-admin.club-events.meetings.minutes-block :block="$outside"
                    :decisions="$decisionsOf(null)" :actions="$actionsOf(null)" :numbers="$numbers"
                    :assignees="$this->usersForAssignment" :read-only="$readOnly" />
            </section>
        </div>
    </div>
</div>
