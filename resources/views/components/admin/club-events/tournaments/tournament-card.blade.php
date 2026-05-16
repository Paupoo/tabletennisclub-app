@props(['tournament'])

@php
    $statusConfig = [
        'pending'   => ['bg' => 'bg-primary/10',  'text' => 'text-primary',          'badge' => 'badge-primary',  'label' => __('Live')],
        'published' => ['bg' => 'bg-success/10',  'text' => 'text-success',          'badge' => 'badge-success',  'label' => __('Published')],
        'setup'     => ['bg' => 'bg-info/10',     'text' => 'text-info',             'badge' => 'badge-info',     'label' => __('Setup')],
        'locked'    => ['bg' => 'bg-warning/10',  'text' => 'text-warning',          'badge' => 'badge-warning',  'label' => __('Locked')],
        'closed'    => ['bg' => 'bg-base-200',    'text' => 'text-base-content/50',  'badge' => 'badge-ghost',    'label' => __('Closed')],
        'cancelled' => ['bg' => 'bg-error/10',    'text' => 'text-error',            'badge' => 'badge-error',    'label' => __('Cancelled')],
        'draft'     => ['bg' => 'bg-base-300',    'text' => 'text-base-content/40',  'badge' => 'badge-outline',  'label' => __('Draft')],
    ];

    $s            = $statusConfig[$tournament->status->value] ?? $statusConfig['draft'];
    $isLive       = $tournament->status === \App\Enums\TournamentStatusEnum::PENDING;
    $activeCount  = $tournament->active_registrations_count ?? $tournament->activeRegistrationsCount();
    $waitingCount = $tournament->waiting_count ?? 0;
    $maxUsers     = $tournament->max_users;
    $percent      = $maxUsers > 0 ? min(100, ($activeCount / $maxUsers) * 100) : 0;

    $formatTags = collect([
        $tournament->sets_to_win > 0  ? trans_choice('{1} :n winning set|[2,*] :n winning sets', $tournament->sets_to_win, ['n' => $tournament->sets_to_win]) : null,
        $tournament->has_handicap_points ? __('Handicap') : null,
        $tournament->deuce_enabled        ? __('Deuce')    : null,
    ])->filter()->all();
@endphp

<div @class([
    'group flex flex-col overflow-hidden rounded-xl border border-base-200 bg-base-100 shadow-sm transition-all hover:shadow-md',
    'ring-2 ring-primary/40' => $isLive,
])>

    {{-- HEADER --}}
    <div class="{{ $s['bg'] }} px-4 py-3">
        <div class="flex items-start justify-between gap-2">

            <div class="min-w-0 flex-1">
                <p class="{{ $s['text'] }} mb-0.5 text-[10px] font-bold uppercase tracking-wider">
                    {{ __('Tournament') }}
                </p>
                <p class="truncate text-[15px] font-semibold text-base-content" title="{{ $tournament->name }}">
                    {{ $tournament->name }}
                </p>
            </div>

            <div class="flex shrink-0 items-center gap-1.5">
                @if ($isLive)
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary opacity-75"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-primary"></span>
                    </span>
                @endif
                <span class="{{ $s['badge'] }} badge badge-sm py-2.5 font-bold uppercase">
                    {{ $s['label'] }}
                </span>
            </div>

        </div>
    </div>

    {{-- BODY --}}
    <div class="flex flex-1 flex-col px-4 py-3">

        {{-- Info rows --}}
        <div class="mb-3 space-y-1.5 text-[13px] text-base-content/70">

            {{-- Date --}}
            <div class="flex items-center gap-2">
                <x-icon class="h-3.5 w-3.5 shrink-0 opacity-50" name="o-calendar" />
                <span>{{ $tournament->start_date->translatedFormat('d M Y · H\hi') }}</span>
            </div>

            {{-- Participants --}}
            <div class="flex items-center gap-2">
                <x-icon class="h-3.5 w-3.5 shrink-0 opacity-50" name="o-users" />
                <span>
                    {{ $activeCount }}
                    @if ($maxUsers > 0)
                        / {{ $maxUsers }}
                    @endif
                </span>
                @if ($waitingCount > 0)
                    <span class="text-[11px] font-medium text-warning">(+{{ $waitingCount }} {{ __('waiting') }})</span>
                @endif
            </div>

            {{-- Price --}}
            <div class="flex items-center gap-2">
                <x-icon class="h-3.5 w-3.5 shrink-0 opacity-50" name="o-banknotes" />
                @if ($tournament->price > 0)
                    <span>{{ number_format((float) $tournament->price, 2, ',', ' ') }} €</span>
                @else
                    <span class="text-base-content/40">{{ __('Free') }}</span>
                @endif
            </div>

        </div>

        {{-- Format tags --}}
        @if (count($formatTags))
            <div class="mb-3 flex flex-wrap gap-1">
                @foreach ($formatTags as $tag)
                    <span class="badge badge-ghost badge-xs text-[10px] uppercase tracking-wide">{{ $tag }}</span>
                @endforeach
            </div>
        @endif

        {{-- Progress bar --}}
        @if ($maxUsers > 0)
            <div class="mb-3">
                <progress
                    class="progress h-1.5 {{ $percent >= 100 ? 'progress-error' : 'progress-primary' }}"
                    max="100"
                    value="{{ $percent }}"
                ></progress>
            </div>
        @endif

        {{-- Spacer --}}
        <div class="flex-1"></div>

        {{-- Actions --}}
        <div class="flex items-center justify-end border-t border-base-200 pt-2">
            <div class="flex items-center gap-1">

                <a
                    class="btn btn-ghost btn-sm btn-square text-base-content/60 hover:text-primary"
                    href="{{ route('admin.tournaments.wizard.edit', $tournament) }}"
                    title="{{ __('Settings') }}"
                >
                    <x-heroicon-o-cog-6-tooth class="h-4 w-4" />
                </a>

                @if ($tournament->status !== \App\Enums\TournamentStatusEnum::CLOSED)
                    <a
                        class="btn btn-ghost btn-sm btn-square {{ $isLive ? 'text-primary hover:bg-primary/10' : 'text-base-content/60 hover:text-primary' }}"
                        href="{{ route('admin.tournaments.live-center', $tournament->id) }}"
                        title="{{ __('Live Center') }}"
                    >
                        <x-heroicon-o-rocket-launch class="h-4 w-4" />
                    </a>
                @endif

            </div>
        </div>

    </div>
</div>
