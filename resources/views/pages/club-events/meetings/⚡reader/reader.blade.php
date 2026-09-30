<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    @php
        $report = $this->report;
        $meeting = $report->meeting;
        $minutes = $report->minutes;
        $myActions = $report->myActions();
        $canToggle = fn (\App\Domains\Meetings\Services\MinutesAction $action): bool => $this->canManage || $action->mine;
        $proseClasses = 'prose prose-sm max-w-none text-base-content [&>:first-child]:mt-0 [&>:last-child]:mb-0';
    @endphp

    <div class="mx-auto max-w-4xl space-y-6">
        @unless ($minutes->is_published)
            <x-alert icon="o-eye" class="alert-warning alert-soft"
                :title="__('Draft — not published')"
                :description="__('Only you and the other meeting managers can see this preview.')" />
        @endunless

        {{-- ── Header ──────────────────────────────────────────────────── --}}
        <header class="rounded-xl border border-base-300 bg-base-100 p-5 sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-bold uppercase tracking-widest text-primary">{{ __('Minutes') }}</span>
                        <span @class([
                            'badge badge-soft badge-sm font-semibold',
                            'badge-primary' => $report->isAssembly(),
                            'badge-neutral' => ! $report->isAssembly(),
                        ])>{{ $meeting->type->getLabel() }}</span>
                    </div>
                    <h1 class="text-2xl font-bold text-base-content sm:text-3xl">{{ $meeting->title }}</h1>

                    <dl class="flex flex-wrap gap-x-5 gap-y-1 text-sm text-muted">
                        @if ($meeting->scheduled_at)
                            <div class="flex items-center gap-1.5">
                                <dt class="sr-only">{{ __('Date') }}</dt>
                                <x-icon name="o-calendar-days" class="h-4 w-4" />
                                <dd>{{ ucfirst($meeting->scheduled_at->translatedFormat('l j F Y · H:i')) }}</dd>
                            </div>
                        @endif
                        @if (filled($meeting->location))
                            <div class="flex items-center gap-1.5">
                                <dt class="sr-only">{{ __('Location') }}</dt>
                                <x-icon name="o-map-pin" class="h-4 w-4" />
                                <dd>{{ $meeting->location }}</dd>
                            </div>
                        @endif
                        <div class="flex items-center gap-1.5">
                            <dt class="sr-only">{{ __('Published') }}</dt>
                            <x-icon name="o-pencil-square" class="h-4 w-4" />
                            <dd>
                                @if ($minutes->is_published)
                                    {{ $minutes->publisher
                                        ? __('Published on :date by :name', ['date' => $minutes->published_at?->translatedFormat('j M Y'), 'name' => $minutes->publisher->full_name])
                                        : __('Published on :date', ['date' => $minutes->published_at?->translatedFormat('j M Y')]) }}
                                    @if ($minutes->corrected_at)
                                        · <span class="font-semibold text-base-content">{{ __('Corrected on :date', ['date' => $minutes->corrected_at->translatedFormat('j M Y')]) }}</span>
                                    @endif
                                @else
                                    {{ __('Draft') }}
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="flex shrink-0 flex-wrap gap-2">
                    <x-button icon="o-arrow-down-tray" :label="__('Download PDF')" class="btn-outline btn-sm"
                        :link="route('meetings.minutes.pdf', $meeting)" no-wire-navigate external />
                    @if ($this->canManage)
                        <x-button icon="o-pencil" :label="__('Edit')" class="btn-ghost btn-sm"
                            :link="route('admin.meetings.minutes', $meeting)" />
                    @endif
                </div>
            </div>
        </header>

        {{-- ── At a glance ─────────────────────────────────────────────── --}}
        <section aria-label="{{ __('At a glance') }}" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-admin.shared.stat-card :label="__('Decisions')" :value="$report->decisions->count()"
                icon="o-check-badge" color="primary" />
            <x-admin.shared.stat-card :label="__('Actions')" :value="$report->actions->count()"
                icon="o-clipboard-document-check" :color="$report->overdueCount() > 0 ? 'error' : 'neutral'"
                :hint="$report->overdueCount() > 0
                    ? trans_choice('{1}1 overdue|[2,*]:count overdue', $report->overdueCount(), ['count' => $report->overdueCount()])
                    : trans_choice('{0}none left to do|{1}1 left to do|[2,*]:count left to do', $report->actions->filter(fn ($a) => $a->status === 'todo')->count(), ['count' => $report->actions->filter(fn ($a) => $a->status === 'todo')->count()])" />
            <x-admin.shared.stat-card :label="$report->attendanceRecorded ? __('People present') : __('People confirmed')"
                :value="$report->present->count()" icon="o-user-group"
                :hint="trans_choice('{0}nobody excused|{1}1 excused|[2,*]:count excused', $report->excused->count(), ['count' => $report->excused->count()])" />
            @if ($meeting->quorum !== null)
                <x-admin.shared.stat-card :label="__('Quorum')" :value="$report->quorumReached() ? __('Reached') : __('Not reached')"
                    icon="o-scale" :color="$report->quorumReached() ? 'success' : 'warning'"
                    :hint="__(':present of :quorum required', ['present' => $report->present->count(), 'quorum' => $meeting->quorum])" />
            @endif
        </section>

        {{-- ── Your actions ────────────────────────────────────────────── --}}
        @if ($myActions->isNotEmpty())
            <section data-minutes-section="mine" class="rounded-xl border border-primary/30 bg-primary/5 p-5">
                <h2 class="mb-3 flex items-center gap-2 text-lg font-bold text-base-content">
                    <x-icon name="o-hand-raised" class="h-5 w-5 text-primary" />
                    {{ trans_choice('{1}Your action|[2,*]Your actions (:count)', $myActions->count(), ['count' => $myActions->count()]) }}
                </h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($myActions as $action)
                        <x-admin.club-events.meetings.minutes-action :action="$action" :can-toggle="true"
                            :point="$report->agenda->isNotEmpty() ? $report->pointLabel($action->item->agendaItem) : null" />
                    @endforeach
                </div>
            </section>
        @endif

        {{-- ── Decisions ───────────────────────────────────────────────── --}}
        <x-card :title="__('Decisions')" data-minutes-section="decisions" class="shadow-sm">
            @forelse ($report->decisions as $decision)
                <div class="flex gap-3 border-base-300 py-3 first:pt-0 last:pb-0 [&:not(:last-child)]:border-b" wire:key="decision-{{ $decision->id }}">
                    <span class="flex h-7 min-w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-xs font-black text-primary">{{ $report->decisionNumber($decision) }}</span>
                    <div class="min-w-0 flex-1 pt-0.5">
                        <div class="{{ $proseClasses }}">{!! \App\Support\Markdown::safe($decision->body) !!}</div>
                        @if ($report->agenda->isNotEmpty())
                            <p class="mt-1 text-xs text-muted">{{ $report->pointLabel($decision->agendaItem) }}</p>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-sm italic text-muted">{{ __('No decision was recorded.') }}</p>
            @endforelse
        </x-card>

        {{-- ── Actions ─────────────────────────────────────────────────── --}}
        <x-card :title="__('Action items')" :subtitle="__('Most urgent first.')" data-minutes-section="actions" class="shadow-sm">
            @if ($report->actions->isEmpty())
                <p class="text-sm italic text-muted">{{ __('No action to follow up.') }}</p>
            @else
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($report->actions as $action)
                        <x-admin.club-events.meetings.minutes-action :action="$action" :can-toggle="$canToggle($action)"
                            :point="$report->agenda->isNotEmpty() ? $report->pointLabel($action->item->agendaItem) : null" />
                    @endforeach
                </div>
            @endif
        </x-card>

        {{-- ── Announcements ───────────────────────────────────────────── --}}
        @if ($report->announcements !== [])
            <x-card :title="__('Announcements')" data-minutes-section="announcements" class="shadow-sm">
                <ul class="space-y-3">
                    @foreach ($report->announcements as $announcement)
                        <li class="flex gap-3">
                            <x-icon name="o-megaphone" class="mt-0.5 h-5 w-5 shrink-0 text-muted" />
                            <div class="{{ $proseClasses }}">{!! \App\Support\Markdown::safe($announcement) !!}</div>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif

        {{-- ── Agenda: what was discussed ─────────────────────────────── --}}
        @if ($report->agenda->isNotEmpty())
            <x-card :title="__('Agenda')" data-minutes-section="agenda" class="shadow-sm">
                <ol class="space-y-3">
                    @foreach ($report->agenda as $i => $item)
                        <li class="flex gap-3">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-base-200 text-xs font-bold text-muted">{{ $i + 1 }}</span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold text-base-content">{{ $item->title }}</p>
                                    @if ($item->discussed_at)
                                        <span class="badge badge-soft badge-success badge-sm">
                                            <x-icon name="o-check" class="h-3 w-3" /> {{ __('Discussed') }}
                                        </span>
                                    @else
                                        <span class="badge badge-outline badge-sm border-base-300 text-muted">{{ __('Not discussed') }}</span>
                                    @endif
                                </div>
                                @if (filled($item->discussion))
                                    <div class="{{ $proseClasses }} mt-1">{!! \App\Support\Markdown::safe($item->discussion) !!}</div>
                                @elseif (filled($item->description))
                                    <div class="{{ $proseClasses }} mt-1 text-muted">{!! \App\Support\Markdown::safe($item->description) !!}</div>
                                @endif
                                @php
                                    $pointDecisions = $report->decisionsFor($item);
                                    $pointActions = $report->actionsFor($item)->count();
                                @endphp
                                @if ($pointDecisions->isNotEmpty() || $pointActions > 0)
                                    <p class="mt-1.5 flex flex-wrap items-center gap-1.5 text-xs text-muted">
                                        <x-icon name="o-arrow-turn-down-right" class="h-3.5 w-3.5" />
                                        @foreach ($pointDecisions as $decision)
                                            <span class="rounded bg-primary/10 px-1.5 font-bold text-primary">{{ $report->decisionNumber($decision) }}</span>
                                        @endforeach
                                        @if ($pointActions > 0)
                                            <span>{{ trans_choice('{1}1 action|[2,*]:count actions', $pointActions, ['count' => $pointActions]) }}</span>
                                        @endif
                                    </p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </x-card>
        @endif

        {{-- ── Notes ───────────────────────────────────────────────────── --}}
        @if ($report->notes)
            <x-card :title="__('Outside the agenda')" data-minutes-section="notes" class="shadow-sm">
                <div class="{{ $proseClasses }}">{!! \App\Support\Markdown::safe($report->notes) !!}</div>
            </x-card>
        @endif

        {{-- ── Attendance: folded, a general assembly's list is long ───── --}}
        <details data-minutes-section="attendance" class="group rounded-xl border border-base-300 bg-base-100 shadow-sm">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 p-5">
                <span class="text-lg font-bold text-base-content">{{ __('Attendance list') }}</span>
                <span class="flex items-center gap-2 text-sm text-muted">
                    {{ __(':present present · :excused excused · :absent absent', [
                        'present' => $report->present->count(),
                        'excused' => $report->excused->count(),
                        'absent' => $report->absentCount,
                    ]) }}
                    <x-icon name="o-chevron-down" class="h-4 w-4 transition group-open:rotate-180" />
                </span>
            </summary>
            <div class="space-y-4 border-t border-base-300 p-5">
                @if ($meeting->users->isEmpty())
                    <p class="text-sm italic text-muted">{{ __('No attendance recorded.') }}</p>
                @elseif (! $report->attendanceRecorded)
                    <p class="text-sm text-muted">{{ __('Attendance was not recorded: these are the members who confirmed they would come.') }}</p>
                @endif
                @foreach ([
                    ['label' => $report->attendanceRecorded ? __('People present') : __('People confirmed'), 'people' => $report->present],
                    ['label' => __('People excused'), 'people' => $report->excused],
                    ['label' => __('People absent'), 'people' => $report->absent],
                ] as $group)
                    @if ($group['people']->isNotEmpty())
                        <div>
                            <p class="mb-2 text-xs font-bold uppercase tracking-widest text-muted">{{ $group['label'] }} ({{ $group['people']->count() }})</p>
                            <ul class="flex flex-wrap gap-1.5">
                                @foreach ($group['people'] as $person)
                                    <li class="rounded-full border border-base-300 px-2.5 py-0.5 text-sm">{{ $person->full_name }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endforeach
                @if ($report->isAssembly() && $report->absentCount > 0)
                    <p class="text-sm text-muted">{{ trans_choice('{1}1 member absent.|[2,*]:count members absent.', $report->absentCount, ['count' => $report->absentCount]) }}</p>
                @endif
            </div>
        </details>

        <p class="text-center text-xs text-subtle">
            {{ __('Situation on :date', ['date' => $report->asOf->translatedFormat('j F Y · H:i')]) }}
        </p>
    </div>
</div>
