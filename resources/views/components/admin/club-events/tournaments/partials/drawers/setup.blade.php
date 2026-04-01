<x-drawer wire:model="setupDrawer" title="Final Setup" right separator with-close-button class="lg:w-1/3">

    <div class="space-y-8">
        {{-- Section 2 : Capacity & logistics --}}
        <div>
            <div class="mb-4">
                <h3 class="text-lg font-bold">Capacity and logistics</h3>
                <p class="text-xs text-base-content/60">Define physical constraints.</p>
            </div>
            <div class="grid gap-4">
                <x-input wire:model.live.debounce.500ms="tournament_minutes" label="Total duration"
                    type="number" icon="o-clock" suffix="min" hint="Ex: 180 = 3 hours" min="60" step="30" />

                <x-input wire:model.live.debounce.500ms="nb_tables" label="Available tables" type="number"
                    icon="o-table-cells" hint="Across all rooms" min="1" />

                <x-input wire:model.live.debounce.500ms="logistics_buffer" label="Buffer between matches"
                    type="number" icon="o-arrows-right-left" suffix="min" min="0" max="10" />
            </div>
        </div>

        <hr />

        {{-- Section 3 : Rules & format --}}
        <div>
            <div class="mb-4">
                <h3 class="text-lg font-bold">Rules and format</h3>
                <p class="text-xs text-base-content/60">Sport parameters and match count.</p>
            </div>
            <div class="grid gap-4">
                <x-select label="{{ __('Match type(*)') }}" icon="o-user" :options="[['id' => 'single', 'name' => 'Singles'], ['id' => 'double', 'name' => 'Doubles']]" />

                <x-select wire:model.live.debounce.500ms="totalSets" :options="$this->setOptions"
                    label="{{ __('Winning sets(*)') }}" icon="o-star" hint="Best of {{ $this->bestOfCount }}" />

                <div class="grid grid-cols-2 gap-4">
                    <x-input wire:model.live.debounce.500ms="nb_poules" label="{{ __('Pools(*)') }}" icon="o-calculator" type="number" min="1" />
                    <x-select wire:model.live.debounce.500ms="pool_size" label="{{ __('Size(*)') }}" icon="o-user-group" :options="$poolSizeOptions" />
                </div>

                <x-input wire:model.live.debounce.500ms="nb_qualifies" label="{{ __('Qualified per pool(*)') }}"
                    icon="o-trophy" type="number" min="1" />

                <x-toggle label="Handicap points" right />

                <x-textarea label="Additional information" rows="3" placeholder="Specific rules..." />
            </div>
        </div>

        {{-- Section Feasibility Simulator --}}
        <div class="pt-4">
            @php
            $risk = $this->riskLevel;
            $occupancy = $this->tableOccupancyPercent;
            $hours = intdiv($this->estimatedMinutes, 60);
            $mins = $this->estimatedMinutes % 60;
            $durationLabel = $hours > 0 ? "{$hours}h" . ($mins > 0 ? "{$mins}min" : '') : "{$mins}min";
            @endphp

            <div class="bg-base-200 p-4 rounded-xl space-y-4 border border-base-300">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <x-icon name="o-beaker" class="w-4 h-4 text-primary" />
                        <span class="font-bold">Simulation</span>
                    </div>
                    <x-badge :value="$risk === 'ok' ? 'Feasible' : ($risk === 'warning' ? 'Tight' : 'Error')"
                        class="{{ $risk === 'ok' ? 'badge-success' : ($risk === 'warning' ? 'badge-warning' : 'badge-error') }} badge-sm" />
                </div>

                {{-- Progress --}}
                <div class="space-y-1">
                    <div class="flex justify-between text-xs">
                        <span>Occupancy</span>
                        <span class="font-mono">{{ min($occupancy, 999) }}%</span>
                    </div>
                    <x-progress value="{{ min($occupancy, 100) }}" max="100"
                        class="progress-{{ $risk === 'ok' ? 'success' : ($risk === 'warning' ? 'warning' : 'error') }} h-1.5" />
                </div>

                {{-- Stats --}}
                <div class="text-sm space-y-2">
                    <div class="flex justify-between border-b border-base-300 pb-1">
                        <span class="text-base-content/60">Estimated duration</span>
                        <span class="font-semibold {{ $this->estimatedMinutes > $this->tournament_minutes ? 'text-error' : '' }}">{{ $durationLabel }}</span>
                    </div>
                    <div class="flex justify-between border-b border-base-300 pb-1">
                        <span class="text-base-content/60">Total matches</span>
                        <span class="font-semibold">{{ $this->grandTotalMatches }}</span>
                    </div>
                </div>

                {{-- Alert compacte --}}
                @if ($risk === 'danger')
                <div class="text-xs text-error flex gap-2">
                    <x-icon name="o-x-circle" class="w-4 h-4 shrink-0" />
                    <span>Capacity exceeded by {{ $this->grandTotalMatches - $this->totalMatchCapacity }} matches.</span>
                </div>
                @endif
            </div>
        </div>
    </div>

    <x-slot:actions>
        <x-button label="Cancel" @click="showDrawer = false" />
        <x-button label="Save Setup" class="btn-primary" icon="o-check" />
    </x-slot:actions>
</x-drawer>