<div>
    <x-header progress-indicator separator
        :title="'Modifier — ' . ($team->club?->name ?? '') . ' ' . $team->name">
        <x-slot:actions>
            <x-button class="btn-ghost" link="{{ route('admin.interclubs.teams.show', $team->id) }}"
                icon="o-arrow-left" label="Retour" />
        </x-slot:actions>
    </x-header>

    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />

    <div class="grid gap-6 lg:grid-cols-3">

        {{-- ── Colonne gauche : nom + capitaine ──────────────────────── --}}
        <div class="space-y-5 lg:col-span-1">

            {{-- Lettre de l'équipe --}}
            <x-card class="border-gray-200 shadow-sm" title="Identité">
                <div class="space-y-4">
                    <x-select
                        label="Lettre de l'équipe"
                        :options="$teamNameOptions"
                        wire:model="name"
                        placeholder="A – Z" />

                    @if ($team->league)
                        <div class="rounded-lg bg-gray-50 p-3 text-sm text-gray-600">
                            <p class="font-medium text-gray-800">Ligue</p>
                            <p class="mt-1">{{ $team->league->level }} · {{ $team->league->division }}</p>
                            <p class="text-xs text-gray-400">{{ $team->league->category }}</p>
                        </div>
                    @endif

                    <div class="text-xs text-gray-400">
                        Saison : <span class="font-medium text-gray-600">{{ $team->season?->name ?? '—' }}</span>
                    </div>
                </div>
            </x-card>

            {{-- Capitaine --}}
            <x-card class="border-gray-200 shadow-sm" title="Capitaine">
                @if ($captainId)
                    @php $captain = $competitors->find($captainId) ?? $team->captain; @endphp
                    <div class="mb-4 flex items-center gap-3 rounded-lg bg-yellow-50 p-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-yellow-200 text-sm font-bold text-yellow-800">
                            {{ mb_strtoupper(substr($captain?->first_name ?? '?', 0, 1)) }}{{ strtoupper(substr($captain?->last_name ?? '', 0, 1)) }}
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-gray-900">
                                {{ $captain?->first_name }} {{ $captain?->last_name }}
                            </p>
                            @if ($captain?->ranking)
                                <p class="text-xs text-gray-500">{{ $captain->ranking }}</p>
                            @endif
                        </div>
                        <x-button class="btn-ghost btn-xs text-gray-400 hover:text-red-500"
                            icon="o-x-mark" wire:click="removeCaptain" />
                    </div>
                @else
                    <p class="mb-4 text-sm text-gray-400 italic">Aucun capitaine désigné.</p>
                @endif

                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                    Désigner parmi le noyau
                </p>
                <div class="max-h-48 space-y-1 overflow-y-auto">
                    @foreach ($competitors->whereIn('id', $memberIds) as $member)
                        <button
                            wire:click="setCaptain({{ $member->id }})"
                            wire:key="cap-{{ $member->id }}"
                            class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm transition hover:bg-gray-50
                                {{ $captainId === $member->id ? 'bg-yellow-50 font-semibold text-yellow-800' : 'text-gray-700' }}">
                            <span>{{ $member->first_name }} {{ $member->last_name }}</span>
                            @if ($member->ranking)
                                <span class="ml-auto rounded bg-gray-100 px-1 py-0.5 text-[10px] font-semibold text-gray-500">
                                    {{ $member->ranking }}
                                </span>
                            @endif
                            @if ($captainId === $member->id)
                                <x-heroicon-s-star class="h-3.5 w-3.5 text-yellow-500" />
                            @endif
                        </button>
                    @endforeach
                </div>
            </x-card>
        </div>

        {{-- ── Colonne droite : composition du noyau ──────────────────── --}}
        <x-card class="border-gray-200 shadow-sm lg:col-span-2" title="Composition du noyau">
            <x-slot:subtitle>
                <span class="text-sm text-gray-500">
                    {{ count($memberIds) }} joueur{{ count($memberIds) > 1 ? 's' : '' }} sélectionné{{ count($memberIds) > 1 ? 's' : '' }}
                </span>
            </x-slot:subtitle>

            <x-input
                class="mb-4"
                clearable
                icon="o-magnifying-glass"
                placeholder="Rechercher un compétiteur…"
                wire:model.live.debounce.250ms="memberSearch" />

            <div class="divide-y divide-gray-100">
                @forelse ($competitors as $user)
                    @php $selected = in_array($user->id, $memberIds); @endphp
                    <div
                        wire:key="competitor-{{ $user->id }}"
                        class="flex cursor-pointer items-center gap-3 rounded-lg px-2 py-2.5 transition
                            {{ $selected ? 'bg-blue-50' : 'hover:bg-gray-50' }}"
                        wire:click="toggleMember({{ $user->id }})">

                        {{-- Checkbox visuel --}}
                        <div class="flex h-5 w-5 shrink-0 items-center justify-center rounded border-2 transition
                            {{ $selected ? 'border-blue-500 bg-blue-500' : 'border-gray-300 bg-white' }}">
                            @if ($selected)
                                <x-heroicon-s-check class="h-3 w-3 text-white" />
                            @endif
                        </div>

                        <div class="flex flex-1 items-center justify-between">
                            <div>
                                <span class="text-sm font-medium text-gray-900">
                                    {{ $user->first_name }} {{ $user->last_name }}
                                </span>
                                @if ($captainId === $user->id)
                                    <span class="ml-1.5 rounded bg-yellow-100 px-1.5 py-0.5 text-[10px] font-semibold text-yellow-700">Cap.</span>
                                @endif
                            </div>
                            @if ($user->ranking)
                                <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-500">
                                    {{ $user->ranking }}
                                </span>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-gray-400 italic">Aucun résultat.</p>
                @endforelse
            </div>
        </x-card>
    </div>

    {{-- ── Actions ──────────────────────────────────────────────────────── --}}
    <div class="mt-6 flex justify-end gap-3">
        <x-button class="btn-ghost" link="{{ route('admin.interclubs.teams.show', $team->id) }}" label="Annuler" />
        <x-button class="btn-primary" icon="o-check" label="Enregistrer" wire:click="save"
            wire:loading.attr="disabled" />
    </div>
</div>
