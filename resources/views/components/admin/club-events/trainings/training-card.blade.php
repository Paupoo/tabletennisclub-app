{{-- resources/views/components/training-card.blade.php --}}
@props(['training'])

@php
    $full = $training['current_spots'] >= $training['max_spots'];
    $percent = ($training['current_spots'] / max(1, $training['max_spots'])) * 100;

    $start = \Carbon\Carbon::parse($training['start_date']);
    $end = $start->copy()->addMinutes($training['duration']);

    $colorMap = [
        'young' => [
            'bg' => 'bg-info/10',
            'border' => 'border-l-info',
            'badge' => 'badge-info',
            'header_text' => 'text-info',
            'header_sub' => 'text-info/80',
        ],
        'starters' => [
            'bg' => 'bg-success/10',
            'badge' => 'badge-success',
            'border' => 'border-l-success',
            'header_text' => 'text-success',
            'header_sub' => 'text-success/80',
        ],
        'advanced' => [
            'bg' => 'bg-warning/10',
            'badge' => 'badge-warning',
            'border' => 'border-l-warning',
            'header_text' => 'text-warning',
            'header_sub' => 'text-warning/80',
        ],
    ];

    $colors = $colorMap[$training['category']] ?? [
        'bg' => 'bg-base-200',
        'badge' => 'badge-ghost',
        'border' => 'border-l-base-content/30',
        'header_text' => 'text-base-content',
        'header_sub' => 'text-base-content/60',
    ];
@endphp

<div class="group overflow-hidden rounded-xl border border-base-200 bg-base-100">

    {{-- HEADER --}}
    <div class="{{ $colors['bg'] }} px-4 py-3">
        <div class="flex items-center justify-between">

            <div>
                <p class="{{ $colors['header_text'] }} text-[15px] font-medium leading-tight">
                    {{ $training['title'] }}
                </p>
            </div>
        </div>
    </div>

    {{-- BODY --}}
    <div class="px-4 py-3">

        {{-- INFOS --}}
        <div class="mb-3 space-y-1.5 text-[13px] text-base-content/80">

            <div class="flex items-center gap-2">
                <x-icon class="h-4 w-4 opacity-50" name="o-clock" />
                {{ $start->format('D H:i') }} – {{ $end->format('H:i') }}
            </div>

            <div class="flex items-center gap-2">
                <x-icon class="h-4 w-4 opacity-50" name="o-user" />
                {{ $training['coach_name'] }}
            </div>

            <div class="flex items-center gap-2">
                <x-icon class="h-4 w-4 opacity-50" name="o-map-pin" />
                {{ $training['location'] }}
            </div>

        </div>

        {{-- PROGRESS --}}
        <div class="mb-3">
            <div class="mb-1 flex items-center justify-between text-[11px] text-base-content/60">
                <span>
                    {{ $training['current_spots'] }} / {{ $training['max_spots'] }}
                </span>

                <span class="{{ $full ? 'text-error' : 'text-success' }} font-medium">
                    {{ $full ? __('Full') : __(':count free', ['count' => $training['max_spots'] - $training['current_spots']]) }}
                </span>
            </div>

            <progress class="progress {{ $full ? 'progress-error' : 'progress-primary' }} h-1" max="100"
                value="{{ $percent }}">
            </progress>
        </div>

        {{-- ACTIONS --}}
        <div class="flex items-center justify-between border-t border-base-200 pt-2">

            {{-- Links (comme mobile) --}}
            <div class="flex flex-wrap gap-3 text-[12px]">
                <x-button class="btn-ghost btn-sm text-xs text-base-content/60 transition hover:text-base-content" href="#">
                    {{ __('Details') }}
                </x-button>

                <x-button class="btn-ghost btn-sm text-xs text-base-content/60 transition hover:text-base-content" href="#">
                    {{ __('Contact') }}
                </x-button>
            </div>

            {{-- CTA --}}
            @if (!$full)
                <x-button class="text-primary btn-ghost btn-sm text-xs font-semibold transition hover:opacity-80">
                    {{ __('Register') }}
                </x-button>
            @endif

        </div>

    </div>
</div>