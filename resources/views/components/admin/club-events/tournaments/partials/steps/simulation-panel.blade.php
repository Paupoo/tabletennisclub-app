{{--
    Le simulateur juge la configuration qu'on est en train de taper : durée
    estimée, occupation des tables, verdict, et les recommandations chiffrées.
    Il vivait tout en bas de l'étape 1, à ~1780 px des champs qui le pilotent
    -- 2,7 écrans sur un portable 1366x768. Extrait ici pour que la colonne de
    droite puisse le garder sous les yeux.
--}}
{{-- ── Feasibility simulator ──────────────────────────────────────────── --}}
@php
    $sim = $this->simulation;
    $risk = $sim->riskLevel;
    $occupancy = $sim->tableOccupancyPercent;
    $hours = intdiv($sim->estimatedMinutes, 60);
    $mins = $sim->estimatedMinutes % 60;
    $durationLabel = $hours > 0 ? "{$hours}h" . ($mins > 0 ? "{$mins}min" : '') : "{$mins}min";
    $eff = $this->tableEfficiency;
@endphp

    <x-card shadow>

        <x-slot:title>
            <div class="flex items-center gap-2">
                <x-icon name="o-beaker" class="w-4 h-4 shrink-0 text-base-content/50" />
                <span class="text-base font-semibold">{{ __('Feasibility simulation') }}</span>

                @if ($risk === 'ok')
                    <x-badge :value="__('Feasible')" class="badge-success badge-soft ml-auto" />
                @elseif ($risk === 'warning')
                    <x-badge :value="__('Tight')" class="badge-warning badge-soft ml-auto" />
                @else
                    <x-badge :value="__('Not feasible')" class="badge-error badge-soft ml-auto" />
                @endif
            </div>
        </x-slot:title>

        @php
            $durationPct = $tournament_minutes > 0
                ? min(100, (int) round($sim->estimatedMinutes / $tournament_minutes * 100))
                : 0;
            $durationRisk = $sim->estimatedMinutes > $tournament_minutes ? 'error'
                : ($durationPct >= 80 ? 'warning' : 'success');
            // Efficiency-based color: high occupancy = good (tables well-used).
            $occupancyColor = $occupancy > 100 ? 'error' : ($occupancy >= 70 ? 'success' : 'warning');
        @endphp

        <div class="space-y-5">

            {{-- Key metrics --}}
            <div class="divide-y divide-base-200">

                {{-- Duration + progress bar --}}
                <div class="py-2.5 space-y-1.5">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2 text-sm text-base-content/60">
                            <x-icon name="o-clock" class="w-4 h-4 shrink-0" />
                            <span>{{ __('Estimated duration') }}</span>
                        </div>
                        <div class="text-right text-sm">
                            <span class="font-semibold tabular-nums {{ $durationRisk === 'error' ? 'text-error' : '' }}">
                                {{ $durationLabel }}
                            </span>
                            <span class="text-base-content/40 ml-1">/ {{ intdiv($tournament_minutes, 60) }}h</span>
                        </div>
                    </div>
                    <x-progress value="{{ $durationPct }}" max="100"
                        class="progress-{{ $durationRisk }} h-1.5" />
                </div>

                {{-- Table occupancy bar (efficiency: green = tables well-used) --}}
                <div class="py-2.5 space-y-1.5">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2 text-sm text-base-content/60">
                            <x-icon name="o-chart-bar" class="w-4 h-4 shrink-0" />
                            <span>{{ __('Table occupancy') }}</span>
                        </div>
                        <span class="font-medium tabular-nums text-sm">{{ min($occupancy, 999) }}%</span>
                    </div>
                    <x-progress value="{{ min($occupancy, 100) }}" max="100"
                        class="progress-{{ $occupancyColor }} h-1.5" />
                    <div class="flex justify-between text-xs text-base-content/40">
                        <span class="tooltip tooltip-bottom cursor-help"
                            data-tip="{{ __('Matches to play: pool rounds + bracket') }}">
                            {{ $sim->grandTotalMatches }} {{ __('needed') }}
                        </span>
                        <span class="tooltip tooltip-bottom cursor-help"
                            data-tip="{{ __('Table capacity: max matches that fit within the available time') }}">
                            {{ $sim->totalMatchCapacity }} {{ __('max') }}
                        </span>
                    </div>
                </div>

                <div class="flex items-center justify-between py-2.5">
                    <div class="flex items-center gap-2 text-sm text-base-content/60">
                        <x-icon name="o-user-group" class="w-4 h-4 shrink-0" />
                        <span>{{ __('Capacity') }}</span>
                    </div>
                    <div class="text-right text-sm">
                        <span class="font-semibold tabular-nums">{{ $sim->totalPlayers }} {{ __('players') }}</span>
                        <span class="text-base-content/40 ml-1">{{ $nb_poules }}×{{ $pool_size }} → {{ $sim->finalistsCount }} {{ __('finalists') }}</span>
                    </div>
                </div>
                
                <div class="flex items-center justify-between py-2.5">
                    <div class="flex items-center gap-2 text-sm text-base-content/60">
                        <x-icon name="o-table-cells" class="w-4 h-4 shrink-0" />
                        <span>{{ __('Total matches') }}</span>
                    </div>
                    <div class="text-right text-sm">
                        <span class="font-semibold tabular-nums">{{ $sim->grandTotalMatches }}</span>
                        <span class="text-base-content/40 ml-1">{{ __(':pools in pools · :bracket in the bracket', ['pools' => $sim->poolMatchesTotal, 'bracket' => $sim->bracketMatchesTotal]) }}</span>
                    </div>
                </div>

                <div class="flex items-center justify-between py-2.5">
                    <div class="flex items-center gap-2 text-sm text-base-content/60">
                        <x-icon name="o-bolt" class="w-4 h-4 shrink-0" />
                        <span>{{ __('Matches per player') }}</span>
                    </div>
                    <div class="text-right text-sm">
                        <span class="font-semibold tabular-nums">{{ $sim->avgMatchesPerPlayer }}</span>
                        <span class="text-base-content/40 ml-1">{{ __('~:count min each', ['count' => $sim->avgMatchMinutes]) }}</span>
                    </div>
                </div>

                <div class="flex items-center justify-between py-2.5">
                    <div class="flex items-center gap-2 text-sm text-base-content/60">
                        <x-icon name="o-pause-circle" class="w-4 h-4 shrink-0" />
                        <span>{{ __('Avg wait between matches') }}</span>
                    </div>
                    <div class="text-right text-sm">
                        <span class="font-semibold tabular-nums">~{{ $sim->avgWaitTimeMinutes }}min</span>
                    </div>
                </div>

            </div>

            {{-- Recommendations --}}
            <div class="space-y-2 pt-1">
                @if ($risk === 'danger')
                    <x-alert
                        :title="__(':count matches over capacity', ['count' => $sim->grandTotalMatches - $sim->totalMatchCapacity])"
                        :description="__('Reduce pools, pool size, or increase available tables / total duration.')"
                        icon="o-x-circle" class="alert-error alert-soft" />
                @elseif ($risk === 'warning')
                    <x-alert :title="__('Tight schedule')"
                        :description="__('Delays could compromise the end of the tournament. A 20% safety margin is recommended.')"
                        icon="o-exclamation-triangle" class="alert-warning alert-soft" />
                @else
                    <x-alert :title="__('Configuration looks good')"
                        :description="__('Buffer: :count matches to absorb unexpected delays.', ['count' => $sim->safetyMarginMatches])"
                        icon="o-check-circle" class="alert-success alert-soft" />
                @endif

                @if ($this->nbTables > 0 && $eff['idle'] > 0)
                    <div class="p-3 rounded-xl border border-warning/40 bg-warning/5 space-y-1.5">
                        <div class="flex items-center gap-2 text-xs font-semibold text-warning-content">
                            <x-icon name="o-exclamation-triangle" class="w-3.5 h-3.5 shrink-0" />
                            {{ __('~:n table(s) may sit idle — tournament will run longer than needed', ['n' => $eff['idle']]) }}
                        </div>
                        <ul class="text-xs text-base-content/60 space-y-0.5 pl-1">
                            @if ($eff['extraPools'] > 0)
                                <li>→ {{ __('Add :n pool(s) → :t total', ['n' => $eff['extraPools'], 't' => $eff['suggestedNbPools']]) }}</li>
                            @endif
                            @if ($eff['nextBetterPoolSize'])
                                <li>→ {{ $matchType === 'double'
                                        ? __(':n pairs/pool instead of :c', ['n' => $eff['nextBetterPoolSize'], 'c' => $pool_size])
                                        : __(':n players/pool instead of :c', ['n' => $eff['nextBetterPoolSize'], 'c' => $pool_size]) }}</li>
                            @endif
                            <li>→ {{ __('Reduce to :n tables', ['n' => max(1, $eff['usefulTables'])]) }}</li>
                        </ul>
                    </div>
                @endif
            </div>

        </div>

    </x-card>
