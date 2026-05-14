@props(['team'])

@php
    $colorMap = [
        'Hommes'   => ['bg' => 'bg-blue-50',   'text' => 'text-blue-700',  'dot' => 'bg-blue-100'],
        'Vétérans' => ['bg' => 'bg-amber-50',  'text' => 'text-amber-700', 'dot' => 'bg-amber-100'],
        'Dames'    => ['bg' => 'bg-pink-50',   'text' => 'text-pink-700',  'dot' => 'bg-pink-100'],
    ];

    $c = $colorMap[$team->category] ?? [
        'bg'  => 'bg-gray-50',
        'text' => 'text-gray-600',
        'dot'  => 'bg-gray-100',
    ];

    $hasCapitain = $team->captain_name !== '—';
    $showRoute   = route('admin.interclubs.teams.show', $team->id);
    $editRoute   = route('admin.interclubs.teams.edit', $team->id);
@endphp

<div class="flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white">

    {{-- En-tête colorée --}}
    <div class="flex items-center gap-3 {{ $c['bg'] }} px-4 py-3">
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ $c['dot'] }}">
            <span class="{{ $c['text'] }} text-xl font-bold leading-none">{{ $team->teamLetter }}</span>
        </div>
        <div class="min-w-0 flex-1">
            <p class="{{ $c['text'] }} truncate text-sm font-semibold">{{ $team->name }}</p>
            <p class="{{ $c['text'] }} mt-0.5 truncate text-[11px] opacity-70">{{ $team->division }}</p>
        </div>
    </div>

    {{-- Corps --}}
    <div class="flex-1 divide-y divide-gray-50 px-4">

        <div class="flex items-center justify-between gap-4 py-2.5">
            <span class="shrink-0 text-[11px] font-medium uppercase tracking-wide text-gray-400">Capitaine</span>
            @if ($hasCapitain)
                <span class="truncate text-sm font-medium text-gray-800">{{ $team->captain_name }}</span>
            @else
                <span class="text-xs italic text-gray-300">Non défini</span>
            @endif
        </div>

        <div class="flex items-center justify-between gap-4 py-2.5">
            <span class="shrink-0 text-[11px] font-medium uppercase tracking-wide text-gray-400">Noyau</span>
            @if ($team->membersCount > 0)
                <span class="text-sm font-medium text-gray-700">
                    {{ $team->membersCount }} joueur{{ $team->membersCount > 1 ? 's' : '' }}
                </span>
            @else
                <span class="text-xs italic text-gray-300">Aucun joueur</span>
            @endif
        </div>

        <div class="flex items-center justify-between gap-4 py-2.5">
            <span class="shrink-0 text-[11px] font-medium uppercase tracking-wide text-gray-400">Prochain match</span>
            @if ($team->nextMatchDate)
                <span class="text-sm font-medium text-gray-700">
                    {{ \Carbon\Carbon::parse($team->nextMatchDate)->translatedFormat('D d M') }}
                </span>
            @else
                <span class="text-xs italic text-gray-300">—</span>
            @endif
        </div>

    </div>

    {{-- Actions --}}
    <div class="flex items-center justify-between border-t border-gray-100 bg-gray-50/70 px-3 py-2">
        <div class="flex gap-0.5">
            <x-button class="btn-ghost btn-xs text-gray-500 hover:text-gray-900" icon="o-eye" label="Détails"
                link="{{ $showRoute }}" />
            <x-button class="btn-ghost btn-xs text-gray-500 hover:text-gray-900" icon="o-pencil" label="Modifier"
                link="{{ $editRoute }}" />
        </div>
        <x-button class="btn-ghost btn-xs text-error hover:opacity-80" icon="o-trash"
            wire:click="confirmDelete({{ $team->id }})" />
    </div>

</div>
