<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <div>
        <x-header separator subtitle="Season 2025-2026 • Ottignies B" title="Captain's Dashboard">
            <x-slot:actions>
                <x-button class="btn-ghost btn-sm" icon="o-arrow-down-tray" label="Export Schedule" />
            </x-slot:actions>
        </x-header>

        <div class="grid grid-cols-1 gap-8 lg:grid-cols-4">
            {{-- COLONNE GAUCHE --}}
            <div class="space-y-6">
                <x-admin.shared.side-card shadow title="{{ __('Select a team') }}">
                    <x-choices label="Select a team" :options="[
                        ['id' => 'ottignies-c', 'name' => 'Ottignies C (Seniors)', 'selected' => true],
                        ['id' => 'ottignies-a', 'name' => 'Ottignies A (Dames)'],
                        ['id' => 'ottignies-b', 'name' => 'Ottignies B (Vétérans)'],
                    ]" wire:model.live="selectedTeam" single />
                </x-admin.shared.side-card>

                <x-admin.shared.side-card icon="o-list-bullet" separator shadow title="{{ __('Division Standing') }}">
                    <div
                        class="bg-primary/5 border-primary/10 mb-4 flex items-center justify-between rounded-xl border p-4">
                        <div>
                            <div class="text-[10px] font-black uppercase opacity-50">{{ __('Rank') }}</div>
                            <div class="text-primary text-3xl font-black">4<span class="text-sm opacity-50">/12</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-[10px] font-black uppercase opacity-50">{{ __('Points') }}</div>
                            <div class="text-xl font-bold">28</div>
                        </div>
                    </div>

                    <div class="space-y-1">
                        @php
                            $standing = [
                                ['pos' => 1, 'team' => 'Wavre A', 'pts' => 36, 'me' => false],
                                ['pos' => 2, 'team' => 'Perwez B', 'pts' => 32, 'me' => false],
                                ['pos' => 3, 'team' => 'Champ d\'en Haut', 'pts' => 30, 'me' => false],
                                ['pos' => 4, 'team' => 'Ottignies B', 'pts' => 28, 'me' => true],
                                ['pos' => 5, 'team' => 'Logis J', 'pts' => 24, 'me' => false],
                            ];
                        @endphp
                        @foreach ($standing as $s)
                            <div @class([
                                'flex items-center justify-between p-2 text-[11px] rounded-lg',
                                'bg-primary/10 font-black border border-primary/20' => $s['me'],
                            ])>
                                <span class="w-4 opacity-50">{{ $s['pos'] }}</span>
                                <span class="mx-2 flex-1 truncate">{{ $s['team'] }}</span>
                                <span class="font-mono">{{ $s['pts'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </x-admin.shared.side-card>

                <x-admin.shared.side-card icon="o-users" separator shadow title="{{ __('Roster') }}">
                    <div class="space-y-1">
                        @php
                            $players = [
                                [
                                    'name' => 'Marc D.',
                                    'rank' => 'B4',
                                    'role' => 'Captain',
                                    'img' => 'https://i.pravatar.cc/150?u=1',
                                ],
                                [
                                    'name' => 'Aurelien V.',
                                    'rank' => 'B6',
                                    'role' => 'Player',
                                    'img' => 'https://i.pravatar.cc/150?u=2',
                                ],
                                [
                                    'name' => 'Jean-Paul H.',
                                    'rank' => 'C0',
                                    'role' => 'Player',
                                    'img' => 'https://i.pravatar.cc/150?u=3',
                                ],
                                [
                                    'name' => 'Luc L.',
                                    'rank' => 'C2',
                                    'role' => 'Reserve',
                                    'img' => 'https://i.pravatar.cc/150?u=4',
                                ],
                            ];
                        @endphp
                        @foreach ($players as $player)
                            <x-list-item :item="[]" class="!p-1" no-hover no-separator>
                                <x-slot:avatar>
                                    <x-avatar :image="$player['img']" class="!w-7" />
                                </x-slot:avatar>
                                <x-slot:value>
                                    <span class="text-xs font-bold">{{ $player['name'] }}</span>
                                </x-slot:value>
                                <x-slot:sub-value class="text-[9px]">{{ $player['rank'] }}</x-slot:sub-value>
                            </x-list-item>
                        @endforeach
                    </div>
                </x-admin.shared.side-card>
            </div>

            {{-- COLONNE DROITE --}}
            <div class="space-y-3 lg:col-span-3">
                <div class="mb-6 flex gap-2">
                    <x-badge class="badge-neutral" value="All Weeks" />
                    <x-badge class="badge-warning font-bold" value="Pending (3)" />
                    <x-badge class="badge-success" value="Completed (9)" />
                </div>

                <div class="divide-base-200 border-base-200 divide-y overflow-hidden rounded-xl border">
                    @foreach ($weeks as $w)
                        @php
                            $count = $w['is_demo'] ? count($selectedPlayers) : ($w['status'] === 'ready' ? 4 : 0);
                        @endphp
                        <x-week-card :date="$w['date']" :opponent="$w['opp']" :selection-count="$count" :status="$w['status']"
                            :week="$w['wk']" />
                    @endforeach
                </div>
            </div>
        </div>

        {{-- COMPOSANTS EXTERNES --}}
        <x-drawer class="w-11/12 lg:w-1/3" right separator subtitle="vs Perwez A • 05/02" title="Sélection WK13"
            wire:model="drawerSelection" with-close-button>
            <div class="space-y-6">
                <div>
                    <div class="mb-2 flex justify-between text-[10px] font-black uppercase">
                        <span>Sélectionnés</span>
                        <span @class(['text-success font-black' => count($selectedPlayers) == 4])>{{ count($selectedPlayers) }} / 4</span>
                    </div>
                    <progress @class([
                        'progress w-full h-2 transition-all duration-500',
                        'progress-primary' => count($selectedPlayers) < 4,
                        'progress-success' => count($selectedPlayers) == 4,
                    ]) max="4"
                        value="{{ count($selectedPlayers) }}"></progress>
                </div>

                <div>
                    <div class="mb-3 text-[10px] font-black uppercase tracking-widest opacity-40">Roster de l'équipe
                    </div>
                    <div class="space-y-2">
                        @foreach ($roster as $p)
                            @php $isSelected = in_array($p['name'], $selectedPlayers); @endphp
                            <div @class([
                                'p-3 rounded-xl border cursor-pointer transition-all flex items-center justify-between group',
                                'border-primary bg-primary/5 ring-1 ring-primary' => $isSelected,
                                'opacity-40 grayscale pointer-events-none' => $p['available'] == 'no',
                                'border-base-200 hover:border-primary/50 bg-base-100' =>
                                    !$isSelected && $p['available'] != 'no',
                            ]) wire:click="togglePlayer('{{ $p['name'] }}')">
                                <div class="flex items-center gap-3">
                                    <x-avatar @class([
                                        '!w-9 !rounded-lg font-black',
                                        $isSelected ? 'bg-primary text-primary-content' : 'bg-base-200',
                                    ])
                                        placeholder="{{ substr($p['name'], 0, 1) }}" />
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
                                            <x-icon class="h-3 w-3" name="o-check" />
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="border-base-300 border-t border-dashed pt-4">
                    <div class="mb-3 text-[10px] font-black uppercase tracking-widest opacity-40">Chercher un remplaçant
                    </div>
                    <x-input class="input-sm bg-base-200/50 rounded-lg border-none" icon="o-magnifying-glass"
                        placeholder="Nom du joueur..." wire:model.live.debounce.300ms="search" />
                    @if (strlen($search) >= 2)
                        <div class="animate-in fade-in slide-in-from-top-2 mt-4 space-y-2">
                            @forelse($searchResults as $res)
                                @php $isSelected = in_array($res['name'], $selectedPlayers); @endphp
                                <div @class([
                                    'p-2 rounded-lg border border-dashed flex items-center justify-between cursor-pointer transition-all',
                                    'border-primary bg-primary/5' => $isSelected,
                                    'border-base-300 hover:border-primary' => !$isSelected,
                                ]) wire:click="togglePlayer('{{ $res['name'] }}')">
                                    <div class="flex items-center gap-2">
                                        <x-icon class="h-4 w-4 opacity-40" name="o-user-plus" />
                                        <div class="flex flex-col">
                                            <span class="text-[11px] font-bold">{{ $res['name'] }}</span>
                                            <span class="text-[9px] uppercase opacity-50">{{ $res['rank'] }} •
                                                {{ $res['winrate'] }}% win</span>
                                        </div>
                                    </div>
                                    @if ($isSelected)
                                        <x-icon class="text-primary h-5 w-5" name="o-check-circle" />
                                    @endif
                                </div>
                            @empty
                                <div class="p-4 text-center text-xs opacity-40">Aucun joueur trouvé.</div>
                            @endforelse
                        </div>
                    @endif
                </div>
            </div>
            <x-slot:actions>
                <x-button @click="$wire.drawerSelection = false" class="btn-ghost" label="Annuler" />
                <x-button :disabled="count($selectedPlayers) === 0" class="btn-primary" icon="o-check" label="Confirmer"
                    wire:click="saveSelection" />
            </x-slot:actions>
        </x-drawer>
        {{-- MODAL MESSAGE --}}
        <x-modal separator title="Dernière étape" wire:model="modalMessage">
            <div class="space-y-4">
                <div class="bg-primary/5 border-primary/10 flex items-center gap-3 rounded-xl border p-3">
                    <x-icon class="text-primary h-5 w-5" name="o-information-circle" />
                    <p class="text-xs font-medium">Votre sélection est prête. Ajoutez une consigne pour vos joueurs.
                    </p>
                </div>

                <x-textarea class="bg-base-200/50 focus:ring-primary border-none" label="Message aux convoqués"
                    placeholder="Ex: Départ 18h45 du club..." rows="4" wire:model="captainMessage" />
            </div>

            <x-slot:actions>
                <x-button class="btn-ghost" label="Passer" wire:click="confirmAndSend" />
                <x-button class="btn-primary" icon="o-paper-airplane" label="Envoyer" wire:click="confirmAndSend" />
            </x-slot:actions>
        </x-modal>
    </div>
</div>
