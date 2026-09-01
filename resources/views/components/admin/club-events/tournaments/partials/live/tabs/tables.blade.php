{{--
    L'état de la salle. Le poll est porté par la régie qui inclut ce partiel.

    La grille passe à deux colonnes dès le téléphone : à une seule, un écran de
    375 px montrait deux tables sur douze, et l'organisateur qui marche dans la
    salle cherche justement d'un coup d'œil lesquelles sont libres. La carte est
    donc compacte par défaut et se remplit à partir de sm.
--}}
<div>
    @if ($this->tables->isEmpty())
        <div class="flex flex-col items-center py-20 text-muted">
            <x-icon name="o-squares-2x2" class="mb-3 h-12 w-12" />
            <p class="text-sm">{{ __('No tables linked to this tournament.') }}</p>
        </div>
    @else
        @foreach ($this->tables as $roomName => $roomTables)
            <div class="mb-10">
                <div class="mb-4 flex items-center gap-3">
                    <x-icon name="o-map-pin" class="h-5 w-5 shrink-0 text-base-content/40" />
                    <span class="truncate text-lg font-black uppercase tracking-tighter">{{ $roomName }}</span>
                    <div class="h-px grow bg-base-300"></div>
                    <span class="shrink-0 text-xs text-muted">
                        {{ __(':free free / :total', ['free' => $roomTables->where('is_free', true)->count(), 'total' => $roomTables->count()]) }}
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-3 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($roomTables as $table)
                        @php
                            $minutesElapsed = ! $table['is_free'] && $table['match_started_at']
                                ? (int) \Carbon\Carbon::parse($table['match_started_at'])->diffInMinutes(now())
                                : 0;
                            $isOverdue = $minutesElapsed > 20;
                            $match = $table['is_free'] ? null : $table['match'];
                        @endphp

                        <div wire:key="table-{{ $table['id'] }}" @class([
                            'flex flex-col rounded-xl border p-3 transition-colors',
                            'border-success/60 bg-success/5' => $table['is_free'],
                            'border-error/50 bg-error/5' => $isOverdue,
                            'border-primary/25 bg-base-100' => ! $table['is_free'] && ! $isOverdue,
                        ])>
                            <div class="mb-2 flex items-center justify-between gap-2">
                                <span class="truncate text-lg font-black leading-none">{{ $table['name'] }}</span>

                                @if ($table['is_free'])
                                    <x-badge :value="__('FREE')" class="badge-success badge-xs shrink-0 font-bold" />
                                @else
                                    <x-badge value="{{ $minutesElapsed }}′"
                                        class="{{ $isOverdue ? 'badge-error' : 'badge-ghost' }} badge-xs shrink-0" />
                                @endif
                            </div>

                            @if ($isOverdue)
                                <p class="mb-2 flex items-center gap-1 text-xs font-bold text-error">
                                    <x-icon name="o-exclamation-triangle" class="h-3.5 w-3.5 shrink-0" />
                                    <span class="truncate">{{ __('check the referee!') }}</span>
                                </p>
                            @endif

                            @if ($match)
                                @php
                                    $isDoubles = $match->pair1_id !== null;
                                    $side1Name = $isDoubles ? ($match->pair1?->displayName() ?? '—') : ($match->player1?->full_name ?? '—');
                                    $side2Name = $isDoubles ? ($match->pair2?->displayName() ?? '—') : ($match->player2?->full_name ?? '—');
                                @endphp
                                <div class="text-xs leading-tight">
                                    <p class="truncate font-semibold">{{ $side1Name }}</p>
                                    <p class="truncate font-semibold text-base-content/60">{{ $side2Name }}</p>
                                </div>

                                @if ($match->referee)
                                    <p class="mt-1.5 hidden items-center gap-1 text-xs text-muted sm:flex">
                                        <x-icon name="o-eye" class="h-3 w-3 shrink-0" />
                                        <span class="truncate">{{ $match->referee->full_name }}</span>
                                    </p>
                                @endif

                                @if ($match->sets->count())
                                    <div class="mt-1.5 hidden flex-wrap gap-1 sm:flex">
                                        @foreach ($match->sets as $set)
                                            <x-badge value="{{ $set->player1_score }}-{{ $set->player2_score }}"
                                                class="badge-info badge-soft px-1.5 font-mono text-xs" />
                                        @endforeach
                                    </div>
                                @endif

                                <x-button :label="__('Score')" icon="o-pencil"
                                    class="btn-outline btn-xs mt-2 w-full"
                                    wire:click="openScoreEntry({{ $match->id }}, {{ $table['id'] }})" />
                            @else
                                <x-button :label="__('Launch a match')" icon="o-play"
                                    class="btn-outline btn-xs mt-auto w-full text-success"
                                    wire:click="openLaunchDrawer({{ $table['id'] }})" />
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    @endif
</div>
