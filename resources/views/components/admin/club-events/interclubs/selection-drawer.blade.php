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
    'canSearchSubstitute' => false,
    'searchResults' => [],
    'searchNote' => null,
    'searchModel' => 'search',
    'searchTerm' => '',
    'saveAction' => 'saveSelection',
    'saveLabel' => null,
])

@php
    $title ??= __('Selection');
    $saveLabel ??= __('Save selection');
    $selectedCount = count($selectedIds);
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
            <div class="space-y-1.5">
                @foreach ($roster as $player)
                    @php
                        $isSelected  = in_array($player['id'], $selectedIds);
                        $avail       = $player['availability'];
                        $isUnavail   = $avail === \App\Domains\Shared\Enums\InterclubAvailability::UNAVAILABLE;
                        $isBlocked   = $player['is_blocked'] ?? false;
                        $blockedTeam = $player['blocked_team'] ?? null;
                    @endphp
                    {{-- La ligne porte les numéros de téléphone et l'e-mail du joueur :
                         ni un <button> ni un <label> ne peuvent envelopper des liens. La
                         case à cocher reste donc l'unique commande — et elle porte une
                         vraie cible de 44 px (règle KB-1 : pas de commande souris-seule sur
                         un <div>). Le curseur ne promet plus une ligne cliquable qu'il
                         n'était pas. --}}
                    <div
                        @class([
                            'flex items-center gap-3 rounded-xl border p-3 transition-all',
                            'cursor-not-allowed' => $isBlocked,
                            'border-primary bg-primary/5 ring-1 ring-primary/40' => $isSelected && ! $isBlocked,
                            'border-base-200 bg-base-50 opacity-60' => $isBlocked,
                            'border-base-200 hover:border-primary/40 bg-base-100' => ! $isSelected && ! $isBlocked,
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
                            {{-- Captain override: contact details of own players (T8) --}}
                            @if (! empty($player['phone_number']) || ! empty($player['email']))
                                <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                    @if (! empty($player['phone_number']))
                                        <a href="tel:{{ $player['phone_number'] }}" @click.stop
                                            class="inline-flex items-center gap-1 text-xs font-semibold text-base-content/60 hover:text-primary">
                                            <x-icon name="o-phone" class="h-2.5 w-2.5" />{{ $player['phone_number'] }}
                                        </a>
                                    @endif
                                    @if (! empty($player['email']))
                                        <a href="mailto:{{ $player['email'] }}" @click.stop
                                            class="inline-flex items-center gap-1 truncate text-xs font-semibold text-base-content/60 hover:text-primary">
                                            <x-icon name="o-envelope" class="h-2.5 w-2.5 shrink-0" />{{ $player['email'] }}
                                        </a>
                                    @endif
                                </div>
                            @endif
                        </div>

                        {{-- Stats: joués | sél. --}}
                        <div class="flex shrink-0 overflow-hidden rounded-lg border border-base-200 text-center">
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
                        @if ($isBlocked)
                            <x-icon name="o-lock-closed" class="h-4 w-4 shrink-0 text-error/50" />
                        @else
                            {{-- 44 px est la cible de confort de l'Apple HIG ; la case elle-même
                                 en fait 24, l'étiquette autour lui donne le reste. --}}
                            <label class="-m-2 flex h-11 w-11 shrink-0 cursor-pointer items-center justify-center">
                                <input type="checkbox"
                                    class="checkbox checkbox-primary checkbox-sm h-6 w-6"
                                    aria-label="{{ __('Select :player', ['player' => $player['name']]) }}"
                                    @checked($isSelected)
                                    wire:click="togglePlayer({{ $player['id'] }})" />
                            </label>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

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
        <x-button
            :disabled="$selectedCount === 0"
            class="btn-primary"
            icon="o-check"
            :label="$saveLabel"
            wire:click="{{ $saveAction }}" />
    </x-slot:actions>
</x-drawer>