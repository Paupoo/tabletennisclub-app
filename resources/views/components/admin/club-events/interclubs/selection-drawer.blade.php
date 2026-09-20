{{--
    Le tiroir de composition d'une équipe, partagé par l'écran des sélections et
    le control center.

    Les deux pages composaient une équipe avec deux tiroirs différents, et celui
    du control center était le plus pauvre : ni disponibilités, ni joueurs déjà
    alignés ailleurs cette semaine, ni statistiques, ni coordonnées. Un admin qui
    composait depuis là était aveugle aux deux informations qui décident d'une
    composition. Il n'y a plus qu'une implémentation.

    Les données arrivent par le trait ComposesInterclubLineup, qui garantit que
    les deux appelants fournissent la même forme.

    Usage :
        <x-admin.club-events.interclubs.selection-drawer
            model="drawerSelection"
            :title="..." :subtitle="..."
            :roster="$roster" :selected-ids="$selectedPlayerIds"
            :max-players="$maxPlayers" :week-number="$weekNumber"
            :can-search-substitute="$canSearchSubstitute"
            :search-results="$searchResults" :search-note="$searchNote"
            :search-term="$search"
            save-action="saveSelection" />
--}}
@props([
    'model',
    'title' => null,
    'subtitle' => '',
    'roster' => [],
    'selectedIds' => [],
    'maxPlayers' => 4,
    'weekNumber' => null,
    'fixtureId' => null,
    'canSearchSubstitute' => false,
    'searchResults' => [],
    'searchNote' => null,
    'searchModel' => 'search',
    'searchTerm' => '',
    'saveAction' => 'saveSelection',
    'saveLabel' => null,
    'poolRows' => [],
    'poolWaiting' => [],
    'poolHiddenCount' => 0,
    'poolMaybeCount' => 0,
    'poolMaybeTeams' => [],
    'lineupConstraint' => null,
])

@php
    $title ??= __('Selection');
    $saveLabel ??= __('Save selection');
    $selectedCount = count($selectedIds);
    $isFull = $selectedCount >= $maxPlayers;
    // Le pool est déplié quand il manque quelqu'un, replié sinon : c'est le seul
    // état où sa longueur coûte sans rien apporter.
    $poolOpen = ! $isFull;
    $poolHasSomethingToSay = count($poolRows) > 0 || count($poolWaiting) > 0
        || $poolHiddenCount > 0 || $poolMaybeCount > 0;
@endphp

