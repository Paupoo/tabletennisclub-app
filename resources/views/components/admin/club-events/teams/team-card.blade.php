@props(['team'])

@php
    $colorMap = [
        'Juniors' => [
            'border' => 'border-l-teal-500',
            'bg' => 'bg-teal-50',
            'badge' => 'bg-teal-100 text-teal-800',
            'header_text' => 'text-teal-900',
            'header_sub' => 'text-teal-700',
            'avatar_bg' => 'bg-teal-100',
            'avatar_text' => 'text-teal-700',
        ],
        'Seniors' => [
            'border' => 'border-l-blue-500',
            'bg' => 'bg-blue-50',
            'badge' => 'bg-blue-100 text-blue-800',
            'header_text' => 'text-blue-900',
            'header_sub' => 'text-blue-700',
            'avatar_bg' => 'bg-blue-100',
            'avatar_text' => 'text-blue-700',
        ],
        'Vétérans' => [
            'border' => 'border-l-amber-500',
            'bg' => 'bg-amber-50',
            'badge' => 'bg-amber-100 text-amber-800',
            'header_text' => 'text-amber-900',
            'header_sub' => 'text-amber-700',
            'avatar_bg' => 'bg-amber-100',
            'avatar_text' => 'text-amber-700',
        ],
        'Dames' => [
            'border' => 'border-l-pink-500',
            'bg' => 'bg-pink-50',
            'badge' => 'bg-pink-100 text-pink-800',
            'header_text' => 'text-pink-900',
            'header_sub' => 'text-pink-700',
            'avatar_bg' => 'bg-pink-100',
            'avatar_text' => 'text-pink-700',
        ],
    ];

    $colors = $colorMap[$team->category] ?? [
        'border' => 'border-l-gray-400',
        'bg' => 'bg-gray-50',
        'badge' => 'bg-gray-100 text-gray-700',
        'header_text' => 'text-gray-900',
        'header_sub' => 'text-gray-600',
        'avatar_bg' => 'bg-gray-100',
        'avatar_text' => 'text-gray-600',
    ];

    $initials = collect(explode(' ', $team->captain_name))
        ->map(fn($w) => strtoupper(substr($w, 0, 1)))
        ->take(2)
        ->join('');
@endphp

<div class="overflow-hidden rounded-xl border border-gray-200 bg-white">

    {{-- MOBILE --}}
    <div class="sm:hidden">
        <div class="{{ $colors['border'] }} border-l-4 px-4 pb-3 pt-4">

            <div class="mb-3 flex items-start justify-between">
                <div>
                    <p class="text-[15px] font-medium leading-tight text-gray-900">
                        {{ $team->name }}
                    </p>
                </div>
                <div class="flex flex-col items-end gap-1">
                    <span class="text-[11px] uppercase tracking-wide text-gray-400">
                        {{ $team->division }}
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <p class="mb-0.5 text-[11px] uppercase tracking-wide text-gray-400">
                        {{ __('Ranking') }}
                    </p>
                    <p class="text-[22px] font-medium leading-none text-gray-900">
                        {{ $team->rank }}<span class="relative -top-1 align-top text-[13px] text-gray-400">e</span>
                    </p>
                </div>

                <div>
                    <p class="mb-1 text-[11px] uppercase tracking-wide text-gray-400">
                        {{ __('Captain') }}
                    </p>
                    <div class="flex items-center gap-1.5">
                        <span class="truncate text-[13px] font-medium text-gray-800">
                            {{ $team->captain_name }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <p class="mb-0.5 text-[11px] uppercase tracking-wide text-gray-400">
                    {{ __('Next match') }}
                </p>
                <p class="text-[13px] font-medium text-gray-900">
                    {{ \Carbon\Carbon::parse($team->nextMatchDate)->translatedFormat('D d M') }}
                </p>
            </div>
        </div>

        <div class="flex items-center justify-between border-t border-gray-100 bg-gray-50 px-2 py-1.5">
            <div class="flex">
                <a class="rounded-lg px-3 py-1.5 text-[12px] text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-900"
                    href="#">
                    {{ __('View') }}
                </a>
                <a class="rounded-lg px-3 py-1.5 text-[12px] text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-900"
                    href="#">
                    {{ __('Edit') }}
                </a>
                <a class="rounded-lg px-3 py-1.5 text-[12px] text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-900"
                    href="#">
                    {{ __('Contact') }}
                </a>
            </div>

            <button
                class="rounded-lg px-3 py-1.5 text-[12px] text-red-500 transition-colors hover:bg-red-50 hover:text-red-700"
                onclick="confirm('{{ __('Delete this team?') }}') && this.closest('form')?.submit()">
                {{ __('Delete') }}
            </button>
        </div>
    </div>

    {{-- DESKTOP --}}
    <div class="hidden sm:block">

        <div class="{{ $colors['bg'] }} px-4 py-3">
            <div class="flex items-center justify-between">
                <div>
                    <p class="{{ $colors['header_text'] }} text-[15px] font-medium leading-tight">
                        {{ $team->name }}
                    </p>
                </div>
                <p class="{{ $colors['header_text'] }} text-[28px] font-medium leading-none">
                    {{ $team->rank }}<span
                        class="{{ $colors['header_sub'] }} relative -top-1 align-top text-[14px]">e</span>
                </p>
            </div>
            <p class="{{ $colors['header_sub'] }} mt-1 text-[11px] uppercase tracking-wide">
                {{ $team->division }}
            </p>
        </div>

        <div class="space-y-3 px-4 py-3">

            <div class="flex items-center justify-between">
                <p class="text-xs uppercase tracking-wide text-gray-400">
                    {{ __('Captain') }}
                </p>
                <p class="text-sm font-semibold text-gray-900">
                    {{ $team->captain_name }}
                </p>
            </div>

            <div class="flex items-center justify-between">
                <span class="text-xs uppercase tracking-wide text-gray-400">
                    {{ __('Next match') }}
                </span>
                <span class="text-sm font-medium text-gray-700">
                    {{ \Carbon\Carbon::parse($team->nextMatchDate)->translatedFormat('D d M') }}
                </span>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-1">

                <x-button class="btn-ghost btn-xs text-gray-500 transition hover:text-gray-900" href="#"
                    label="{{ __('Details') }}">
                    <x-heroicon-o-pencil class="h-4 w-4" />
                </x-button>
                <x-button class="btn-ghost btn-xs text-gray-500 transition hover:text-gray-900" href="#"
                    label="{{ __('Edit') }}">
                    <x-heroicon-o-pencil class="h-4 w-4" />
                </x-button>

                <x-button class="btn-ghost btn-xs text-gray-500 transition hover:text-gray-900" href="#"
                    label="{{ __('Contact') }}">
                    <x-heroicon-o-envelope class="h-4 w-4" />
                </x-button>

                <x-button class="text-error btn-ghost btn-xs text-[12px] font-semibold transition hover:opacity-80"
                    label="{{ __('Delete') }}"
                    onclick="confirm('{{ __('Delete this team?') }}') && this.closest('form')?.submit()">
                    <x-heroicon-o-trash class="h-4 w-4" />
                </x-button>
            </div>

        </div>

    </div>
</div>