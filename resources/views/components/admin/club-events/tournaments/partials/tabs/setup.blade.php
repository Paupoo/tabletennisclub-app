<x-tab name="1" label="Setup" icon="o-cog-6-tooth">
    <div class="mt-8 grid grid-cols-6 gap-4 md:gap-6">

        {{-- ── Section 1 : Details ─────────────────────────────────────────── --}}
        <x-admin.shared.form-section title="Details"
            subtitle="Define the framework for your competition: location, date and rules of the game.">

            <div class="lg:col-span-2 space-y-6">
                <div class="grid md:grid-cols-2 gap-5">
                    <x-input label="Tournament name(*)" placeholder="Ex: Spring Grand Prix" icon="o-trophy"
                        class="md:col-span-2" wire:model.live.debounce.500ms="name" />
                    <x-choices label="Room(s)(*)" wire:model="selectedRooms" :options="$rooms"
                        icon="o-map-pin" />
                    <x-datepicker label="Date(*)" value="2026-05-15" icon="o-calendar"
                        wire:model="tournamentDate" type="date" />
                    <x-input label="Start time(*)" type="time" icon="o-clock" />
                    <x-input label="Registration fee" suffix="€" type="number" icon="o-banknotes" />
                    <div class="col-span-2">
                        <x-toggle label="{{ __('Open registrations') }}" icon="o-eye"
                            hint="{{ __('If chosen, this tournament will be open for external registration on our website.') }}"
                            wire:model.live="publicRegistration" />
                    </div>
                </div>
            </div>

        </x-admin.shared.form-section>

        {{-- ── Section 2 : Capacity & logistics ──────────────────────────────── --}}
        <x-admin.shared.form-section title="Capacity and logistics"
            subtitle="These physical parameters define the maximum number of playable matches. Everything else follows from this.">

            <x-input wire:model.live.debounce.500ms="tournament_minutes" label="Total duration"
                type="number" icon="o-clock" suffix="min" hint="Ex: 180 = 3 hours" min="60"
                step="30" />
            <x-input wire:model.live.debounce.500ms="nb_tables" label="Available tables" type="number"
                icon="o-table-cells" hint="Across all rooms" min="1" />
            <x-input wire:model.live.debounce.500ms="logistics_buffer" label="Buffer between matches"
                type="number" icon="o-arrows-right-left" suffix="min" hint="Rotation, scoring, movement"
                min="0" max="10" />

        </x-admin.shared.form-section>

        {{-- ── Section 3 : Rules & format ─────────────────────────────────────── --}}
        <x-admin.shared.form-section title="Rules and format"
            subtitle="Sport parameters directly impact the number and duration of matches.">

            <x-select label="{{ __('Match type(*)') }}" icon="o-user" :options="[['id' => 'single', 'name' => 'Singles'], ['id' => 'double', 'name' => 'Doubles']]" />
            <x-select wire:model.live.debounce.500ms="totalSets" :options="$this->setOptions"
                label="{{ __('Winning sets(*)') }}" icon="o-star"
                hint="Best of {{ $this->bestOfCount }}" />
            <x-input wire:model.live.debounce.500ms="nb_poules" label="{{ __('Number of pools(*)') }}"
                icon="o-calculator" type="number" min="1" />
            <x-select wire:model.live.debounce.500ms="pool_size" label="{{ __('Players per pool(*)') }}"
                icon="o-user-group" :options="$poolSizeOptions" hint="Strong impact on match count" />
            <x-input wire:model.live.debounce.500ms="nb_qualifies" label="{{ __('Qualified per pool(*)') }}"
                icon="o-trophy" type="number" hint="Players advancing to the bracket" min="1"
                numeric />
            <x-toggle label="Handicap points" right />
            <div class="lg:col-span-2">
                <x-textarea label="Additional information" rows="4"
                    placeholder="Specific rules, dress code..." />
            </div>

        </x-admin.shared.form-section>

        {{-- ── Feasibility simulator ──────────────────────────────────────────── --}}
        @php
        $risk = $this->riskLevel;
        $colors = [
        'ok' => [
        'bg' => 'bg-success/10',
        'border' => 'border-success',
        'text' => 'text-success',
        'badge' => 'badge-success',
        'icon' => 'o-check-circle',
        ],
        'warning' => [
        'bg' => 'bg-warning/10',
        'border' => 'border-warning',
        'text' => 'text-warning',
        'badge' => 'badge-warning',
        'icon' => 'o-exclamation-triangle',
        ],
        'danger' => [
        'bg' => 'bg-error/10',
        'border' => 'border-error',
        'text' => 'text-error',
        'badge' => 'badge-error',
        'icon' => 'o-x-circle',
        ],
        ];
        $c = $colors[$risk];
        $occupancy = $this->tableOccupancyPercent;
        $barColor = match (true) {
        $occupancy > 100 => 'bg-error',
        $occupancy >= 80 => 'bg-warning',
        default => 'bg-success',
        };
        $hours = intdiv($this->estimatedMinutes, 60);
        $mins = $this->estimatedMinutes % 60;
        $durationLabel = $hours > 0 ? "{$hours}h" . ($mins > 0 ? "{$mins}min" : '') : "{$mins}min";
        @endphp

        {{-- Feasibility Simulator --}}
        <div class="col-span-6">
            <x-card shadow>

                {{-- Header --}}
                <x-slot:title>
                    <div class="flex items-center gap-2">
                        <x-icon name="o-beaker" class="w-4 h-4 shrink-0 text-base-content/50" />
                        <span class="text-base font-semibold">Feasibility simulation</span>

                        @if ($risk === 'ok')
                        <x-badge value="Feasible" class="badge-success badge-soft ml-auto" />
                        @elseif ($risk === 'warning')
                        <x-badge value="Tight" class="badge-warning badge-soft ml-auto" />
                        @else
                        <x-badge value="Not feasible" class="badge-error badge-soft ml-auto" />
                        @endif
                    </div>
                </x-slot:title>

                <div class="space-y-5">

                    {{-- Occupancy bar --}}
                    <div class="space-y-1.5">
                        <div class="flex items-baseline justify-between gap-2 text-sm">
                            <span class="text-base-content/60 shrink-0">Table occupancy</span>
                            <span class="font-medium tabular-nums">{{ min($occupancy, 999) }}%</span>
                        </div>
                        <x-progress value="{{ min($occupancy, 100) }}" max="100"
                            class="progress-{{ $risk === 'ok' ? 'success' : ($risk === 'warning' ? 'warning' : 'error') }} h-2" />
                        <div class="flex justify-between text-xs text-base-content/40">
                            <span>{{ $this->grandTotalMatches }} needed</span>
                            <span>{{ $this->totalMatchCapacity }} max</span>
                        </div>
                    </div>

                    {{-- Key metrics : une ligne par stat, label à gauche / valeur à droite --}}
                    <div class="divide-y divide-base-200">

                        <div class="flex items-center justify-between py-2.5">
                            <div class="flex items-center gap-2 text-sm text-base-content/60">
                                <x-icon name="o-clock" class="w-4 h-4 shrink-0" />
                                <span>Estimated duration</span>
                            </div>
                            <div class="text-right text-sm">
                                <span
                                    class="font-semibold tabular-nums {{ $this->estimatedMinutes > $this->tournament_minutes ? 'text-error' : '' }}">
                                    {{ $durationLabel }}
                                </span>
                                <span class="text-base-content/40 ml-1">/
                                    {{ intdiv($this->tournament_minutes, 60) }}h</span>
                            </div>
                        </div>

                        <div class="flex items-center justify-between py-2.5">
                            <div class="flex items-center gap-2 text-sm text-base-content/60">
                                <x-icon name="o-table-cells" class="w-4 h-4 shrink-0" />
                                <span>Total matches</span>
                            </div>
                            <div class="text-right text-sm">
                                <span
                                    class="font-semibold tabular-nums">{{ $this->grandTotalMatches }}</span>
                                <span class="text-base-content/40 ml-1">{{ $this->poolMatchesTotal }}
                                    pools · {{ $this->bracketMatchesTotal }} bracket</span>
                            </div>
                        </div>

                        <div class="flex items-center justify-between py-2.5">
                            <div class="flex items-center gap-2 text-sm text-base-content/60">
                                <x-icon name="o-user-group" class="w-4 h-4 shrink-0" />
                                <span>Players</span>
                            </div>
                            <div class="text-right text-sm">
                                <span class="font-semibold tabular-nums">{{ $this->totalPlayers }}</span>
                                <span class="text-base-content/40 ml-1">→ {{ $this->finalistsCount }}
                                    finalists</span>
                            </div>
                        </div>

                        <div class="flex items-center justify-between py-2.5">
                            <div class="flex items-center gap-2 text-sm text-base-content/60">
                                <x-icon name="o-bolt" class="w-4 h-4 shrink-0" />
                                <span>Matches per player</span>
                            </div>
                            <div class="text-right text-sm">
                                <span
                                    class="font-semibold tabular-nums">{{ $this->avgMatchesPerPlayer }}</span>
                                <span class="text-base-content/40 ml-1">~{{ $this->avgMatchMinutes }}min
                                    each</span>
                            </div>
                        </div>

                    </div>

                    {{-- Contextual alert --}}
                    @if ($risk === 'danger')
                    <x-alert
                        title="{{ $this->grandTotalMatches - $this->totalMatchCapacity }} matches over capacity"
                        description="Reduce the number of pools, pool size, or increase available tables / total duration."
                        icon="o-x-circle" class="alert-error alert-soft" />
                    @elseif ($risk === 'warning')
                    <x-alert title="Tight schedule"
                        description="Delays could compromise the end of the tournament. A 20% safety margin is recommended."
                        icon="o-exclamation-triangle" class="alert-warning alert-soft" />
                    @else
                    <x-alert title="Configuration looks good"
                        description="Buffer: {{ $this->totalMatchCapacity - $this->grandTotalMatches }} matches to absorb unexpected delays."
                        icon="o-check-circle" class="alert-success alert-soft" />
                    @endif

                </div>

            </x-card>
        </div>

    </div>
</x-tab>