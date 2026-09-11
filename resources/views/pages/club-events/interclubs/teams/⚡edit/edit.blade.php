<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator
        :title="'Modifier — ' . ($team->club?->name ?? '') . ' ' . $team->name">
        <x-slot:actions>
            <x-button class="btn-ghost" link="{{ route('admin.interclubs.teams.show', $team->id) }}"
                icon="o-arrow-left" label="Retour" />
        </x-slot:actions>
    </x-header>

    <div class="grid gap-6 lg:grid-cols-3">

        {{-- ── Colonne gauche : nom + capitaine ──────────────────────── --}}
        <div class="space-y-5 lg:col-span-1">

            {{-- Lettre de l'équipe --}}
            <x-card class="shadow-sm" :title="__('Identity')">
                <div class="space-y-4">
                    <x-select
                        :label="__('Team letter')"
                        :options="$teamNameOptions"
                        wire:model="name"
                        placeholder="A – Z" />

                    @if ($scheduledMatchCount === 0)
                        @if ($newDivisionMode)
                            <x-select :label="__('Category')" :options="$categoryOptions" wire:model="newCategory"
                                placeholder="Sélectionner..." />
                            <x-select label="Niveau" :options="$levelOptions" wire:model="newLevel"
                                placeholder="Sélectionner..." />
                            <x-input :label="__('Division')" wire:model="newDivision" placeholder="ex: 3B" />
                            <x-button class="btn-ghost btn-xs" icon="o-arrow-uturn-left"
                                :label="__('Choose an existing division')"
                                wire:click="$set('newDivisionMode', false)" />
                        @else
                            <x-select
                                :label="__('Division')"
                                :options="$leagueOptions"
                                wire:model="leagueId"
                                :placeholder="__('Select a division')" />
                            <x-button class="btn-ghost btn-xs" icon="o-plus"
                                :label="__('Create a new division')"
                                wire:click="$set('newDivisionMode', true)" />
                        @endif
                    @else
                        <div class="rounded-lg bg-gray-50 p-3 text-sm text-gray-600">
                            <p class="font-medium text-gray-800">{{ __('Division') }}</p>
                            <p class="mt-1">{{ $division }}</p>
                            <p class="mt-2 flex items-start gap-1.5 text-xs text-gray-500">
                                <x-icon name="o-lock-closed" class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                <span>{{ trans_choice('Locked: :count match is already scheduled for this team. Change the schedule first.|Locked: :count matches are already scheduled for this team. Change the schedule first.', $scheduledMatchCount, ['count' => $scheduledMatchCount]) }}</span>
                            </p>
                        </div>
                    @endif

                    <div class="text-xs text-gray-400">
                        Saison : <span class="font-medium text-gray-600">{{ $team->season?->name ?? '—' }}</span>
                    </div>
                </div>
            </x-card>

            {{-- Capitaine --}}
            <x-card class="shadow-sm" :title="__('Captain')">
                @if ($captainId)
                    <div class="mb-4 flex items-center gap-3 rounded-lg bg-yellow-50 p-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-yellow-200 text-sm font-bold text-yellow-800">
                            {{ mb_strtoupper(substr($captainUser?->first_name ?? '?', 0, 1)) }}{{ strtoupper(substr($captainUser?->last_name ?? '', 0, 1)) }}
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-gray-900">
                                {{ $captainUser?->first_name }} {{ $captainUser?->last_name }}
                            </p>
                            @if ($captainUser?->ranking)
                                <p class="text-xs text-gray-500">{{ $captainUser->ranking->getLabel() }}</p>
                            @endif
                        </div>
                        <x-button class="btn-ghost btn-xs text-gray-400 hover:text-red-500"
                            icon="o-x-mark" wire:click="removeCaptain" />
                    </div>
                @else
                    <p class="mb-4 text-sm text-gray-400 italic">{{ __('No captain designated.') }}</p>
                @endif

                @if ($captainNeedsEmailConfirmation || $captainNeedsProfile)
                    <div class="mb-4 space-y-1 rounded-lg border border-warning/30 bg-warning/10 p-3 text-xs text-warning-content">
                        @if ($captainNeedsEmailConfirmation)
                            <p>{{ __('This captain has not confirmed their email address yet.') }}</p>
                        @endif
                        @if ($captainNeedsProfile)
                            <p>{{ __('This captain must complete their profile before they can compose a lineup.') }}</p>
                        @endif
                    </div>
                @endif

                {{-- Le vivier, c'est le club entier : un bénévole non affilié capitaine
                     aussi bien qu'un joueur du noyau. À 318 membres, une liste à plat
                     ne tient plus — recherche vide, le noyau remonte en tête. --}}
                <x-choices wire:model.live="captainId" :label="__('Designate a captain')"
                    single searchable :options="$captainOptions"
                    :placeholder="__('Search a member…')" />

            </x-card>
        </div>

        {{-- ── Colonne droite : composition du noyau ──────────────────── --}}
        <x-card class="shadow-sm lg:col-span-2" :title="__('Composition of the core')">
            <x-slot:subtitle>
                <span class="text-sm text-gray-500">
                    {{ count($memberIds) }} joueur{{ count($memberIds) > 1 ? 's' : '' }} sélectionné{{ count($memberIds) > 1 ? 's' : '' }}
                </span>
            </x-slot:subtitle>

            <x-input
                class="mb-4"
                clearable
                icon="o-magnifying-glass"
                :placeholder="__('Search for a competitor…')"
                wire:model.live.debounce.250ms="memberSearch" />

            @error('memberIds')
                <p class="mb-4 text-sm text-error">{{ $message }}</p>
            @enderror

            {{-- Le noyau reste sous les yeux : paginer les candidats sans le
                 rappeler reviendrait à cacher sa propre sélection. --}}
            @if ($teamMembers->isNotEmpty())
                <div class="mb-4 rounded-lg border border-base-300 bg-base-200/40 p-3">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-base-content/50">
                        {{ __('Current core') }}
                    </p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($teamMembers as $member)
                            <button type="button" wire:key="core-{{ $member->id }}"
                                wire:click="toggleMember({{ $member->id }})"
                                class="badge badge-primary badge-soft gap-1 cursor-pointer">
                                {{ $member->first_name }} {{ $member->last_name }}
                                <x-heroicon-s-x-mark class="h-3 w-3" />
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="divide-y divide-gray-100">
                @forelse ($competitors as $user)
                    @php $selected = in_array($user->id, $memberIds); @endphp
                    <button type="button"
                        wire:key="competitor-{{ $user->id }}"
                        aria-pressed="{{ $selected ? 'true' : 'false' }}"
                        class="flex w-full cursor-pointer items-center gap-3 rounded-lg px-2 py-2.5 text-left transition
                            {{ $selected ? 'bg-blue-50' : 'hover:bg-gray-50' }}"
                        wire:click="toggleMember({{ $user->id }})">

                        {{-- Checkbox visuel --}}
                        <div class="flex h-5 w-5 shrink-0 items-center justify-center rounded border-2 transition
                            {{ $selected ? 'border-blue-500 bg-blue-500' : 'border-base-300 bg-white' }}">
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
                                    <span class="ml-1.5 rounded bg-yellow-100 px-1.5 py-0.5 text-xs font-semibold text-yellow-700">Cap.</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                {{-- Déjà pris dans la catégorie : on le dit, on ne le cache pas.
                                     Un candidat qui disparaît passe pour inéligible. --}}
                                @if (! $selected && isset($heldElsewhere[$user->id]))
                                    <span class="badge badge-warning badge-soft badge-sm whitespace-nowrap">
                                        {{ __('In team :team', ['team' => $heldElsewhere[$user->id]]) }}
                                    </span>
                                @endif

                                @if ($user->ranking)
                                    <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-500">
                                        {{ $user->ranking->getLabel() }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </button>
                @empty
                    <p class="py-6 text-center text-sm text-gray-400 italic">{{ __('No results.') }}</p>
                @endforelse
            </div>

            <div class="mt-4">
                {{ $competitors->links() }}
            </div>
        </x-card>
    </div>

    {{-- Le déplacement nomme l'équipe dépossédée : c'est l'opérateur qui porte
         la décision, il n'y a pas de notification derrière. --}}
    <x-confirm-modal model="showMoveModal" :title="__('Move this player?')"
        :confirmLabel="__('Move')" confirmClass="btn-warning"
        confirmAction="confirmMove" cancelAction="cancelMove" :open="$showMoveModal">
        @if ($pendingMove)
            <p>
                {{ __(':player currently holds a place in team :from. Move them to team :to?', [
                    'player' => $pendingMove['playerName'],
                    'from' => $pendingMove['teamName'],
                    'to' => $team->name,
                ]) }}
            </p>
            <p class="mt-2 text-sm text-gray-500">
                {{ __('Team :from will lose the player when you save this form.', ['from' => $pendingMove['teamName']]) }}
            </p>
        @endif
    </x-confirm-modal>

    {{-- ── Actions ──────────────────────────────────────────────────────── --}}
    <div class="mt-6 flex justify-end gap-3">
        <x-button class="btn-ghost" link="{{ route('admin.interclubs.teams.show', $team->id) }}" label="Annuler" />
        <x-button class="btn-primary" icon="o-check" label="Enregistrer" wire:click="save"
            wire:loading.attr="disabled" />
    </div>
</div>
