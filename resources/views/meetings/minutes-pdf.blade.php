{{--
    Published minutes, A4 — rendered by App\Domains\Meetings\Pdf\MinutesPdf.

    mPDF reads plain CSS (no custom property, no flex) and lays out with tables.
    A sober document that prints well in black and white: statuses are written
    out, colour only repeats them.
--}}
@php
    $meeting = $report->meeting;
    $minutes = $report->minutes;
    $clubName = $club?->name ?? config('app.name');
    $clubName = str_contains(mb_strtoupper($clubName), 'ASBL') ? $clubName : $clubName . ' ASBL';
    $address = $club ? trim(collect([$club->street, trim(($club->city_code ?? '') . ' ' . ($club->city_name ?? ''))])->filter()->implode(', ')) : '';
@endphp
<style>
    body { font-family: dejavusans, sans-serif; font-size: 9.5pt; color: #1f1f1f; line-height: 1.45; }
    .club { width: 100%; border-bottom: 1.5px solid #1e40af; padding-bottom: 3mm; margin-bottom: 6mm; }
    .club td { vertical-align: middle; }
    .club-name { font-size: 12pt; font-weight: bold; color: #1e40af; }
    .club-meta { font-size: 8pt; color: #5f5e5a; }
    .eyebrow { font-size: 8pt; font-weight: bold; letter-spacing: 1.5pt; color: #1e40af; text-transform: uppercase; }
    h1 { font-size: 17pt; margin: 1mm 0 1.5mm; color: #1f1f1f; }
    .meta { font-size: 9pt; color: #5f5e5a; margin-bottom: 5mm; }
    table.tiles { width: 100%; border-collapse: separate; border-spacing: 2mm 0; margin: 0 -2mm 6mm; }
    table.tiles td { width: 25%; border: 0.6px solid #d6d3d1; padding: 2.5mm 3mm; vertical-align: top; }
    .tile-label { font-size: 7pt; font-weight: bold; letter-spacing: 0.8pt; color: #5f5e5a; text-transform: uppercase; }
    .tile-value { font-size: 16pt; font-weight: bold; margin-top: 0.5mm; }
    .tile-hint { font-size: 7.5pt; color: #5f5e5a; }
    h2 { font-size: 11pt; color: #1e40af; text-transform: uppercase; letter-spacing: 1pt; margin: 6mm 0 2mm; padding-bottom: 1mm; border-bottom: 0.6px solid #d6d3d1; }
    table.rows { width: 100%; border-collapse: collapse; }
    table.rows td, table.rows th { padding: 2mm 1.5mm; vertical-align: top; border-bottom: 0.4px solid #e7e5e4; }
    table.rows th { text-align: left; font-size: 7.5pt; color: #5f5e5a; text-transform: uppercase; letter-spacing: 0.5pt; border-bottom: 1px solid #1e40af; }
    .ref { width: 11mm; font-weight: bold; color: #1e40af; }
    .num { width: 7mm; font-weight: bold; color: #5f5e5a; }
    .status { font-size: 8pt; font-weight: bold; white-space: nowrap; }
    .overdue { color: #b91c1c; }
    .todo { color: #92400e; }
    .done { color: #166534; }
    .title { font-weight: bold; }
    .details { color: #44403c; font-size: 9pt; }
    .details p, .content p { margin: 0 0 1.5mm; }
    .content ul, .content ol, .details ul, .details ol { margin: 0 0 1.5mm 4mm; padding: 0; }
    .muted { color: #5f5e5a; }
    .empty { color: #5f5e5a; font-style: italic; }
    img { max-width: 100%; }
    a { color: #1e40af; }
</style>

{{-- ── Club ─────────────────────────────────────────────────────────── --}}
<table class="club">
    <tr>
        @if ($logo)
            <td style="width: 16mm;"><img src="{{ $logo }}" style="width: 13mm; height: 13mm;" alt=""></td>
        @endif
        <td>
            <div class="club-name">{{ $clubName }}</div>
            <div class="club-meta">
                {{ $address }}@if ($address !== '' && filled($club?->enterprise_number)) · @endif
                @if (filled($club?->enterprise_number)) {{ __('Enterprise no.') }} {{ $club->enterprise_number }} @endif
            </div>
        </td>
    </tr>
</table>

{{-- ── Title ────────────────────────────────────────────────────────── --}}
<div class="eyebrow">{{ __('Minutes') }} · {{ $meeting->type->getLabel() }}</div>
<h1>{{ $meeting->title }}</h1>
<div class="meta">
    @if ($meeting->scheduled_at){{ ucfirst($meeting->scheduled_at->translatedFormat('l j F Y · H:i')) }}@endif
    @if (filled($meeting->location)) · {{ $meeting->location }}@endif
    @if ($minutes->publisher) · {{ __('Written by :name', ['name' => $minutes->publisher->full_name]) }}@endif
</div>

{{-- ── At a glance ──────────────────────────────────────────────────── --}}
<table class="tiles">
    <tr>
        <td>
            <div class="tile-label">{{ __('Decisions') }}</div>
            <div class="tile-value">{{ count($report->decisions) }}</div>
        </td>
        <td>
            <div class="tile-label">{{ __('Actions') }}</div>
            <div class="tile-value">{{ $report->actions->count() }}</div>
            @if ($report->overdueCount() > 0)
                <div class="tile-hint overdue">{{ trans_choice('{1}1 overdue|[2,*]:count overdue', $report->overdueCount(), ['count' => $report->overdueCount()]) }}</div>
            @endif
        </td>
        <td>
            <div class="tile-label">{{ $report->attendanceRecorded ? __('People present') : __('People confirmed') }}</div>
            <div class="tile-value">{{ $report->present->count() }}</div>
            <div class="tile-hint">{{ trans_choice('{0}nobody excused|{1}1 excused|[2,*]:count excused', $report->excused->count(), ['count' => $report->excused->count()]) }}</div>
        </td>
        <td>
            @if ($meeting->quorum !== null)
                <div class="tile-label">{{ __('Quorum') }}</div>
                <div class="tile-value {{ $report->quorumReached() ? 'done' : 'todo' }}" style="font-size: 12pt;">{{ $report->quorumReached() ? __('Reached') : __('Not reached') }}</div>
                <div class="tile-hint">{{ __(':present of :quorum required', ['present' => $report->present->count(), 'quorum' => $meeting->quorum]) }}</div>
            @elseif ($report->agenda->isNotEmpty())
                <div class="tile-label">{{ __('Agenda') }}</div>
                <div class="tile-value">{{ $report->agenda->whereNotNull('discussed_at')->count() }}/{{ $report->agenda->count() }}</div>
                <div class="tile-hint">{{ __('points discussed') }}</div>
            @endif
        </td>
    </tr>
</table>

{{-- ── Decisions ────────────────────────────────────────────────────── --}}
<h2>{{ __('Decisions') }}</h2>
@if ($report->decisions === [])
    <p class="empty">{{ __('No decision was recorded.') }}</p>
@else
    <table class="rows">
        @foreach ($report->decisions as $i => $decision)
            <tr>
                <td class="ref">D{{ $i + 1 }}</td>
                <td class="content">{!! \App\Support\Markdown::safe($decision) !!}</td>
            </tr>
        @endforeach
    </table>
@endif

{{-- ── Actions ──────────────────────────────────────────────────────── --}}
<h2>{{ __('Action items') }}</h2>
@if ($report->actions->isEmpty())
    <p class="empty">{{ __('No action to follow up.') }}</p>
@else
    <table class="rows">
        <thead>
            <tr>
                <th style="width: 30mm;">{{ __('Status') }}</th>
                <th>{{ __('Action') }}</th>
                <th style="width: 34mm;">{{ __('Assigned to') }}</th>
                <th style="width: 22mm;">{{ __('Due date') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($report->actions as $action)
                <tr>
                    <td class="status {{ $action->status }}">{{ mb_strtoupper($action->label()) }}</td>
                    <td>
                        <div class="title">{{ $action->item->title }}</div>
                        @if (filled($action->item->description))
                            <div class="details">{!! \App\Support\Markdown::safe($action->item->description) !!}</div>
                        @endif
                    </td>
                    <td>{{ $action->item->assignedTo?->full_name ?? __('Nobody') }}</td>
                    <td>{{ $action->item->due_date?->format('d/m/Y') ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- ── Announcements ────────────────────────────────────────────────── --}}
@if ($report->announcements !== [])
    <h2>{{ __('Announcements') }}</h2>
    <table class="rows">
        @foreach ($report->announcements as $announcement)
            <tr>
                <td class="num">•</td>
                <td class="content">{!! \App\Support\Markdown::safe($announcement) !!}</td>
            </tr>
        @endforeach
    </table>
@endif

{{-- ── Agenda ───────────────────────────────────────────────────────── --}}
@if ($report->agenda->isNotEmpty())
    <h2>{{ __('Agenda') }}</h2>
    <table class="rows">
        @foreach ($report->agenda as $i => $item)
            <tr>
                <td class="num">{{ $i + 1 }}.</td>
                <td>
                    <div class="title">{{ $item->title }}</div>
                    @if (filled($item->description))
                        <div class="details">{!! \App\Support\Markdown::safe($item->description) !!}</div>
                    @endif
                </td>
                <td class="status {{ $item->discussed_at ? 'done' : 'muted' }}" style="width: 26mm; text-align: right;">
                    {{ $item->discussed_at ? __('Discussed') : __('Not discussed') }}
                </td>
            </tr>
        @endforeach
    </table>
@endif

{{-- ── Notes ────────────────────────────────────────────────────────── --}}
@if ($report->notes)
    <h2>{{ __('Additional notes') }}</h2>
    <div class="content">{!! \App\Support\Markdown::safe($report->notes) !!}</div>
@endif

{{-- ── Attendance ───────────────────────────────────────────────────── --}}
<h2>{{ __('Attendance list') }}</h2>
@if ($report->meeting->users->isEmpty())
    <p class="empty">{{ __('No attendance recorded.') }}</p>
@elseif (! $report->attendanceRecorded)
    <p class="muted">{{ __('Attendance was not recorded: these are the members who confirmed they would come.') }}</p>
@endif
@foreach ([
    ['label' => $report->attendanceRecorded ? __('People present') : __('People confirmed'), 'people' => $report->present],
    ['label' => __('People excused'), 'people' => $report->excused],
    ['label' => __('People absent'), 'people' => $report->absent],
] as $group)
    @if ($group['people']->isNotEmpty())
        <p><strong>{{ $group['label'] }} ({{ $group['people']->count() }})</strong> — {{ $group['people']->map->full_name->implode(', ') }}</p>
    @endif
@endforeach
@if ($report->isAssembly() && $report->absentCount > 0)
    <p class="muted">{{ trans_choice('{1}1 member absent.|[2,*]:count members absent.', $report->absentCount, ['count' => $report->absentCount]) }}</p>
@endif
