@if ($this->isLaunched)
    <div class="mt-6 flex items-center gap-3 p-4 rounded-xl bg-base-200 border border-base-300 text-sm">
        <x-icon name="o-lock-closed" class="w-5 h-5 shrink-0 text-base-content/40" />
        <p class="text-base-content/60">{{ __('The tournament has been launched. Configuration is read-only.') }}</p>
    </div>
@endif

{{--
    The sections stack; each one owns its own two-column grid since 5db5a83c.
    A `grid grid-cols-6` here made every section — and every separator between
    them — a grid item one sixth of the width, so the three sections lined up in
    ~160px columns and every label overlapped its own field.

    `pointer-events-none` ne bloquait ni la tabulation ni la soumission : un
    tournoi lancé restait modifiable au clavier. `<fieldset disabled>` désactive
    vraiment chaque champ. `min-w-0` neutralise le `min-width: min-content` que
    les navigateurs imposent aux fieldset et que Preflight ne réinitialise pas :
    sans lui, le bloc refuserait de rétrécir sous la largeur de son contenu.
--}}
<fieldset @disabled($this->isLaunched)
    @class(['mt-8 grid min-w-0 grid-cols-1 gap-6 xl:grid-cols-3', 'opacity-60' => $this->isLaunched])>

    {{-- Repli du panneau : sous xl, la colonne de droite passe sous le formulaire.
         Le conteneur disparaît avec la barre, sinon il laisserait une rangée
         de grille vide -- et son gap -- en haut de l'étape. --}}
    <div class="xl:hidden">
        @include('components.admin.club-events.tournaments.partials.steps.simulation-bar')
    </div>

    <div class="flex min-w-0 flex-col gap-4 md:gap-6 xl:col-span-2">

        {{-- ── Section 1 : Details ─────────────────────────────────────────── --}}
        <x-admin.shared.form-section stacked :title="__('Details')"
            :subtitle="__('Define the framework for your competition: location, date and rules of the game.')">

            <div class="lg:col-span-2 space-y-6">
                <div class="grid md:grid-cols-2 gap-5">

                    {{-- Name — locked after validation --}}
                    <div class="md:col-span-2">
                        <x-input :label="__('Tournament name(*)')" :placeholder="__('Ex: Spring Grand Prix')" icon="o-trophy"
                            wire:model.live.debounce.500ms="name"
                            :readonly="$this->isContractLocked"
                            :hint="$this->isContractLocked ? __('Locked — tournament validated') : null" />
                    </div>

                    {{-- Rooms — always editable, notification if players registered --}}
                    <div class="relative">
                        <x-choices :label="__('Room(s)(*)')" wire:model.live="selectedRooms" :options="$this->availableRooms"
                            icon="o-map-pin" />
                        @if ($this->hasRegisteredUsers)
                            <span class="absolute top-0 right-0 text-xs text-warning-content font-medium flex items-center gap-0.5">
                                <x-icon name="o-bell-alert" class="w-3 h-3" /> {{ __('Will notify') }}
                            </span>
                        @endif
                    </div>

                    {{-- Location — used on the public event page --}}
                    <x-input
                        :label="__('Location (public)')"
                        :placeholder="__('e.g. Club House, Rue des Sports 1, Ottignies')"
                        :hint="__('Displayed on the website event page.')"
                        icon="o-map-pin"
                        wire:model="eventLocation"
                    />

                    {{-- Date — always editable, notification if players registered --}}
                    <div class="relative">
                        <x-datepicker :label="__('Date(*)')" icon="o-calendar"
                            wire:model="tournamentDate" type="date" />
                        @if ($this->hasRegisteredUsers)
                            <span class="absolute top-0 right-0 text-xs text-warning-content font-medium flex items-center gap-0.5">
                                <x-icon name="o-bell-alert" class="w-3 h-3" /> {{ __('Will notify') }}
                            </span>
                        @endif
                    </div>

                    {{-- Time — always editable, notification if players registered --}}
                    <div class="relative">
                        <x-input :label="__('Start time(*)')" type="time" icon="o-clock" wire:model="startTime" />
                        @if ($this->hasRegisteredUsers)
                            <span class="absolute top-0 right-0 text-xs text-warning-content font-medium flex items-center gap-0.5">
                                <x-icon name="o-bell-alert" class="w-3 h-3" /> {{ __('Will notify') }}
                            </span>
                        @endif
                    </div>

                    {{-- Registration deadline --}}
                    <x-datepicker :label="__('Registration deadline(*)')" icon="o-calendar-days"
                        wire:model="registration_deadline" type="date"
                        :hint="__('Required before sending invitations')" />

                    {{-- Price — locked after validation --}}
                    <x-input :label="__('Registration fee')" suffix="€" type="number" icon="o-banknotes"
                        wire:model="price"
                        :readonly="$this->isContractLocked"
                        :hint="$this->isContractLocked ? __('Locked — tournament validated') : null" />

                    <div class="col-span-2">
                        <x-toggle :label="__('Open registrations')" icon="o-eye"
                            :hint="__('If chosen, this tournament will be open for external registration on our website.')"
                            wire:model.live="publicRegistration" />
                    </div>
                </div>
            </div>

        </x-admin.shared.form-section>

        {{-- ── Section 2 : Capacity & logistics ──────────────────────────────── --}}
        <x-admin.shared.form-section stacked :title="__('Capacity and logistics')"
            :subtitle="__('These physical parameters define the maximum number of playable matches. Everything else follows from this.')">

            <x-input wire:model.live.debounce.500ms="tournament_minutes" :label="__('Total duration')"
                type="number" icon="o-clock" suffix="min" :hint="__('Ex: 180 = 3 hours')" min="60"
                step="30" />

            {{-- nb_tables: auto from rooms, or manual if no rooms selected --}}
            @if (count($selectedRooms) > 0)
                <div>
                    <x-input :label="__('Available tables')" type="number" icon="o-table-cells"
                        :hint="__('Auto-computed from selected rooms')" :value="$this->nbTables" readonly />
                </div>
            @else
                <x-input wire:model.live.debounce.500ms="nb_tables" :label="__('Available tables')" type="number"
                    icon="o-table-cells" :hint="__('Select rooms above or enter manually')" min="1" />
            @endif

            <x-input wire:model.live.debounce.500ms="logistics_buffer" :label="__('Buffer between matches')"
                type="number" icon="o-arrows-right-left" suffix="min" :hint="__('Rotation, scoring, movement')"
                min="0" max="10" />

            {{-- Le plafond d'inscriptions suit la structure tant qu'on n'y touche pas.
                 Sans ce champ il n'était jamais saisissable, et « Places restantes »
                 affichait une valeur que personne n'avait choisie (issue #37). --}}
            <div>
                <x-input wire:model.live.debounce.500ms="maxUsers" :label="__('Maximum number of players')"
                    type="number" icon="o-user-group" min="0"
                    :hint="$maxUsersManual
                        ? __('Set by hand. 0 means no limit.')
                        : __('Follows the structure: :pools pools × :size players. Type a value to fix it.', [
                            'pools' => $nb_poules,
                            'size' => $pool_size,
                        ])" />

                @if ($maxUsersManual)
                    <x-button :label="__('Follow the structure again')" class="btn-ghost btn-xs mt-1"
                        icon="o-arrow-path" wire:click="resetMaxUsersToStructure" />
                @endif
            </div>

        </x-admin.shared.form-section>

        {{-- ── Section 3 : Rules & format ─────────────────────────────────────── --}}
        <x-admin.shared.form-section stacked :title="__('Rules and format')"
            :subtitle="__('Sport parameters directly impact the number and duration of matches.')">

            <x-select :label="__('Match type(*)')" icon="o-user" wire:model.live="matchType"
                :options="[['id' => 'single', 'name' => __('Singles')], ['id' => 'double', 'name' => __('Doubles')]]" />

            @if ($matchType === 'double')
                <div class="flex flex-col gap-2">
                    <p class="text-sm font-medium">{{ __('Pair registration mode') }}</p>
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model.live="doublesRegistrationMode" value="club" class="radio radio-sm radio-primary" />
                            <span class="text-sm">{{ __('Club composes pairs') }}</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model.live="doublesRegistrationMode" value="self" class="radio radio-sm radio-primary" />
                            <span class="text-sm">{{ __('Players choose partners') }}</span>
                        </label>
                    </div>
                    <p class="text-xs text-base-content/50">
                        @if ($doublesRegistrationMode === 'club')
                            {{ __('Admin composes pairs in the Registrations tab.') }}
                        @else
                            {{ __('Players pick their partner from their profile page.') }}
                        @endif
                    </p>
                </div>
            @endif

            <x-select wire:model.live.debounce.500ms="totalSets" :options="$this->setOptions"
                :label="__('Winning sets(*)')" icon="o-star"
                :hint="__('Best of :count', ['count' => ($this->totalSets * 2) - 1])" />
            <div class="flex flex-col gap-1">
                <x-toggle wire:model.live="deuceEnabled" :label="__('Deuce rule')" right />
                <p class="text-xs text-base-content/50 leading-tight">
                    @if ($deuceEnabled)
                        {{ __('Standard: win at 11 with a 2-point lead. At 10-10, play continues until +2 (e.g. 12-10).') }}
                    @else
                        {{ __('Simplified: first to 11 wins — 10-10 becomes 11-10. Faster matches.') }}
                    @endif
                </p>
                @if (! $deuceEnabled)
                    <p class="text-xs text-warning-content/80 font-semibold mt-0.5">
                        <x-icon name="o-bolt" class="mb-0.5 inline h-3.5 w-3.5" /> {{ __('Recommended for "Minimize duration" objective.') }}
                    </p>
                @endif
            </div>
            <div class="flex flex-col gap-1">
                <x-toggle wire:model.live="hasHandicapPoints" :label="__('Handicap points (AFTT)')" right />
                <p class="text-xs text-base-content/50 leading-tight">
                    {{ __('Each set starts with a score advantage based on player rankings. Ideal for friendly or mixed-level tournaments.') }}
                </p>
                @if (! $hasHandicapPoints)
                    <p class="text-xs text-warning-content/80 font-semibold mt-0.5">
                        <x-icon name="o-trophy" class="mb-0.5 inline h-3.5 w-3.5" /> {{ __('Disabled for "Competitive format" objective.') }}
                    </p>
                @endif
            </div>
            <x-input wire:model.live.debounce.500ms="nb_poules" :label="__('Number of pools(*)')"
                icon="o-calculator" type="number" min="1" />
            <x-select wire:model.live.debounce.500ms="pool_size" :label="__('Players per pool(*)')"
                icon="o-user-group" :options="$poolSizeOptions" :hint="__('Strong impact on match count')" />
            <x-input wire:model.live.debounce.500ms="nb_qualifies" :label="__('Qualified per pool(*)')"
                icon="o-trophy" type="number" :hint="__('Players advancing to the bracket')" min="1"
                numeric />


            <div class="lg:col-span-2">
                <x-textarea wire:model="description" :label="__('Additional information')" rows="4"
                    :placeholder="__('Specific rules, dress code...')" />
            </div>

        </x-admin.shared.form-section>

        {{-- ── Section 4 : Objective & Suggestion ────────────────────────────── --}}
        <x-admin.shared.form-section stacked :title="__('Optimization objective')"
            :subtitle="__('Let the assistant suggest the best configuration for your constraints.')">

            <div class="lg:col-span-2 space-y-4">
                <x-select :label="__('Tournament objective')" icon="o-light-bulb"
                    wire:model.live="selectedObjective"
                    :options="$objectiveOptions"
                    :placeholder="__('Choose an objective...')" />

                @if ($selectedObjective)
                    @php
                        $obj = \App\Domains\Shared\Enums\TournamentObjectiveEnum::tryFrom($selectedObjective);
                    @endphp
                    @if ($obj)
                        <div class="p-3 rounded-xl bg-primary/5 border border-primary/20 text-sm text-base-content/70">
                            {{ $obj->description() }}
                        </div>
                    @endif
                @endif

                <x-button
                    :label="__('Suggest configuration')"
                    icon="o-sparkles"
                    class="btn-primary btn-outline btn-sm"
                    wire:click="applyObjectiveSuggestion"
                    spinner="applyObjectiveSuggestion"
                    :disabled="empty($selectedObjective)" />
            </div>

        </x-admin.shared.form-section>


    </div>

    {{--
        Le simulateur reste sous les yeux pendant qu'on modifie les champs qui le
        pilotent -- poules, taille de poule, qualifiés, durée -- au lieu d'attendre
        1780 px plus bas. Le bouton d'enregistrement le suit : dans une page de
        cette longueur, il était lui aussi hors de portée.
    --}}
    <aside class="min-w-0 xl:col-span-1">
        <div class="flex flex-col gap-3 xl:sticky xl:top-4">
            @include('components.admin.club-events.tournaments.partials.steps.simulation-panel')

            <x-button
                :label="$tournamentId ? __('Update tournament') : __('Create tournament')"
                :icon="$tournamentId ? 'o-arrow-path' : 'o-plus-circle'"
                class="btn-primary w-full"
                wire:click="save"
                spinner="save" />
        </div>
    </aside>

</fieldset>
