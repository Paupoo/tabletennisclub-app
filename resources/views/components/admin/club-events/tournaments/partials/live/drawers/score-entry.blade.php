<x-drawer wire:model="scoreDrawer" :title="__('Enter score')" right separator with-close-button class="w-11/12 md:w-[450px]"
    x-data="{ confirmOpen: false }">

    @if ($this->selectedMatch)
        @php
            $match      = $this->selectedMatch;
            $maxSets    = ($tournament->sets_to_win * 2) - 1;
            $hp1        = $p1Handicap ?? 0;
            $hp2        = $p2Handicap ?? 0;
            $doneSets   = collect($setScores)->filter(fn ($s) => !((int)($s['p1'] ?? 0) === $hp1 && (int)($s['p2'] ?? 0) === $hp2));

            $p1Sets = 0;
            $p2Sets = 0;
            $setNum = 0;
            foreach ($doneSets as $s) {
                $sp1  = (int)($s['p1'] ?? 0);
                $sp2  = (int)($s['p2'] ?? 0);
                $setNum++;
                $validSet = $tournament->validateSetScore($sp1, $sp2, $setNum, $hp1, $hp2) === null;
                if ($validSet) {
                    $sp1 > $sp2 ? $p1Sets++ : $p2Sets++;
                }
                if ($p1Sets >= $tournament->sets_to_win || $p2Sets >= $tournament->sets_to_win) {
                    break;
                }
            }

            $matchFinished = $p1Sets >= $tournament->sets_to_win || $p2Sets >= $tournament->sets_to_win;
            $hasSets    = $doneSets->isNotEmpty();
            $winner     = $matchFinished
                ? ($p1Sets >= $tournament->sets_to_win ? $match->player1 : $match->player2)
                : null;
            $isDoubles  = $match->pair1_id !== null;
            $side1Name  = $isDoubles ? ($match->pair1?->displayName() ?? '—') : ($match->player1?->full_name ?? '—');
            $side2Name  = $isDoubles ? ($match->pair2?->displayName() ?? '—') : ($match->player2?->full_name ?? '—');
            $winnerName = $matchFinished
                ? ($p1Sets >= $tournament->sets_to_win ? $side1Name : $side2Name)
                : null;
        @endphp

        {{-- Match header --}}
        <div class="bg-base-200 p-4 rounded-xl mb-6 border border-base-300">
            <div class="flex justify-between items-center mb-3">
                <span class="text-[10px] font-black uppercase tracking-widest opacity-60">
                    {{ $match->pool?->name ?? __('Bracket') }}
                </span>
                <div class="flex items-center gap-2">
                    @if ($match->referee)
                        <span class="flex items-center gap-1 text-[10px] opacity-50">
                            <x-icon name="o-eye" class="w-3 h-3 shrink-0" />
                            {{ $match->referee->full_name }}
                        </span>
                    @endif
                    <x-badge value="{{ __('Best of') }} {{ $maxSets }}" class="badge-outline badge-xs opacity-50 font-bold" />
                </div>
            </div>

            <div class="flex justify-between items-center gap-4">
                <div class="flex-1 text-center">
                    <div @class(['font-black text-sm truncate uppercase', 'text-success' => $matchFinished && $p1Sets >= $tournament->sets_to_win])>
                        {{ $side1Name }}
                    </div>
                    <div @class(['text-3xl font-extrabold', 'text-success' => $matchFinished && $p1Sets >= $tournament->sets_to_win, 'text-primary' => ! ($matchFinished && $p1Sets >= $tournament->sets_to_win)])>
                        {{ $p1Sets }}
                    </div>
                </div>
                <div class="text-xl font-black opacity-20 italic">VS</div>
                <div class="flex-1 text-center">
                    <div @class(['font-black text-sm truncate uppercase', 'text-success' => $matchFinished && $p2Sets >= $tournament->sets_to_win])>
                        {{ $side2Name }}
                    </div>
                    <div @class(['text-3xl font-extrabold', 'text-success' => $matchFinished && $p2Sets >= $tournament->sets_to_win])>
                        {{ $p2Sets }}
                    </div>
                </div>
            </div>
        </div>

        {{-- Handicap info bar --}}
        @if ($tournament->has_handicap_points && ($hp1 > 0 || $hp2 > 0))
            <div class="rounded-xl border border-warning/40 bg-warning/10 p-3 mb-4">
                <p class="text-[10px] font-bold uppercase tracking-widest text-warning text-center mb-2">
                    {{ __('Handicap per set — starting scores') }}
                </p>
                <div class="flex justify-between items-center">
                    <div class="flex-1 text-center">
                        <div class="text-[10px] font-bold opacity-60 truncate">{{ $side1Name }}</div>
                        <div @class(['text-2xl font-extrabold leading-none', 'text-warning' => $hp1 > 0, 'text-base-content/30' => $hp1 === 0])>
                            +{{ $hp1 }}
                        </div>
                    </div>
                    <div class="text-[10px] font-bold opacity-40 uppercase">pts</div>
                    <div class="flex-1 text-center">
                        <div class="text-[10px] font-bold opacity-60 truncate">{{ $side2Name }}</div>
                        <div @class(['text-2xl font-extrabold leading-none', 'text-warning' => $hp2 > 0, 'text-base-content/30' => $hp2 === 0])>
                            +{{ $hp2 }}
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Set scores (all always editable) --}}
        <div class="space-y-3">
            <div class="flex items-center gap-2 mb-4">
                <x-icon name="o-list-bullet" class="w-4 h-4 opacity-50" />
                <span class="text-xs font-bold uppercase tracking-wider">{{ __('Set scores') }}</span>
            </div>

            @for ($i = 0; $i < $maxSets; $i++)
                @php
                    $p1      = (int)($setScores[$i]['p1'] ?? $hp1);
                    $p2      = (int)($setScores[$i]['p2'] ?? $hp2);
                    $isEmpty = ($p1 === $hp1 && $p2 === $hp2);
                    $sMax    = max($p1, $p2);
                    $sMin    = min($p1, $p2);
                    $setDone = ! $isEmpty && $p1 !== $p2 && (
                        $tournament->deuce_enabled
                            ? (($sMin < 10 && $sMax === 11) || ($sMin >= 10 && $sMax - $sMin === 2))
                            : ($sMax === 11)
                    );
                @endphp

                <div class="flex items-center gap-4 p-3 rounded-xl border transition-all set-row"
                    data-set-index="{{ $i }}"
                    data-hp1="{{ $hp1 }}"
                    data-hp2="{{ $hp2 }}"
                    data-deuce="{{ $tournament->deuce_enabled ? 'true' : 'false' }}"
                    @class([
                        'border-success/40 bg-success/5' => $setDone,
                        'border-base-300 bg-base-100'    => ! $setDone,
                    ])
                >
                    <div @class([
                        'flex-none w-10 h-10 rounded-lg flex flex-col items-center justify-center',
                        'bg-success text-success-content' => $setDone,
                        'bg-base-200 text-base-content/50' => ! $setDone,
                    ])>
                        <span class="text-[9px] uppercase font-bold leading-none">Set</span>
                        <span class="text-lg font-black leading-none">{{ $i + 1 }}</span>
                    </div>

                    <div class="flex grow items-center gap-2">
                        <x-input wire:model.debounce-300ms="setScores.{{ $i }}.p1"
                            type="number" min="{{ $hp1 }}" max="30" placeholder="{{ $hp1 }}"
                            class="input-sm text-center font-mono font-bold text-lg" />
                        <span class="opacity-30 font-bold">:</span>
                        <x-input wire:model.debounce-300ms="setScores.{{ $i }}.p2"
                            type="number" min="{{ $hp2 }}" max="30" placeholder="{{ $hp2 }}"
                            class="input-sm text-center font-mono font-bold text-lg" />
                    </div>

                    <div class="flex-none w-6 flex justify-center">
                        @if ($setDone)
                            <x-icon name="o-check-circle" class="w-6 h-6 text-success" />
                        @endif
                    </div>
                </div>
            @endfor
        </div>

        {{-- QR code for mobile entry --}}
        @if ($this->selectedTableId)
            @php
                $qrUrl  = route('tournament.table.score', [$tournament, $this->selectedTableId]);
                $qrCode = new \Endroid\QrCode\QrCode($qrUrl, size: 160, margin: 4);
                $svgQr  = substr((new \Endroid\QrCode\Writer\SvgWriter)->write($qrCode)->getString(), 22);
            @endphp
            <div class="mt-6 pt-6 border-t border-base-300 flex flex-col items-center gap-2">
                <p class="text-[10px] uppercase font-bold opacity-40 tracking-widest">{{ __('Mobile score entry') }}</p>
                <a href="{{ $qrUrl }}" target="_blank"
                    class="opacity-60 hover:opacity-100 transition-opacity p-2 bg-white rounded-xl inline-block shadow-sm">
                    {!! $svgQr !!}
                </a>
            </div>
        @endif

        {{-- Confirm overlay (inside drawer) --}}
        <div x-show="confirmOpen" x-cloak
            class="absolute inset-0 z-10 flex items-end justify-center bg-base-100/90 backdrop-blur-sm p-6">
            <div class="w-full bg-base-100 rounded-2xl shadow-2xl border border-base-300 p-6 space-y-4 text-center">
                <x-icon name="o-trophy" class="w-12 h-12 mx-auto text-success" />
                @if ($winnerName)
                    <div>
                        <p class="text-xs uppercase font-bold opacity-40 mb-1">{{ __('Winner') }}</p>
                        <p class="text-xl font-black">{{ $winnerName }}</p>
                        <p class="text-3xl font-extrabold text-success mt-1">{{ $p1Sets }} — {{ $p2Sets }}</p>
                    </div>
                @endif
                <p class="text-sm opacity-60">{{ __('Confirm and record this result?') }}</p>
                <div class="flex gap-2">
                    <button @click="confirmOpen = false" class="btn btn-ghost flex-1">{{ __('Cancel') }}</button>
                    <button wire:click="submitScore"
                        @click="confirmOpen = false; $wire.scoreDrawer = false"
                        class="btn btn-success flex-1">
                        {{ __('Confirm') }}
                    </button>
                </div>
            </div>
        </div>

        <x-slot:actions>
            <x-button :label="__('Cancel')" @click="$wire.scoreDrawer = false" />
            @if ($matchFinished)
                <x-button :label="__('Submit score')" icon="o-trophy"
                    class="btn-success" @click="confirmOpen = true" />
            @elseif ($hasSets)
                <x-button :label="__('Save sets')" icon="o-arrow-down-tray"
                    class="btn-outline" wire:click="saveDraft" spinner="saveDraft"
                    @click="$wire.scoreDrawer = false" />
            @endif
        </x-slot:actions>

        <script>
            function updateSetValidationDrawer() {
                document.querySelectorAll('.set-row').forEach(setRow => {
                    const inputs = setRow.querySelectorAll('input[type="number"]');
                    if (inputs.length < 2) return;

                    const p1Str = inputs[0].value;
                    const p2Str = inputs[1].value;
                    const hp1 = parseInt(setRow.dataset.hp1) || 0;
                    const hp2 = parseInt(setRow.dataset.hp2) || 0;
                    const deuce = setRow.dataset.deuce === 'true';

                    const p1 = p1Str ? parseInt(p1Str) : hp1;
                    const p2 = p2Str ? parseInt(p2Str) : hp2;

                    // Check if set is won
                    let isWon = false;
                    if (p1 !== hp1 || p2 !== hp2) { // not empty
                        if (p1 !== p2) { // not tied
                            const max = Math.max(p1, p2);
                            const min = Math.min(p1, p2);
                            if (deuce) {
                                isWon = (min < 10 && max === 11) || (min >= 10 && max - min === 2);
                            } else {
                                isWon = max === 11;
                            }
                        }
                    }

                    // Update classes
                    if (isWon) {
                        setRow.classList.remove('border-base-300', 'bg-base-100');
                        setRow.classList.add('border-success/40', 'bg-success/5');
                        const icon = setRow.querySelector('.bg-success');
                        if (!icon) {
                            const badge = setRow.querySelector('[class*="w-10"]');
                            if (badge) {
                                badge.classList.remove('bg-base-200', 'text-base-content/50');
                                badge.classList.add('bg-success', 'text-success-content');
                            }
                        }
                    } else {
                        setRow.classList.remove('border-success/40', 'bg-success/5');
                        setRow.classList.add('border-base-300', 'bg-base-100');
                        const badge = setRow.querySelector('[class*="w-10"]');
                        if (badge) {
                            badge.classList.remove('bg-success', 'text-success-content');
                            badge.classList.add('bg-base-200', 'text-base-content/50');
                        }
                    }
                });
            }

            // Update on input
            document.addEventListener('input', (e) => {
                if (e.target.matches('input[type="number"]')) {
                    updateSetValidationDrawer();
                }
            }, true);
        </script>
    @endif

</x-drawer>
