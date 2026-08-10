@props(['team'])

@php
    $colorMap = [
        'Hommes'   => ['bg' => 'bg-blue-50  dark:bg-blue-900/40',  'text' => 'text-blue-700  dark:text-blue-300',  'dot' => 'bg-blue-100  dark:bg-blue-800/60'],
        'Vétérans' => ['bg' => 'bg-amber-50 dark:bg-amber-900/40', 'text' => 'text-amber-700 dark:text-amber-300', 'dot' => 'bg-amber-100 dark:bg-amber-800/60'],
        'Dames'    => ['bg' => 'bg-pink-50  dark:bg-pink-900/40',  'text' => 'text-pink-700  dark:text-pink-300',  'dot' => 'bg-pink-100  dark:bg-pink-800/60'],
    ];

    $c = $colorMap[$team->category] ?? [
        'bg'   => 'bg-base-200/60',
        'text' => 'text-base-content/70',
        'dot'  => 'bg-base-300',
    ];

    $hasCapitain = $team->captain_name !== '—';
    $showRoute   = route('admin.interclubs.teams.show', $team->id);
    $editRoute   = route('admin.interclubs.teams.edit', $team->id);
@endphp

<div class="flex flex-col overflow-hidden rounded-xl border border-base-200 bg-base-100">

    {{-- Colored header --}}
    <div class="flex items-center gap-3 {{ $c['bg'] }} px-4 py-3">
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ $c['dot'] }}">
            <span class="{{ $c['text'] }} text-xl font-bold leading-none">{{ $team->teamLetter }}</span>
        </div>
        <div class="min-w-0 flex-1">
            <p class="{{ $c['text'] }} truncate text-sm font-semibold">{{ $team->name }}</p>
            <p class="{{ $c['text'] }} mt-0.5 truncate text-xs opacity-70">{{ $team->division }}</p>
        </div>
    </div>

    {{-- Body --}}
    <div class="flex-1 divide-y divide-base-200 px-4">

        <div class="flex items-center justify-between gap-4 py-2.5">
            <span class="shrink-0 text-xs font-medium uppercase tracking-wide text-base-content/40">Capitaine</span>
            @if ($hasCapitain)
                <span class="truncate text-sm font-medium text-base-content">{{ $team->captain_name }}</span>
            @else
                <span class="text-xs italic text-base-content/25">{{ __('Not defined') }}</span>
            @endif
        </div>

        <div class="flex items-center justify-between gap-4 py-2.5">
            <span class="shrink-0 text-xs font-medium uppercase tracking-wide text-base-content/40">Noyau</span>
            @if ($team->membersCount > 0)
                <span class="text-sm font-medium text-base-content">
                    {{ $team->membersCount }} joueur{{ $team->membersCount > 1 ? 's' : '' }}
                </span>
            @else
                <span class="text-xs italic text-base-content/25">Aucun joueur</span>
            @endif
        </div>

        <div class="flex items-center justify-between gap-4 py-2.5">
            <span class="shrink-0 text-xs font-medium uppercase tracking-wide text-base-content/40">Prochain match</span>
            @if ($team->nextMatchDate)
                <span class="text-sm font-medium text-base-content">
                    {{ \Carbon\Carbon::parse($team->nextMatchDate)->translatedFormat('D d M') }}
                </span>
            @else
                <span class="text-xs italic text-base-content/25">—</span>
            @endif
        </div>

    </div>

    {{-- Actions --}}
    <div class="flex items-center justify-between border-t border-base-200 bg-base-200/40 px-3 py-2">
        <div class="flex gap-0.5">
            <x-button class="btn-ghost btn-xs text-base-content/50 hover:text-base-content" icon="o-eye" :label="__('Details')"
                link="{{ $showRoute }}" />
            @if (auth()->user()->can('teams.manage'))
                <x-button class="btn-ghost btn-xs text-base-content/50 hover:text-base-content" icon="o-pencil" label="Modifier"
                    link="{{ $editRoute }}" />
            @endif
        </div>
        @if (auth()->user()->can('teams.manage'))
            <x-button class="btn-ghost btn-xs text-error hover:opacity-80" icon="o-trash"
                wire:click="confirmDelete({{ $team->id }})" />
        @endif
    </div>

</div>
