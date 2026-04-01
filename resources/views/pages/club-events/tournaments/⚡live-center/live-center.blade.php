<div class="p-4 max-w-6xl mx-auto">
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header title="{{ $tournament->name }}" subtitle="{{ __('Follow or manage your tournament from here') }}">
        {{-- <x-slot:middle class="justify-end">
            <x-input placeholder="Rechercher..." wire:model.live.debounce.300ms="search" icon="o-magnifying-glass" />
        </x-slot:middle> --}}
        <x-slot:actions>
            {{-- <x-button label="{{ __('Actions') }}" class="btn-primary" @click="$wire.drawer = true" /> --}}
        </x-slot:actions>
    </x-header>
    {{-- CONTENU : POULES (8 poules sur 2 colonnes) --}}
    @php
    // Génération de 8 poules de 6 joueurs
    $noms = [
    'J. Peuplu',
    'S. Vigote',
    'M. Assin',
    'A. Térieur',
    'D. Drable',
    'G. Liguili',
    'P. Dupont',
    'M. Legrand',
    ];
    $ranks = ['B2', 'C4', 'D0', 'E2', 'E6', 'NC'];

    $loggedPlayer = 'A. Térieur';
    $subtitle = __('Good luck') . ' ' . ($loggedPlayer ?? __('everyone')) . '!';

    $upcomingTable = __('Table');
    @endphp


    <x-tabs wire:model="activeTab" class="mb-6">
        <x-tab name="pools" icon="o-users">
            <x-slot:label>
                {{ __('Pools') }}
            </x-slot:label>


            <x-header title="{{ __('Pools ranking') }}" :subtitle="$subtitle" class="mt-8" size="md" />


            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach (range(1, 8) as $pNum)
                <x-card title="{{ 'Pool' }} {{ $pNum }}" shadow compact class="border-0">
                    <div class="">
                        <div class="flex justify-between font-bold border-b border-base-300 pb-1 mb-1 opacity-50">
                            <span>{{ __('Player') }}</span>
                            <div class="flex gap-4">
                                <span>{{ __('Rank.') }}</span><span>{{ __('Pts') }}</span>
                            </div>
                        </div>
                        @foreach (range(0, 5) as $i)
                        <div @class([ 'flex justify-between items-center border-b border-base-300/30 py-1' , 'text-primary underline underline-offset-4 decoration-2'=>
                            ($noms[$i] ?? '') === $loggedPlayer,
                            ])>
                            <span class="truncate font-medium">{{ $noms[$i] ?? 'Player' }} @if (($noms[$i] ?? '') === $loggedPlayer)
                                <x-icon name="o-arrow-left" class="ml-4" />
                                @endif
                            </span>
                            <div class="flex gap-5 items-center">
                                <span class="opacity-50 font-mono w-6">{{ $ranks[$i] ?? 'NC' }}</span>
                                <span class="font-bold w-4 text-right">{{ rand(0, 9) }}</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </x-card>
                @endforeach
            </div>

        </x-tab>

        <x-tab name="tables" icon="o-squares-2x2">
            <x-slot:label>
                {{ __('Tables') }}
            </x-slot:label>

            <x-header title="Gestion des Salles" class="mt-8" size="md">
                <x-slot:actions>
                </x-slot:actions>
            </x-header>

            @foreach ([['nom' => 'Room A', 'range' => range(1, 8)], ['nom' => 'Room B', 'range' => range(9, 14)]] as $salle)
            <div class="mb-10">
                {{-- Titre de salle avec style Mary-UI --}}
                <div class="flex items-center gap-3 mb-6">
                    <x-icon name="o-map-pin" class="w-5 h-5" />
                    <span class="text-lg font-black tracking-tighter uppercase">{{ $salle['nom'] }}</span>
                    <div class="h-px bg-base-300 flex-grow"></div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    @foreach ($salle['range'] as $table)
                    @php
                    $isLibre = $salle['nom'] === 'Room B' || $table > 5;
                    $scores = ['11-8', '4-11', '11-9', '7-11', ' -- '];
                    @endphp

                    {{-- On utilise x-card pour la structure de base --}}
                    <x-card shadow
                        class="border border-base-400 {{ $isLibre ? 'bg-base-200/40' : 'bg-base-100/40' }} relative">

                        {{-- Header de la Card : Numéro et Statut --}}
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <div class="text-[10px] uppercase font-bold opacity-50">{{ __('Table') }}
                                </div>
                                <div class="text-2xl font-black">{{ str_pad($table, 2, '0', STR_PAD_LEFT) }}
                                </div>
                            </div>

                            @if ($isLibre)
                            <x-badge value="LIBRE" class="badge-success badge-sm font-bold" />
                            @else
                            <div class="text-right">
                                <x-badge value="{{ rand(5, 20) }} min" class="badge-ghost badge-xs"
                                    icon="o-clock" />
                            </div>
                            @endif
                        </div>

                        {{-- Détails du match --}}
                        <div class="space-y-3">
                            @if (!$isLibre)
                            <div class="bg-base-100 rounded-lg p-2 border border-base-300">
                                <div class="text-[11px] font-bold truncate">P. Dupont</div>
                                <div class="flex items-center gap-2 my-1">
                                    <div class="h-[1px] flex-grow bg-base-300"></div>
                                    <span class="text-[9px] font-black opacity-30 italic">VS</span>
                                    <div class="h-[1px] flex-grow bg-base-300"></div>
                                </div>
                                <div class="text-[11px] text-right font-bold truncate">M. Legrand</div>
                            </div>

                            {{-- Affichage du score avec Mary-UI --}}
                            <div class="flex justify-center gap-2 flex-wrap">
                                @foreach ($scores as $score)
                                <x-badge value="{{ $score }}"
                                    class="badge-info badge-soft font-mono text-[10px] px-2" />
                                @endforeach
                            </div>

                            <div class="pt-2">
                                <x-button label="Modifier" icon="o-pencil"
                                    class="btn-ghost btn-xs w-full bg-base-200"
                                    @click="$wire.drawer = true" />
                            </div>
                            @else
                            <div
                                class="py-6 flex flex-col items-center justify-center border-2 border-dashed rounded-lg">
                                <x-button
                                    label="{{ __('Launch') }}"
                                    icon="o-play"
                                    class="btn-outline btn-sm text-success"
                                    @click="$wire.launchDrawer = true" />
                            </div>
                            @endif
                        </div>

                    </x-card>
                    @endforeach
                </div>
            </div>
            @endforeach

        </x-tab>

        <x-tab name="upcoming" icon="o-megaphone">
            <x-slot:label>
                {{ __('Upcoming') }}
                <x-badge value="2" class="ml-1 badge-primary badge-sm" />
            </x-slot:label>

            {{-- Contenu : upcoming matches & annonces --}}
            <x-header title="{{ __('Upcoming matches') }}" class="mt-8" size="md">
                <x-slot:actions>
                    <span class="loading loading-ring loading-sm text-primary"></span>
                </x-slot:actions>
            </x-header>

            <div class="flex flex-col gap-3 lg:w-200">
                @foreach (range(1, 5) as $index)
                <div class="flex items-stretch bg-base-300 shadow-lg border border-base-content/10">
                    <div
                        class="bg-primary text-primary-content w-12 flex items-center justify-center font-black text-lg">
                        {{ $index }}
                    </div>
                    <div class="flex-1 p-3">
                        <div class="flex justify-between items-center mb-1">
                            <span
                                class="text-[10px] font-bold uppercase tracking-widest opacity-60">{{ __('Pool') }}
                                {{ rand(1, 8) }}</span>
                            <x-badge :value="$upcomingTable . ' ' . rand(1, 14)" class="badge-primary badge-soft badge-xs" />
                        </div>
                        <div class="flex justify-between items-center">
                            <div class="text-sm font-bold w-2/5">{{ __('Player') }} A <span
                                    class="text-[10px] opacity-50 italic">(B2)</span></div>
                            <div class="text-xs font-black italic opacity-30">VS</div>
                            <div class="text-sm font-bold w-2/5 text-right"><span
                                    class="text-[10px] opacity-50 italic">(C0)</span> {{ __('Player') }} B</div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Widgets bas de page --}}
            <div class="mt-16 p-4 bg-primary/10 border border-primary/20 rounded-lg flex items-center gap-4 lg:w-200">
                <x-icon name="o-information-circle" class="w-8 h-8 text-primary" />
                <div class="text-[11px] leading-tight">
                    <strong>{{ __('Reminder') }} :</strong>
                    {{ __('The matches are refereed by players from the previous pool.') }}
                </div>
            </div>
            <div
                class="mt-8 bg-black text-yellow-500 p-4 rounded font-mono text-[11px] border-l-4 border-yellow-500 flex items-center gap-3 lg:w-200">
                <span class="animate-ping text-red-500">●</span>
                <span>{{ __('Announcement: The sheets for pool 8 are to be brought to the scoring table.') }}</span>
            </div>
        </x-tab>

        <x-tab name="results" icon="o-trophy">
            <x-slot:label>
                {{ __('Results') }}
            </x-slot:label>
            <x-header title="{{ __('Final Table') }}" subtitle="{{ __('Knockout Stage') }}" class="my-8"
                size="md" />

            {{-- min-h-[800px] est nécessaire car il y a 8 matchs dans la première colonne --}}
            <div class="flex gap-12 overflow-x-auto pb-10 min-h-[800px]">

                {{-- Huitièmes de finale --}}
                <div class="flex flex-col min-w-[200px]">
                    <div class="text-center font-bold text-gray-400 uppercase text-xs h-8 mb-2">
                        {{ __('Round of 16') }}
                    </div>
                    <div class="flex flex-col justify-around flex-grow">
                        @foreach (range(1, 8) as $match)
                        <div class="p-2 border rounded-lg  shadow-sm space-y-2">
                            <div class="flex justify-between text-sm">
                                <span>{{ __('Player') }} {{ $match * 2 - 1 }}</span><strong>0</strong>
                            </div>
                            <div class="flex justify-between text-sm border-t pt-2 text-gray-400">
                                <span>{{ __('Player') }} {{ $match * 2 }}</span><strong>0</strong>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Quarts de finale --}}
                <div class="flex flex-col min-w-[200px]">
                    <div class="text-center font-bold text-gray-400 uppercase text-xs h-8 mb-2">
                        {{ __('Quarter-finals') }}
                    </div>
                    <div class="flex flex-col justify-around flex-grow">
                        @foreach (range(1, 4) as $match)
                        <div class="p-2 border rounded-lg  shadow-sm space-y-2 relative">
                            <div class="flex justify-between text-sm">
                                <span>{{ __('Winner R16') }}-{{ $match * 2 - 1 }}</span><strong>3</strong>
                            </div>
                            <div class="flex justify-between text-sm border-t pt-2 text-gray-400">
                                <span>{{ __('Winner R16') }}-{{ $match * 2 }}</span><strong>1</strong>
                            </div>
                            {{-- Connecteur visuel --}}
                            <div class="absolute -left-6 top-1/2 w-6 h-0.5 bg-gray-300"></div>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Demi-finales --}}
                <div class="flex flex-col min-w-[200px]">
                    <div class="text-center font-bold text-gray-400 uppercase text-xs h-8 mb-2">
                        {{ __('Semi-finals') }}
                    </div>
                    <div class="flex flex-col justify-around flex-grow">
                        @foreach (range(1, 2) as $match)
                        <div class="p-2 border-2 border-primary rounded-lg  shadow-md space-y-2 relative">
                            <div class="flex justify-between text-sm">
                                <span>{{ __('Winner Q') }}{{ $match * 2 - 1 }}</span><strong>2</strong>
                            </div>
                            <div class="flex justify-between text-sm border-t pt-2">
                                <span>{{ __('Winner Q') }}{{ $match * 2 }}</span><strong>3</strong>
                            </div>
                            {{-- Connecteur visuel --}}
                            <div class="absolute -left-6 top-1/2 w-6 h-0.5 bg-gray-300"></div>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Finale & Petite Finale --}}
                <div class="flex flex-col min-w-[220px]">
                    <div class="text-center font-bold text-yellow-500 uppercase text-xs h-8 mb-2">{{ __('Final') }}
                    </div>
                    <div class="flex flex-col justify-around flex-grow relative">

                        {{-- Grande Finale --}}
                        <div
                            class="p-4 border-4 border-yellow-500 rounded-xl bg-base-100 shadow-xl space-y-3 scale-110 relative z-10">
                            <div class="flex justify-between font-bold">
                                <span>{{ __('Winner S') }}1</span> <x-badge value="0" />
                            </div>
                            <div class="flex justify-between font-bold">
                                <span>{{ __('Winner S') }}2</span> <x-badge value="0" />
                            </div>
                            <x-button label="{{ __('Encoder Finale') }}" class="btn-warning btn-xs w-full" />
                            {{-- Connecteur visuel --}}
                            <div class="absolute -left-6 top-1/2 w-6 h-0.5 bg-yellow-500"></div>
                        </div>

                        {{-- Petite Finale --}}
                        <div class="absolute bottom-0 w-full p-3 border-2 border-info rounded-lg shadow-md space-y-2">
                            <div class="text-center font-bold text-gray-500 uppercase text-[10px] mb-1">
                                {{ __('3rd Place') }}
                            </div>
                            <div class="flex justify-between text-sm">
                                <span>{{ __('Loser S') }}1</span> <x-badge value="0" class="badge-ghost" />
                            </div>
                            <div class="flex justify-between text-sm border-t border-gray-300 pt-2">
                                <span>{{ __('Loser S') }}2</span> <x-badge value="0" class="badge-ghost" />
                            </div>
                            <x-button label="{{ __('Encoder Petite Finale') }}"
                                class="btn-outline btn-xs w-full mt-2" />
                        </div>

                    </div>
                </div>
            </div>
        </x-tab>
    </x-tabs>



    {{-- Score Entry Drawer --}}
    <x-drawer wire:model="drawer" title="Score Entry" right separator with-close-button class="w-11/12 md:w-[450px]">

        {{-- Match Overview Card --}}
        <div class="bg-base-200 p-4 rounded-xl mb-6 border border-base-300">
            <div class="flex justify-between items-center mb-3">
                <span class="text-[10px] font-black uppercase tracking-widest opacity-60">Live Match • Table 04</span>
                <x-badge value="BEST OF 5" class="badge-outline badge-xs opacity-50 font-bold" />
            </div>

            <div class="flex justify-between items-center gap-4">
                <div class="flex-1 text-center">
                    <div class="font-black text-sm truncate uppercase">P. Dupont</div>
                    <div class="text-3xl font-extrabold text-primary">2</div>
                </div>

                <div class="text-xl font-black opacity-20 italic">VS</div>

                <div class="flex-1 text-center">
                    <div class="font-black text-sm truncate uppercase">M. Legrand</div>
                    <div class="text-3xl font-extrabold">1</div>
                </div>
            </div>
        </div>

        {{-- Sets Scoring Section --}}
        <div class="space-y-3">
            <div class="flex items-center gap-2 mb-4">
                <x-icon name="o-list-bullet" class="w-4 h-4" />
                <span class="text-xs font-bold uppercase tracking-wider">Set Scores</span>
            </div>

            @foreach ([1, 2, 3, 4, 5] as $setNum)
            @php
            $isCurrent = $setNum === 4;
            $isFinished = $setNum < 4;
                $isLocked=$setNum> 4;
                @endphp

                <div
                    class="flex items-center gap-4 p-3 rounded-xl border transition-all {{ $isCurrent ? 'border-primary bg-primary/5 shadow-sm' : 'border-base-300 bg-base-100' }} {{ $isLocked ? 'opacity-40' : '' }}">

                    {{-- Set Indicator --}}
                    <div
                        class="flex-none w-10 h-10 rounded-lg flex flex-col items-center justify-center {{ $isCurrent ? 'bg-primary text-primary-content' : 'bg-base-200 text-base-content/50' }}">
                        <span class="text-[9px] uppercase font-bold leading-none">Set</span>
                        <span class="text-lg font-black leading-none">{{ $setNum }}</span>
                    </div>

                    {{-- Score Inputs --}}
                    <div class="flex flex-grow items-center gap-2">
                        <x-input type="number" placeholder="00"
                            class="input-sm text-center font-mono font-bold text-lg"
                            value="{{ $setNum == 1 ? '11' : ($setNum == 2 ? '04' : ($setNum == 3 ? '11' : '')) }}"
                            :disabled="$isLocked" />

                        <span class="opacity-30 font-bold">:</span>

                        <x-input type="number" placeholder="00"
                            class="input-sm text-center font-mono font-bold text-lg"
                            value="{{ $setNum == 1 ? '08' : ($setNum == 2 ? '11' : ($setNum == 3 ? '09' : '')) }}"
                            :disabled="$isLocked" />
                    </div>

                    {{-- Set Status Icon --}}
                    <div class="flex-none w-6 flex justify-center">
                        @if ($isFinished)
                        <x-icon name="o-check-circle" class="w-6 h-6 text-success" />
                        @elseif($isCurrent)
                        <span class="loading loading-ring loading-sm text-primary"></span>
                        @else
                        <x-icon name="o-lock-closed" class="w-4 h-4 opacity-20" />
                        @endif
                    </div>
                </div>
                @endforeach
        </div>

        {{-- Additional Comments --}}
        <div class="mt-8">
            <x-textarea label="Match Notes" placeholder="e.g., Medical timeout, yellow card, conduct warnings..."
                rows="3" class="textarea-bordered text-sm" />
        </div>

        {{-- Footer Actions --}}
        <x-slot:actions>
            <x-button label="Forfeit" icon="o-flag" class="btn-ghost text-error btn-sm" />
            <div class="flex-grow"></div>
            <x-button label="Cancel" @click="$wire.drawer = false" class="btn-sm" />
            <x-button label="Submit Score" icon="o-check" class="btn-primary btn-sm" />
        </x-slot:actions>

    </x-drawer>
    {{-- <x-drawer wire:model="drawer" title="Hello" subtitle="Livewire" separator with-close-button close-on-escape
        right class="w-full lg:w-1/3">
        <div>Hey!</div>

        <x-slot:actions>
            <x-button label="Cancel" @click="$wire.drawer = false" />
            <x-button label="Confirm" class="btn-primary" icon="o-check" spinner />
        </x-slot:actions>
    </x-drawer> --}}

    <x-drawer wire:model="launchDrawer" title="{{ __('Launch a match') }}" right separator with-close-button class="lg:w-1/3">

        <div class="space-y-6">
            {{-- En-tête de la liste --}}
            <div>
                <p class="text-sm text-base-content/60 mb-4">
                    {{ __('Select a match to assign to this table. The next scheduled match is highlighted.') }}
                </p>
            </div>

            {{-- Liste des matches --}}
            <div class="space-y-3">
                @php
                // Simulation de données fictives (à remplacer par ta variable $remainingMatches)
                $fictionalMatches = [
                ['id' => 1, 'p1' => 'Jean-Pierre G.', 'p2' => 'Marc O.', 'type' => 'Pool A', 'is_pool' => true, 'time' => '14:20'],
                ['id' => 2, 'p1' => 'Sarah L.', 'p2' => 'Amélie Q.', 'type' => 'Pool C', 'is_pool' => true, 'time' => '14:25'],
                ['id' => 3, 'p1' => 'Victor B.', 'p2' => 'Thomas M.', 'type' => 'Quarter-final', 'is_pool' => false, 'time' => '14:40'],
                ['id' => 4, 'p1' => 'Lucas D.', 'p2' => 'Éric P.', 'type' => 'Pool B', 'is_pool' => true, 'time' => '14:45'],
                ];
                @endphp

                @foreach($fictionalMatches as $index => $match)
                @php $isFirst = $index === 0; @endphp

                <div class="relative group">
                    {{-- Badge "Recommended" pour le premier match --}}
                    @if($isFirst)
                    <div class="absolute -top-2 left-4 z-10">
                        <x-badge value="{{ __('Recommended') }}" class="badge-primary badge-xs font-bold shadow-sm" />
                    </div>
                    @endif

                    <div @click="$wire.startMatch({{ $match['id'] }})"
                        class="p-4 rounded-xl border-2 transition-all cursor-pointer flex items-center justify-between
                        {{ $isFirst ? 'border-primary bg-primary/5 ring-1 ring-primary/20' : 'border-base-200 hover:border-primary/50 bg-base-100' }}">

                        <div class="flex-1">
                            {{-- Type de match --}}
                            <div class="flex items-center gap-2 mb-1">
                                <x-badge :value="$match['type']"
                                    class="{{ $match['is_pool'] ? 'badge-ghost' : 'badge-warning' }} badge-xs uppercase font-bold" />
                                <span class="text-[10px] opacity-40 font-mono">{{ $match['time'] }}</span>
                            </div>

                            {{-- Joueurs --}}
                            <div class="flex flex-col">
                                <span class="font-bold text-sm">{{ $match['p1'] }}</span>
                                <span class="text-[10px] opacity-30 italic font-black my-0.5">VS</span>
                                <span class="font-bold text-sm">{{ $match['p2'] }}</span>
                            </div>
                        </div>

                        {{-- Action --}}
                        <div class="ml-4">
                            <x-button icon="o-play"
                                class="btn-circle {{ $isFirst ? 'btn-primary' : 'btn-ghost' }} btn-sm" />
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            {{-- État vide si aucun match --}}
            @if(empty($fictionalMatches))
            <div class="text-center py-12 opacity-30">
                <x-icon name="o-no-symbol" class="w-12 h-12 mx-auto" />
                <p class="mt-2">{{ __('No matches scheduled') }}</p>
            </div>
            @endif
        </div>

        <x-slot:actions>
            <x-button label="{{ __('Cancel') }}" @click="$wire.launchDrawer = false" />
        </x-slot:actions>
    </x-drawer>
</div>