<x-drawer class="w-11/12 lg:w-2/5" right separator
    :title="$title"
    :subtitle="$subtitle"
    :wire:model="$model" with-close-button>
    <div class="space-y-6">

        {{-- Progress --}}
        <div>
            <div class="mb-2 flex justify-between text-xs font-bold uppercase">
                <span>{{ __('Selected') }}</span>
                <span @class([
                    'font-bold',
                    'text-success' => $selectedCount == $maxPlayers,
                    'text-warning-content' => $selectedCount > 0 && $selectedCount < $maxPlayers,
                    'text-base-content/60' => $selectedCount === 0,
                ])>{{ $selectedCount }} / {{ $maxPlayers }}</span>
            </div>
            <progress @class([
                'progress w-full h-2 transition-all duration-500',
                'progress-success' => $selectedCount == $maxPlayers,
                'progress-warning' => $selectedCount > 0 && $selectedCount < $maxPlayers,
                'progress-primary' => $selectedCount === 0,
            ]) max="{{ $maxPlayers }}" value="{{ $selectedCount }}"></progress>
        </div>

        {{-- Roster --}}
        <div>
            <div class="mb-3 text-xs font-bold uppercase tracking-widest opacity-60">{{ __('Team roster') }}</div>
            @if ($isFull)
                <p class="mb-3 text-xs text-base-content/70">
                    {{ __('Lineup full. Untick a player to free a spot.') }}
                </p>
            @endif
            <div class="space-y-1.5">
                @foreach ($roster as $player)
                    @php
                        $isSelected  = in_array($player['id'], $selectedIds);
                        $avail       = $player['availability'];
                        $isUnavail   = $avail === \App\Domains\Shared\Enums\InterclubAvailability::UNAVAILABLE;
                        $isBlocked   = $player['is_blocked'] ?? false;
                        $blockedTeam = $player['blocked_team'] ?? null;
                        // Une compo pleine ne refuse plus un geste : elle cesse
                        // de le proposer. Décocher reste toujours possible.
                        $isRefused   = $isFull && ! $isSelected;
                        // Décision 20 : on masque ce qu'on n'a jamais promis, on
                        // désactive ce qu'on a déjà montré. Un joueur de son
                        // propre effectif que l'on ferait disparaître passerait
                        // pour un bug, pas pour une règle.
                        $isIllegal   = ($player['is_illegal'] ?? false) && ! $isSelected;
                        $ruleNote    = $player['legality_reason'] ?? null;
                    @endphp
                    {{-- La ligne porte les numéros de téléphone et l'e-mail du joueur :
                         ni un <button> ni un <label> ne peuvent envelopper des liens. Elle
                         restait donc un <div> avec la seule case à cocher pour commande,
                         tout en s'allumant au survol comme si elle était cliquable : elle
                         promettait un geste qu'elle ne tenait pas, et sur 375 px la cible
                         était un carré au bout d'une ligne encombrée.
                         D'où le recouvrement : la commande reste le <label> de la case
                         (règle KB-1, focus et clavier inchangés), mais son ::before
                         s'étend sur toute la carte. Les deux liens de contact repassent
                         au-dessus en z-10, donc ils composent et écrivent toujours. --}}
                    {{-- La clé porte l'état, pas seulement l'identité : le
                         navigateur bascule la *propriété* `checked`, Livewire
                         rend l'*attribut*. Quand les deux coïncident, morphdom
                         ne touche à rien et la case garde la valeur du clic
                         même si le serveur a dit non. Une clé qui change force
                         le remplacement du nœud. --}}
                    <div
                        data-roster-row
                        wire:key="roster-{{ $fixtureId }}-{{ $player['id'] }}-{{ $isSelected ? 1 : 0 }}"
                        @class([
                            'relative flex items-center gap-3 rounded-xl border p-3 transition-all',
                            'cursor-not-allowed' => $isBlocked || $isIllegal,
                            'opacity-60' => $isRefused,
                            'border-primary bg-primary/5 ring-1 ring-primary/40' => $isSelected && ! $isBlocked,
                            'border-base-300 bg-base-50 opacity-60' => $isBlocked,
                            'border-base-300 hover:border-primary/40 bg-base-100' => ! $isSelected && ! $isBlocked,
                        ])>

                        {{-- Rank chip --}}
                        <div @class([
                            'w-10 shrink-0 rounded-lg py-1.5 text-center text-sm font-bold tabular-nums',
                            'bg-primary text-primary-content' => $isSelected,
                            'bg-error/20 text-error' => $isUnavail && ! $isSelected,
                            'bg-base-200 text-base-content/70' => ! $isSelected && ! $isUnavail,
                        ])>{{ $player['rank'] }}</div>

                        {{-- Name + availability + note --}}
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-bold">{{ $player['name'] }}</div>
                            <div class="mt-0.5 flex items-center gap-1">
                                @if ($isBlocked)
                                    <x-icon name="o-no-symbol" class="h-3 w-3 text-error" />
                                    <span class="text-xs font-bold text-error">
                                        {{ __('Already in lineup – W:n', ['n' => $weekNumber]) }}
                                        @if ($canSearchSubstitute && $blockedTeam)
                                            · {{ __('Team') }}&nbsp;{{ $blockedTeam }}
                                        @endif
                                    </span>
                                @elseif ($avail)
                                    <span class="{{ $avail->color() }} badge badge-sm font-bold">{{ $avail->label() }}</span>
                                @else
                                    <span class="text-xs opacity-60">{{ __('No response') }}</span>
                                @endif
                            </div>
                            @if (! empty($player['availability_note']))
                                <div class="mt-0.5 text-xs italic opacity-60">"{{ $player['availability_note'] }}"</div>
                            @endif
                            @if ($ruleNote)
                                <div @class([
                                    'mt-0.5 flex items-start gap-1 text-xs font-semibold',
                                    'text-error' => $isIllegal,
                                    'text-warning-content' => ! $isIllegal,
                                ])>
                                    <x-icon name="o-scale" class="mt-0.5 h-3 w-3 shrink-0" />
                                    <span>{{ $ruleNote }}</span>
                                </div>
                            @endif
                            {{-- Captain override: contact details of own players (T8) --}}
                            @if (! empty($player['phone_number']) || ! empty($player['email']))
                                <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                    @if (! empty($player['phone_number']))
                                        <a href="tel:{{ $player['phone_number'] }}" @click.stop
                                            data-roster-contact
                                            class="relative z-10 inline-flex items-center gap-1 text-xs font-semibold text-base-content/60 hover:text-primary">
                                            <x-icon name="o-phone" class="h-2.5 w-2.5" />{{ $player['phone_number'] }}
                                        </a>
                                    @endif
                                    @if (! empty($player['email']))
                                        <a href="mailto:{{ $player['email'] }}" @click.stop
                                            data-roster-contact
                                            class="relative z-10 inline-flex items-center gap-1 truncate text-xs font-semibold text-base-content/60 hover:text-primary">
                                            <x-icon name="o-envelope" class="h-2.5 w-2.5 shrink-0" />{{ $player['email'] }}
                                        </a>
                                    @endif
                                </div>
                            @endif
                        </div>

                        {{-- Stats: joués | sél. --}}
                        <div class="flex shrink-0 overflow-hidden rounded-lg border border-base-300 text-center">
                            <div class="flex flex-col items-center px-3 py-1.5">
                                <span class="text-sm font-bold tabular-nums leading-none">{{ $player['matches_played'] }}</span>
                                <span class="mt-0.5 text-xs font-bold uppercase opacity-60">{{ __('played') }}</span>
                            </div>
                            <div class="self-stretch w-px bg-base-200"></div>
                            <div class="flex flex-col items-center px-3 py-1.5">
                                <span class="text-sm font-bold tabular-nums leading-none">{{ $player['matches_selected'] }}</span>
                                <span class="mt-0.5 text-xs font-bold uppercase opacity-60">{{ __('sel.') }}</span>
                            </div>
                        </div>

                        {{-- Checkbox / lock --}}
                        @if ($isBlocked || $isIllegal)
                            <x-icon name="o-lock-closed" class="h-4 w-4 shrink-0 text-error/50" />
                        @else
                            {{-- 44 px reste la cible de confort de l'Apple HIG pour la case
                                 elle-même ; le ::before étend la même commande à toute la
                                 carte, sans déplacer un pixel de ce qui est peint. --}}
                            <label
                                data-roster-toggle
                                @class([
                                    '-m-2 flex h-11 w-11 shrink-0 items-center justify-center',
                                    "before:absolute before:inset-0 before:content-['']" => ! $isRefused,
                                    'cursor-pointer' => ! $isRefused,
                                    'cursor-not-allowed' => $isRefused,
                                ])>
                                <input type="checkbox"
                                    class="checkbox checkbox-primary checkbox-sm h-6 w-6"
                                    aria-label="{{ __('Select :player', ['player' => $player['name']]) }}"
                                    @checked($isSelected)
                                    @if ($isRefused) disabled="disabled" @endif
                                    wire:loading.attr="disabled"
                                    wire:target="togglePlayer({{ $player['id'] }})"
                                    wire:click="togglePlayer({{ $player['id'] }})" />
                            </label>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ── JOUEURS LIBRES ────────────────────────────────────────────
             Les disponibles que les capitaines des équipes sœurs n'ont pas
             retenus, une fois leur composition publiée. Le pool ne montre que
             ce que l'article C.22 permet d'aligner : ce qu'il masque, il le
             compte, sans quoi la liste paraîtrait simplement vide. --}}
        @if ($poolHasSomethingToSay)
            <details class="border-t border-dashed border-base-300 pt-4" @if ($poolOpen) open @endif>
                <summary class="flex cursor-pointer list-none items-center justify-between gap-2">
                    <span class="text-xs font-bold uppercase tracking-widest opacity-60">
                        {{ __('Free players this match day') }}
                    </span>
                    <span class="badge badge-sm font-bold tabular-nums">{{ count($poolRows) }}</span>
                </summary>

                <div class="mt-3 space-y-2">
                    @forelse ($poolRows as $candidate)
                        @php $isSelected = in_array($candidate['id'], $selectedIds); @endphp
                        <div wire:key="pool-{{ $fixtureId }}-{{ $candidate['id'] }}-{{ $isSelected ? 1 : 0 }}"
                            @class([
                                'relative flex items-center gap-3 rounded-xl border border-dashed p-3 transition-all',
                                'border-primary bg-primary/5' => $isSelected,
                                'border-base-300 bg-base-100 hover:border-primary/40' => ! $isSelected,
                                'opacity-60' => $isFull && ! $isSelected,
                            ])>

                            <div class="w-10 shrink-0 rounded-lg bg-base-200 py-1.5 text-center text-sm font-bold tabular-nums text-base-content/70">
                                {{ $candidate['rank'] }}
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="text-xs font-bold">{{ $candidate['name'] }}</div>

                                <div class="mt-0.5 flex flex-wrap items-center gap-1">
                                    {{-- D'où il vient : « B0, disponible » ne dit pas si l'on
                                         emprunte juste au-dessus ou trois divisions plus bas. --}}
                                    <span class="badge badge-ghost badge-sm font-bold">
                                        {{ __('Team') }} {{ $candidate['origin_team'] }}
                                    </span>
                                    @if ($candidate['availability'])
                                        <span class="{{ $candidate['availability']->color() }} badge badge-sm font-bold">
                                            {{ $candidate['availability']->label() }}
                                        </span>
                                    @endif
                                    @if ($candidate['force_index'] !== null)
                                        <span class="text-xs font-semibold tabular-nums opacity-60">#{{ $candidate['force_index'] }}</span>
                                    @endif
                                </div>

                                @if (! empty($candidate['availability_note']))
                                    {{-- Citée comme ce qu'elle est : une note laissée à un autre
                                         capitaine, pour une autre rencontre. --}}
                                    <div class="mt-0.5 text-xs italic opacity-60">
                                        {{ __('Note left to team :team', ['team' => $candidate['origin_team']]) }} :
                                        "{{ $candidate['availability_note'] }}"
                                    </div>
                                @endif

                                @if ($candidate['legality_reason'])
                                    <div class="mt-0.5 flex items-start gap-1 text-xs font-semibold text-warning-content">
                                        <x-icon name="o-scale" class="mt-0.5 h-3 w-3 shrink-0" />
                                        <span>{{ $candidate['legality_reason'] }}</span>
                                    </div>
                                @endif

                                @if (! empty($candidate['phone_number']) || ! empty($candidate['email']))
                                    <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                        @if (! empty($candidate['phone_number']))
                                            <a href="tel:{{ $candidate['phone_number'] }}" @click.stop
                                                class="relative z-10 inline-flex items-center gap-1 text-xs font-semibold text-base-content/60 hover:text-primary">
                                                <x-icon name="o-phone" class="h-2.5 w-2.5" />{{ $candidate['phone_number'] }}
                                            </a>
                                        @endif
                                        @if (! empty($candidate['email']))
                                            <a href="mailto:{{ $candidate['email'] }}" @click.stop
                                                class="relative z-10 inline-flex items-center gap-1 truncate text-xs font-semibold text-base-content/60 hover:text-primary">
                                                <x-icon name="o-envelope" class="h-2.5 w-2.5 shrink-0" />{{ $candidate['email'] }}
                                            </a>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            {{-- Combien de fois il a déjà joué chez nous : un emprunt répété
                                 finit par peser sur la feuille de match. --}}
                            <div class="flex shrink-0 flex-col items-center rounded-lg border border-base-300 px-3 py-1.5 text-center">
                                <span class="text-sm font-bold tabular-nums leading-none">{{ $candidate['played_for_us'] }}</span>
                                <span class="mt-0.5 text-xs font-bold uppercase opacity-60">{{ __('with us') }}</span>
                            </div>

                            <label @class([
                                '-m-2 flex h-11 w-11 shrink-0 items-center justify-center',
                                "before:absolute before:inset-0 before:content-['']" => ! ($isFull && ! $isSelected),
                                'cursor-pointer' => ! ($isFull && ! $isSelected),
                                'cursor-not-allowed' => $isFull && ! $isSelected,
                            ])>
                                <input type="checkbox"
                                    class="checkbox checkbox-primary checkbox-sm h-6 w-6"
                                    aria-label="{{ __('Select :player', ['player' => $candidate['name']]) }}"
                                    @checked($isSelected)
                                    @if ($isFull && ! $isSelected) disabled="disabled" @endif
                                    wire:loading.attr="disabled"
                                    wire:target="togglePlayer({{ $candidate['id'] }})"
                                    wire:click="togglePlayer({{ $candidate['id'] }})" />
                            </label>
                        </div>
                    @empty
                        <p class="px-1 text-xs opacity-60">{{ __('Nobody is free in this category for this match day.') }}</p>
                    @endforelse

                    {{-- Ce que la règle a retiré de la liste. Sans ce compte, un pool
                         filtré et un pool vide se ressemblent trait pour trait. --}}
                    @if ($poolHiddenCount > 0)
                        <p class="px-1 text-xs opacity-60">
                            {{ trans_choice('{1} :count player hidden: too strong for this team under rule C.22.|[2,*] :count players hidden: too strong for this team under rule C.22.', $poolHiddenCount, ['count' => $poolHiddenCount]) }}
                        </p>
                    @endif

                    {{-- Un pool vide a deux causes opposées et le même aspect : tout le
                         monde joue, ou personne n'a encore composé. On dit laquelle,
                         et à qui téléphoner. --}}
                    @foreach ($poolWaiting as $waiting)
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 rounded-lg bg-warning/10 p-3 text-xs text-warning-content">
                            <x-icon name="o-clock" class="h-4 w-4 shrink-0" />
                            <span class="font-semibold">
                                {{ trans_choice(
                                    '{1} Team :team is still holding :count available player.|[2,*] Team :team is still holding :count available players.',
                                    $waiting->availableCount,
                                    ['team' => $waiting->team->name, 'count' => $waiting->availableCount],
                                ) }}
                            </span>
                            @if ($waiting->team->captain)
                                <span class="opacity-80">
                                    {{ $waiting->team->captain->last_name }} {{ $waiting->team->captain->first_name }}
                                </span>
                                @if ($waiting->team->captain->phone_number)
                                    <a href="tel:{{ $waiting->team->captain->phone_number }}"
                                        class="relative z-10 inline-flex items-center gap-1 font-semibold underline">
                                        <x-icon name="o-phone" class="h-3 w-3" />{{ $waiting->team->captain->phone_number }}
                                    </a>
                                @endif
                                @if ($waiting->team->captain->email)
                                    <a href="mailto:{{ $waiting->team->captain->email }}"
                                        class="relative z-10 inline-flex items-center gap-1 truncate font-semibold underline">
                                        <x-icon name="o-envelope" class="h-3 w-3 shrink-0" />{{ $waiting->team->captain->email }}
                                    </a>
                                @endif
                            @endif
                        </div>
                    @endforeach

                    {{-- Un « peut-être » reste une piste à J-2, mais ce n'est pas un oui :
                         il vit sous le pool, jamais dedans. --}}
                    @if ($poolMaybeCount > 0)
                        <p class="px-1 text-xs opacity-60">
                            {{ trans_choice(
                                '{1} :count player answered "maybe" in team :teams.|[2,*] :count players answered "maybe" in teams :teams.',
                                $poolMaybeCount,
                                ['count' => $poolMaybeCount, 'teams' => implode(', ', $poolMaybeTeams)],
                            ) }}
                        </p>
                    @endif

                    {{-- Décision 18 : un nom d'équipe qui ne se range pas désactive la
                         règle pour la catégorie, et le dit plutôt que de calculer sur
                         un ordre supposé. --}}
                    @if ($lineupConstraint && ! $lineupConstraint->rankIsReadable)
                        <div class="flex items-start gap-2 rounded-lg bg-base-200 p-3 text-xs">
                            <x-icon name="o-information-circle" class="mt-0.5 h-4 w-4 shrink-0" />
                            <span>{{ __('Team order cannot be read from the team names — rule C.22 is not checked here.') }}</span>
                        </div>
                    @endif
                </div>
            </details>
        @endif

        {{-- Search substitute (admin / selector only) --}}
        @if ($canSearchSubstitute)
            <div class="border-t border-dashed border-base-300 pt-4">
                <div class="mb-3 text-xs font-bold uppercase tracking-widest opacity-60">
                    {{ __('Search a substitute') }}
                </div>
                <x-input class="input-sm rounded-lg border-none bg-base-200/50" icon="o-magnifying-glass"
                    :placeholder="__('Player name...')" wire:model.live.debounce.300ms="{{ $searchModel }}" />
                @if (strlen((string) $searchTerm) >= 2)
                    <div class="animate-in fade-in slide-in-from-top-2 mt-4 space-y-2">
                        @forelse($searchResults as $res)
                            @php $isSelected = in_array($res['id'], $selectedIds); @endphp
                            <div @class([
                                'flex cursor-pointer items-center justify-between rounded-lg border border-dashed p-2 transition-all',
                                'border-primary bg-primary/5' => $isSelected,
                                'border-base-300 hover:border-primary' => ! $isSelected,
                            ]) wire:click="togglePlayer({{ $res['id'] }})">
                                <div class="flex items-center gap-2">
                                    <x-icon class="h-4 w-4 opacity-60" name="o-user-plus" />
                                    <div class="flex flex-col">
                                        <span class="text-sm font-bold">{{ $res['name'] }}</span>
                                        <span class="text-xs uppercase opacity-60">{{ $res['rank'] }}</span>
                                    </div>
                                </div>
                                @if ($isSelected)
                                    <x-icon class="h-5 w-5 text-primary" name="o-check-circle" />
                                @endif
                            </div>
                        @empty
                            @if ($searchNote)
                                <div class="flex items-start gap-2 rounded-lg bg-warning/10 p-3 text-xs text-warning-content">
                                    <x-icon name="o-information-circle" class="mt-0.5 h-4 w-4 shrink-0" />
                                    <span>{{ $searchNote }}</span>
                                </div>
                            @else
                                <div class="p-4 text-center text-xs opacity-60">{{ __('No player found.') }}</div>
                            @endif
                        @endforelse
                    </div>
                @endif
            </div>
        @endif
    </div>

    <x-slot:actions>
        <x-button x-on:click="$wire.set('{{ $model }}', false)" class="btn-ghost" :label="__('Cancel')" />
        {{-- `spinner` ne fait pas qu'afficher une roue : il pose aussi
             `wire:loading.attr="disabled"`, donc il répond au deuxième clic
             autant qu'au silence du premier. --}}
        <x-button
            :disabled="$selectedCount === 0"
            class="btn-primary"
            icon="o-check"
            :label="$saveLabel"
            spinner="{{ $saveAction }}"
            wire:click="{{ $saveAction }}" />
    </x-slot:actions>
</x-drawer>