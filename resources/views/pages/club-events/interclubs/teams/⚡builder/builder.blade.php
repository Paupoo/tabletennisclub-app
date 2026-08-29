<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>
    <x-header progress-indicator separator :title="__('Team Builder')">
        <x-slot:actions>
            <x-button class="btn-ghost" link="{{ route('admin.interclubs.teams') }}" icon="o-arrow-left"
                :label="__('Back to teams')" />
        </x-slot:actions>
    </x-header>

    {{-- ── ÉTAPE 1 : Paramètres ─────────────────────────────────────────── --}}
    @if ($step === 1)
        <div class="mx-auto max-w-lg">
            <x-card class="shadow-sm" :title="__('Composition settings')">
                <div class="space-y-5">
                    <x-select
                        label="Saison"
                        :options="$seasons"
                        option-label="name"
                        option-value="id"
                        wire:model.live="seasonId"
                        :placeholder="__('Select a season')" />

                    {{-- Sélecteur de catégorie --}}
                    <div>
                        <p class="mb-2 text-sm font-medium text-gray-700">{{ __('Category') }}</p>
                        @php
                            $cats = [
                                'MEN'      => ['label' => 'Hommes',   'desc' => __('All competitors'), 'icon' => 'o-user-group'],
                                'WOMEN'    => ['label' => 'Dames',    'desc' => __('Female competitors only'), 'icon' => 'o-user-group'],
                                'VETERANS' => ['label' => 'Vétérans', 'desc' => __('Competitors turning 40 during the season'), 'icon' => 'o-user-group'],
                            ];
                        @endphp
                        <div class="grid grid-cols-3 gap-2">
                            @foreach ($cats as $value => $cat)
                                <button type="button"
                                    wire:click="$set('teamCategory', '{{ $value }}')"
                                    @class([
                                        'flex flex-col items-center gap-1 rounded-lg border-2 px-3 py-3 text-center text-sm transition-all',
                                        'border-blue-500 bg-blue-50 text-blue-800 shadow-sm' => $teamCategory === $value,
                                        'border-base-300 bg-white text-gray-600 hover:border-primary hover:bg-gray-50' => $teamCategory !== $value,
                                    ])>
                                    <span class="font-semibold leading-tight">{{ $cat['label'] }}</span>
                                    <span class="text-xs leading-tight opacity-60">{{ $cat['desc'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    @if ($missingBirthdateCount > 0)
                        <div class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                            <x-heroicon-o-exclamation-triangle class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
                            <span>
                                <strong>{{ $missingBirthdateCount }} compétiteur{{ $missingBirthdateCount > 1 ? 's' : '' }}</strong>
                                sans date de naissance — impossible de vérifier leur éligibilité vétéran.
                                Ils sont <strong>exclus</strong> de cette composition.
                            </span>
                        </div>
                    @endif

                    {{-- Mary pose son label dans un <legend> : cela nomme le groupe,
                    pas le curseur. L'aria-label nomme le curseur lui-même. --}}
                    <x-range
                        :label="__('Core size (players per team)')"
                        :aria-label="__('Core size (players per team)')"
                        wire:model.live="nucleusSize"
                        min="5"
                        max="20"
                        step="1" />

                    <div @class([
                        'rounded-lg p-4 text-sm',
                        'bg-blue-50 text-blue-800'   => $eligibleCount > 0,
                        'bg-amber-50 text-amber-800' => $eligibleCount === 0,
                    ])>
                        @php $teamsPreview = ($nucleusSize > 0 && $eligibleCount > 0) ? intdiv($eligibleCount, $nucleusSize) : 0; @endphp
                        <p class="font-semibold">{{ __('Estimated result') }}</p>
                        @if ($eligibleCount === 0)
                            <p class="mt-1">Aucun compétiteur éligible pour cette catégorie{{ $teamCategory === 'VETERANS' && !$seasonId ? ' (sélectionnez d\'abord une saison)' : '' }}.</p>
                        @else
                            <p class="mt-1">
                                <span class="text-2xl font-bold">{{ $teamsPreview }}</span> équipe{{ $teamsPreview > 1 ? 's' : '' }}
                                de <span class="font-semibold">{{ $nucleusSize }}</span> joueurs
                                <span class="opacity-70">
                                    ({{ $eligibleCount - ($teamsPreview * $nucleusSize) }} non assigné{{ $eligibleCount - ($teamsPreview * $nucleusSize) > 1 ? 's' : '' }})
                                </span>
                            </p>
                            <p class="mt-1 text-xs opacity-60">{{ $eligibleCount }} compétiteur{{ $eligibleCount > 1 ? 's' : '' }} éligible{{ $eligibleCount > 1 ? 's' : '' }}</p>
                        @endif
                    </div>
                </div>

                <x-slot:actions>
                    <x-button class="btn-primary w-full" icon="o-bolt" label="Calculer la distribution"
                        wire:click="startComputing" spinner="startComputing" />
                </x-slot:actions>
            </x-card>
        </div>

    {{-- ── ÉTAPE 2 : Distribution + ajustements ────────────────────────── --}}
    @elseif ($step === 2)

        {{-- Wrapper Alpine pour le drag & drop --}}
        <div x-data="{ dragging: null, over: null }">

            <p class="mb-6 text-sm text-gray-500">
                {{ count($proposedTeams) }} équipes proposées ·
                <span class="text-gray-400">{{ __('drag and drop players · click the star to designate a captain') }}</span>
            </p>

            <div class="grid gap-5 lg:grid-cols-2 xl:grid-cols-3">
                @foreach ($proposedTeams as $index => $teamData)

                    {{-- Zone de dépôt : équipe --}}
                    <div
                        wire:key="team-card-{{ $index }}"
                        class="rounded-xl border bg-white shadow-sm transition-all"
                        :class="over === {{ $index }} ? 'border-blue-400 ring-2 ring-blue-200' : 'border-base-300'"
                        @dragover.prevent="over = {{ $index }}"
                        @dragleave="over === {{ $index }} && (over = null)"
                        @drop.prevent="$wire.movePlayerToTeam(dragging, {{ $index }}); dragging = null; over = null">

                        {{-- En-tête --}}
                        <div class="flex items-center justify-between rounded-t-xl bg-gray-50 px-4 py-3">
                            <div class="flex items-center gap-2">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-800">
                                    {{ $teamData['letter'] }}
                                </span>
                                <span class="font-medium text-gray-900">Équipe {{ $teamData['letter'] }}</span>
                                @if (($teamData['captainId'] ?? null) !== null)
                                    @php $cap = $competitors[$teamData['captainId']] ?? null; @endphp
                                    @if ($cap)
                                        <span class="flex items-center gap-1 rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-semibold text-yellow-700">
                                            <x-heroicon-s-star class="h-2.5 w-2.5" />
                                            {{ $cap->first_name }}
                                        </span>
                                    @endif
                                @endif
                            </div>
                            <span class="text-xs text-gray-400">{{ count($teamData['players']) }} joueurs</span>
                        </div>

                        {{-- Liste des joueurs --}}
                        <div class="min-h-[3rem] divide-y divide-gray-100 px-4">
                            @forelse ($teamData['players'] as $playerId)
                                @php
                                    $player    = $competitors[$playerId] ?? null;
                                    $isCaptain = ($teamData['captainId'] ?? null) === $playerId;
                                @endphp
                                @if ($player)
                                    <div
                                        wire:key="player-{{ $playerId }}"
                                        draggable="true"
                                        class="flex cursor-grab items-center gap-2 py-2 active:cursor-grabbing"
                                        :class="dragging === {{ $playerId }} ? 'opacity-40' : 'hover:bg-gray-50'"
                                        @dragstart="dragging = {{ $playerId }}; $event.dataTransfer.effectAllowed = 'move'"
                                        @dragend="dragging = null; over = null">

                                        {{-- Grip --}}
                                        <x-heroicon-o-bars-2 class="h-3.5 w-3.5 shrink-0 text-gray-300" />

                                        <span class="flex-1 text-sm font-medium text-gray-900">
                                            {{ $player->first_name }} {{ $player->last_name }}
                                        </span>

                                        @if ($player->ranking)
                                            <span class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-semibold text-gray-600">
                                                {{ $player->ranking->getLabel() }}
                                            </span>
                                        @endif
                                        @if ($player->forceListFor($teamCategory) !== null)
                                            <span class="rounded bg-primary/10 px-1.5 py-0.5 text-xs font-semibold text-primary"
                                                title="Liste de force">
                                                #{{ $player->forceListFor($teamCategory) }}
                                            </span>
                                        @endif

                                        {{-- Bouton capitaine --}}
                                        <button
                                            wire:click="setCaptainInTeam({{ $index }}, {{ $playerId }})"
                                            title="{{ $isCaptain ? 'Retirer le capitanat' : 'Désigner capitaine' }}"
                                            class="shrink-0 rounded p-0.5 transition {{ $isCaptain ? 'text-yellow-500' : 'text-gray-300 hover:text-yellow-400' }}">
                                            <x-heroicon-s-star class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                @endif
                            @empty
                                <p class="py-4 text-center text-sm italic text-gray-400">{{ __('Drop a player here') }}</p>
                            @endforelse
                        </div>

                        {{-- Infos de ligue --}}
                        <div class="border-t border-base-300 bg-gray-50 px-4 pb-3 pt-2">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Ligue</p>
                            <div class="grid grid-cols-3 gap-2">
                                <x-select
                                    :options="$categoryOptions"
                                    wire:model="proposedTeams.{{ $index }}.category"
                                    :placeholder="__('Category')"
                                    class="select-xs text-xs" />
                                <x-select
                                    :options="$levelOptions"
                                    wire:model="proposedTeams.{{ $index }}.level"
                                    placeholder="Niveau"
                                    class="select-xs text-xs" />
                                <x-input
                                    wire:model="proposedTeams.{{ $index }}.division"
                                    placeholder="ex: 3A"
                                    class="input-xs text-xs" />
                            </div>
                        </div>
                    </div>
                @endforeach

                {{-- Zone dépôt : non assignés --}}
                @if (count($unassigned) > 0 || true)
                    <div
                        class="rounded-xl border bg-white shadow-sm transition-all"
                        :class="over === 'unassigned'
                            ? 'border-orange-400 ring-2 ring-orange-200'
                            : ({{ count($unassigned) > 0 ? 'true' : 'false' }} ? 'border-dashed border-base-300' : 'border-dashed border-base-300 opacity-60')"
                        @dragover.prevent="over = 'unassigned'"
                        @dragleave="over === 'unassigned' && (over = null)"
                        @drop.prevent="$wire.movePlayerToUnassigned(dragging); dragging = null; over = null">

                        <div class="flex items-center gap-2 rounded-t-xl bg-orange-50 px-4 py-3">
                            <x-heroicon-o-user-minus class="h-5 w-5 text-orange-400" />
                            <span class="font-medium text-orange-800">{{ __('Unassigned') }}</span>
                            @if (count($unassigned) > 0)
                                <span class="ml-auto text-xs text-orange-500">
                                    {{ count($unassigned) }} joueur{{ count($unassigned) > 1 ? 's' : '' }}
                                </span>
                            @endif
                        </div>

                        <div class="min-h-[3rem] divide-y divide-gray-100 px-4">
                            @forelse ($unassigned as $playerId)
                                @php $player = $competitors[$playerId] ?? null; @endphp
                                @if ($player)
                                    <div
                                        wire:key="unassigned-{{ $playerId }}"
                                        draggable="true"
                                        class="flex cursor-grab items-center gap-2 py-2 active:cursor-grabbing"
                                        :class="dragging === {{ $playerId }} ? 'opacity-40' : 'hover:bg-gray-50'"
                                        @dragstart="dragging = {{ $playerId }}; $event.dataTransfer.effectAllowed = 'move'"
                                        @dragend="dragging = null; over = null">
                                        <x-heroicon-o-bars-2 class="h-3.5 w-3.5 shrink-0 text-gray-300" />
                                        <span class="flex-1 text-sm font-medium text-gray-700">
                                            {{ $player->first_name }} {{ $player->last_name }}
                                        </span>
                                        @if ($player->ranking)
                                            <span class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-semibold text-gray-600">
                                                {{ $player->ranking->getLabel() }}
                                            </span>
                                        @endif
                                        @if ($player->forceListFor($teamCategory) !== null)
                                            <span class="rounded bg-primary/10 px-1.5 py-0.5 text-xs font-semibold text-primary"
                                                title="Liste de force">
                                                #{{ $player->forceListFor($teamCategory) }}
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            @empty
                                <p class="py-4 text-center text-sm italic text-gray-400">{{ __('Drop a player here') }}</p>
                            @endforelse
                        </div>
                    </div>
                @endif
            </div>

            {{-- Actions bas de page --}}
            <div class="mt-8 flex justify-end gap-3">
                <x-button class="btn-ghost" icon="o-arrow-left" :label="__('Edit settings')"
                    wire:click="backToStep1" />
                <x-button class="btn-primary" icon="o-check" :label="__('Save all teams')"
                    wire:click="save" wire:loading.attr="disabled" />
            </div>

        </div>{{-- /x-data --}}
    @endif

    {{-- Modal calcul de distribution --}}
    {{-- Volontairement non `persistent` : si le calcul échoue côté serveur, le
         modal n'offre aucun bouton de sortie et l'écran reste mort. --}}
    <x-app-modal wire:model="showComputingModal" :title="__('Building teams')" separator :open="$showComputingModal">
        <div class="py-10 text-center">
            <div class="mb-6 flex justify-center">
                <span class="loading loading-dots loading-lg text-primary"></span>
            </div>
            <h3 class="animate-pulse text-xl font-black uppercase italic tracking-widest">
                {{ __('Calculating distribution...') }}
            </h3>
            <p class="mx-auto mt-4 max-w-xs text-sm opacity-60">
                {{ __('Recalculating the force list and distributing players across teams.') }}
            </p>
        </div>
    </x-app-modal>
</div>
