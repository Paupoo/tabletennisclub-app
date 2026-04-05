<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header title="{{ __('Club Overview') }}" subtitle="{{ __('Season 2025-2026') }}" separator>
        <x-slot:actions>
            <x-button label="{{ __('Send reminder') }}" icon="o-paper-airplane" class="btn-sm btn-primary btn-soft" />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">

        {{-- COLONNE GAUCHE : NAVIGATION & SANTÉ --}}
        <div class="space-y-4">
            <x-admin.shared.side-card title="{{ __('Navigation') }}" shadow class="mt-16">
                <div class="space-y-4">
                    <div>
                        <label
                            class="text-[10px] font-black uppercase opacity-40 mb-4 block tracking-widest italic">{{ __('Competition week') }}</label>
                        <div class="flex items-center gap-2">
                            <x-button icon="o-chevron-left" class="btn-sm btn-ghost bg-base-200"
                                wire:click="prevWeek" />
                            <x-select wire:model.live="selectedWeek" :options="$weeks_options"
                                class="select-sm flex-1 border-none bg-base-200/50 font-bold" />
                            <x-button icon="o-chevron-right" class="btn-sm btn-ghost bg-base-200"
                                wire:click="nextWeek" />
                        </div>
                    </div>

                    <x-choices label="{{ __('Focus on a team') }}" wire:model.live="selectedTeam" :options="$teams_list" single
                        searchable class="choices-sm" />

                    <div class="pt-2 space-y-2 border-t border-base-100">
                        <x-checkbox label="{{ __('Show issues only') }}" wire:model.live="filterAlerts" tight />
                    </div>
                </div>
            </x-admin.shared.side-card>

            <x-admin.shared.side-card class="bg-base-100 border border-base-200 shadow-sm">
                <div
                    class="text-[10px] font-black text-base-content uppercase opacity-50 mb-4 tracking-widest text-center italic">
                    {{ __('Saison : 20 Journées') }}
                </div>
                <div class="grid grid-cols-5 gap-2 justify-items-center">
                    @foreach ($weeks_monitor as $wm)
                        <div class="tooltip" data-tip="Semaine {{ $wm['wk'] }} : {{ $wm['status'] }}">
                            <button wire:click="$set('selectedWeek', 'WK{{ $wm['wk'] }}')"
                                @class([
                                    'w-7 h-7 rounded-md flex items-center justify-center text-[9px] font-black border transition-all hover:scale-110',
                                    'bg-success border-success text-white shadow-sm' => $wm['status'] == 'ok',
                                    'bg-warning border-warning text-black' => $wm['status'] == 'warning',
                                    'bg-error border-error text-white shadow-md' => $wm['status'] == 'nok',
                                    'bg-base-200 border-base-300 text-base-content/40' =>
                                        $wm['status'] == 'pending',
                                    'ring-2 ring-primary ring-offset-2 ring-offset-base-100' =>
                                        'WK' . $wm['wk'] == $selectedWeek,
                                ])>
                                {{ $wm['wk'] }}
                            </button>
                        </div>
                    @endforeach
                </div>
                <div class="mt-6 pt-4 border-t border-base-200 flex justify-between items-center px-1">
                    <span class="text-[9px] font-black uppercase tracking-tighter opacity-50">Score de
                        préparation</span>
                    <span class="text-xl font-black text-success">14<span
                            class="text-xs opacity-50 text-base-content/50">/20</span></span>
                </div>
            </x-admin.shared.side-card>
        </div>

        {{-- COLONNE DROITE : LISTE --}}
        <div class="lg:col-span-3">
            {{-- Bandeau responsabilités --}}
            <div
                class="flex items-center gap-6 px-4 py-3 mb-4 rounded-lg bg-base-200/50 border border-base-300/50 text-sm">
                <div class="flex items-center gap-2">
                    <x-icon name="o-key" class="w-4 h-4 opacity-40 shrink-0" />
                    <span class="text-[10px] uppercase font-black tracking-widest opacity-40 shrink-0">Clés</span>
                    @if (!empty($day_responsibilities['keys']))
                        <div class="flex gap-1">
                            @foreach ($day_responsibilities['keys'] as $person)
                                <span class="badge badge-sm badge-ghost font-semibold">{{ $person }}</span>
                            @endforeach
                        </div>
                    @else
                        <span class="text-error text-xs font-bold uppercase">Personne</span>
                    @endif
                </div>

                <div class="w-px h-4 bg-base-300"></div>

                <div class="flex items-center gap-2">
                    <x-icon name="o-beaker" class="w-4 h-4 opacity-40 shrink-0" />
                    <span class="text-[10px] uppercase font-black tracking-widest opacity-40 shrink-0">Bar</span>
                    @if ($day_responsibilities['bar'])
                        <span
                            class="badge badge-sm badge-ghost font-semibold">{{ $day_responsibilities['bar'] }}</span>
                    @else
                        <span class="text-error text-xs font-bold uppercase">Personne</span>
                    @endif
                </div>

                <div class="ml-auto">
                    @if ($day_status === 'ok')
                        <span
                            class="text-success text-[10px] font-black uppercase tracking-widest flex items-center gap-1">
                            <x-icon name="o-check-circle" class="w-3.5 h-3.5" /> Couvert
                        </span>
                    @else
                        <span
                            class="text-error text-[10px] font-black uppercase tracking-widest flex items-center gap-1">
                            <x-icon name="o-exclamation-circle" class="w-3.5 h-3.5" /> Incomplet
                        </span>
                    @endif
                </div>
            </div>

            <x-card shadow class="border-none overflow-hidden bg-base-100 p-0" wire:loading.class="opacity-50">
                <div class="flex flex-wrap gap-4 mb-6 px-1">
                    <div class="flex items-center gap-1.5">
                        <div class="w-2 h-2 rounded-full bg-success"></div>
                        <span class="text-[10px] font-bold uppercase opacity-60">{{ __('Complete') }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <div class="w-2 h-2 rounded-full bg-warning"></div>
                        <span class="text-[10px] font-bold uppercase opacity-60">{{ __('Incomplete') }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <div class="w-2 h-2 rounded-full bg-error animate-pulse"></div>
                        <span class="text-[10px] font-bold uppercase opacity-60">{{ __('No selection') }}</span>
                    </div>
                </div>
                @foreach ($categories as $name => $teams)
                    <div class="bg-base-200/50 py-2 px-4 border-y border-base-300/20 flex items-center justify-between">
                        <span
                            class="text-[10px] font-black text-base-content/60 uppercase tracking-widest">{{ $name }}</span>
                        <span class="text-[9px] font-bold opacity-40">{{ count($teams) }}
                            {{ __('teams') }}</span>
                    </div>
                    <x-table :headers="$headers" :rows="$teams" :no-headers="$loop->iteration > 1" class="table-compact">
                        @scope('cell_name', $team)
                            <div class="flex items-center gap-3 py-1">
                                <div @class([
                                    'w-1.5 h-6 rounded-full',
                                    'bg-success' => $team['status'] == 'validated',
                                    'bg-warning' => $team['status'] == 'pending',
                                    'bg-error animate-pulse' => $team['status'] == 'alert',
                                ])></div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-sm leading-none">{{ $team['name'] }}</span>

                                    </div>
                                    <span
                                        class="text-[9px] opacity-40 font-black uppercase tracking-tighter">{{ $team['div'] }}</span>
                                </div>
                            </div>
                        @endscope

                        @scope('cell_captain', $team)
                            <div class="flex items-center gap-2">
                                <x-avatar placeholder="{{ substr($team['captain'], 0, 1) }}"
                                    class="w-6 h-6 bg-neutral text-white text-[10px] font-black" />
                                <span class="text-xs font-medium">{{ $team['captain'] }}</span>
                            </div>
                        @endscope

                        @scope('cell_players', $team)
                            <div class="flex justify-center gap-1">
                                @for ($i = 1; $i <= $team['max_players']; $i++)
                                    <div @class([
                                        'w-2 h-4 rounded-sm transition-all',
                                        'bg-primary' => $i <= $team['players'],
                                        'bg-base-300' => $i > $team['players'],
                                    ])></div>
                                @endfor
                            </div>
                        @endscope

                        @scope('cell_status', $team)
                            @if ($team['status'] != 'alert')
                                <div class="flex items-center gap-1 text-primary">
                                    <x-icon name="o-chat-bubble-bottom-center-text" class="w-4 h-4" />
                                    <span class="text-[10px] font-black uppercase">OK</span>
                                </div>
                            @else
                                <div class="text-error flex items-center gap-1 animate-pulse">
                                    <x-icon name="o-exclamation-triangle" class="w-4 h-4" />
                                    <span class="text-[10px] font-black uppercase">Manquant</span>
                                </div>
                            @endif
                        @endscope

                        @scope('cell_action', $team)
                            <x-button icon="o-arrow-right" class="btn-ghost btn-xs text-base-content/40 hover:text-primary"
                                wire:click="$set('drawerSelection', true)" />
                        @endscope
                    </x-table>
                @endforeach
            </x-card>
        </div>
    </div>

    <x-drawer wire:model="drawerSelection" title="Sélection WK13" subtitle="vs Perwez A • 05/02" right separator
        with-close-button class="w-11/12 lg:w-1/3">
        <div class="space-y-6">
            <div>
                <div class="flex justify-between text-[10px] font-black uppercase mb-2">
                    <span>Sélectionnés</span>
                    <span @class(['text-success font-black' => count($selectedPlayers) == 4])>{{ count($selectedPlayers) }} / 4</span>
                </div>
                <progress @class([
                    'progress w-full h-2 transition-all duration-500',
                    'progress-primary' => count($selectedPlayers) < 4,
                    'progress-success' => count($selectedPlayers) == 4,
                ]) value="{{ count($selectedPlayers) }}"
                    max="4"></progress>
            </div>

            <div>
                <div class="text-[10px] font-black uppercase opacity-40 mb-3 tracking-widest">Roster de l'équipe
                </div>
                <div class="space-y-2">
                    @foreach ($roster as $p)
                        @php $isSelected = in_array($p['name'], $selectedPlayers); @endphp
                        <div wire:click="togglePlayer('{{ $p['name'] }}')" @class([
                            'p-3 rounded-xl border cursor-pointer transition-all flex items-center justify-between group',
                            'border-primary bg-primary/5 ring-1 ring-primary' => $isSelected,
                            'opacity-40 grayscale pointer-events-none' => $p['available'] == 'no',
                            'border-base-200 hover:border-primary/50 bg-base-100' =>
                                !$isSelected && $p['available'] != 'no',
                        ])>
                            <div class="flex items-center gap-3">
                                <x-avatar placeholder="{{ substr($p['name'], 0, 1) }}" @class([
                                    '!w-9 !rounded-lg font-black',
                                    $isSelected ? 'bg-primary text-primary-content' : 'bg-base-200',
                                ]) />
                                <div>
                                    <div class="text-xs font-black">{{ $p['name'] }}</div>
                                    <div class="text-[9px] font-bold uppercase opacity-50">{{ $p['rank'] }} •
                                        {{ $p['available'] }}
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="text-right group-hover:invisible">
                                    <div class="text-[10px] font-black">{{ $p['winrate'] }}%</div>
                                </div>
                                <div @class([
                                    'w-5 h-5 rounded border flex items-center justify-center',
                                    'bg-primary border-primary text-primary-content' => $isSelected,
                                    'border-base-300 bg-white' => !$isSelected,
                                ])>
                                    @if ($isSelected)
                                        <x-icon name="o-check" class="w-3 h-3" />
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="pt-4 border-t border-dashed border-base-300">
                <div class="text-[10px] font-black uppercase opacity-40 mb-3 tracking-widest">Chercher un remplaçant
                </div>
                <x-input placeholder="Nom du joueur..." icon="o-magnifying-glass"
                    wire:model.live.debounce.300ms="search" class="input-sm bg-base-200/50 border-none rounded-lg" />
                @if (strlen($search) >= 2)
                    <div class="mt-4 space-y-2 animate-in fade-in slide-in-from-top-2">
                        @forelse($searchResults as $res)
                            @php $isSelected = in_array($res['name'], $selectedPlayers); @endphp
                            <div wire:click="togglePlayer('{{ $res['name'] }}')" @class([
                                'p-2 rounded-lg border border-dashed flex items-center justify-between cursor-pointer transition-all',
                                'border-primary bg-primary/5' => $isSelected,
                                'border-base-300 hover:border-primary' => !$isSelected,
                            ])>
                                <div class="flex items-center gap-2">
                                    <x-icon name="o-user-plus" class="w-4 h-4 opacity-40" />
                                    <div class="flex flex-col">
                                        <span class="text-[11px] font-bold">{{ $res['name'] }}</span>
                                        <span class="text-[9px] opacity-50 uppercase">{{ $res['rank'] }} •
                                            {{ $res['winrate'] }}% win</span>
                                    </div>
                                </div>
                                @if ($isSelected)
                                    <x-icon name="o-check-circle" class="w-5 h-5 text-primary" />
                                @endif
                            </div>
                        @empty
                            <div class="text-center p-4 text-xs opacity-40">Aucun joueur trouvé.</div>
                        @endforelse
                    </div>
                @endif
            </div>
        </div>
        <x-slot:actions>
            <x-button label="Annuler" @click="$wire.drawerSelection = false" class="btn-ghost" />
            <x-button label="Confirmer" wire:click="saveSelection" class="btn-primary" icon="o-check"
                :disabled="count($selectedPlayers) === 0" />
        </x-slot:actions>
    </x-drawer>

    {{-- MODAL MESSAGE --}}
    <x-modal wire:model="modalMessage" title="Dernière étape" separator>
        <div class="space-y-4">
            <div class="flex items-center gap-3 p-3 bg-primary/5 rounded-xl border border-primary/10">
                <x-icon name="o-information-circle" class="w-5 h-5 text-primary" />
                <p class="text-xs font-medium">Votre sélection est prête. Ajoutez une consigne pour vos joueurs.
                </p>
            </div>

            <x-textarea label="Message aux convoqués" wire:model="captainMessage"
                placeholder="Ex: Départ 18h45 du club..." rows="4"
                class="bg-base-200/50 border-none focus:ring-primary" />
        </div>

        <x-slot:actions>
            <x-button label="Passer" wire:click="confirmAndSend" class="btn-ghost" />
            <x-button label="Envoyer" wire:click="confirmAndSend" class="btn-primary" icon="o-paper-airplane" />
        </x-slot:actions>
    </x-modal>
</div>
