<x-drawer wire:model="scoreDrawer" title="{{ __('Enter score') }}" right separator with-close-button class="w-11/12 md:w-[450px]">

    @if ($this->selectedMatch)
        @php
            $match   = $this->selectedMatch;
            $maxSets = ($tournament->sets_to_win * 2) - 1;
            $p1Sets  = collect($setScores)->filter(fn ($s) => (int)($s['p1'] ?? 0) > (int)($s['p2'] ?? 0))->count();
            $p2Sets  = collect($setScores)->filter(fn ($s) => (int)($s['p2'] ?? 0) > (int)($s['p1'] ?? 0))->count();
        @endphp

        {{-- Match header --}}
        <div class="bg-base-200 p-4 rounded-xl mb-6 border border-base-300">
            <div class="flex justify-between items-center mb-3">
                <span class="text-[10px] font-black uppercase tracking-widest opacity-60">
                    {{ $match->pool?->name ?? __('Bracket') }}
                </span>
                <x-badge value="{{ __('Best of') }} {{ $maxSets }}" class="badge-outline badge-xs opacity-50 font-bold" />
            </div>

            <div class="flex justify-between items-center gap-4">
                <div class="flex-1 text-center">
                    <div class="font-black text-sm truncate uppercase">{{ $match->player1?->full_name ?? '—' }}</div>
                    <div class="text-3xl font-extrabold text-primary">{{ $p1Sets }}</div>
                </div>
                <div class="text-xl font-black opacity-20 italic">VS</div>
                <div class="flex-1 text-center">
                    <div class="font-black text-sm truncate uppercase">{{ $match->player2?->full_name ?? '—' }}</div>
                    <div class="text-3xl font-extrabold">{{ $p2Sets }}</div>
                </div>
            </div>
        </div>

        {{-- Set scores --}}
        <div class="space-y-3">
            <div class="flex items-center gap-2 mb-4">
                <x-icon name="o-list-bullet" class="w-4 h-4 opacity-50" />
                <span class="text-xs font-bold uppercase tracking-wider">{{ __('Set scores') }}</span>
            </div>

            @for ($i = 0; $i < $maxSets; $i++)
                @php
                    $p1 = (int)($setScores[$i]['p1'] ?? 0);
                    $p2 = (int)($setScores[$i]['p2'] ?? 0);
                    $setDone = $p1 > 0 || $p2 > 0;
                    // Lock sets beyond the match winner detection
                    $p1SetsWon = 0; $p2SetsWon = 0;
                    for ($j = 0; $j < $i; $j++) {
                        $sp1 = (int)($setScores[$j]['p1'] ?? 0);
                        $sp2 = (int)($setScores[$j]['p2'] ?? 0);
                        if ($sp1 > $sp2) $p1SetsWon++; else if ($sp2 > $sp1) $p2SetsWon++;
                    }
                    $matchWon = $p1SetsWon >= $tournament->sets_to_win || $p2SetsWon >= $tournament->sets_to_win;
                    $isLocked = $matchWon;
                @endphp

                <div class="flex items-center gap-4 p-3 rounded-xl border transition-all
                    {{ $isLocked ? 'opacity-30 border-base-200 bg-base-100' : ($setDone ? 'border-success/40 bg-success/5' : 'border-base-300 bg-base-100') }}">

                    <div class="flex-none w-10 h-10 rounded-lg flex flex-col items-center justify-center
                        {{ $setDone && !$isLocked ? 'bg-success text-success-content' : 'bg-base-200 text-base-content/50' }}">
                        <span class="text-[9px] uppercase font-bold leading-none">Set</span>
                        <span class="text-lg font-black leading-none">{{ $i + 1 }}</span>
                    </div>

                    <div class="flex flex-grow items-center gap-2">
                        <x-input wire:model.live="setScores.{{ $i }}.p1"
                            type="number" min="0" max="30" placeholder="0"
                            class="input-sm text-center font-mono font-bold text-lg"
                            :disabled="$isLocked" />
                        <span class="opacity-30 font-bold">:</span>
                        <x-input wire:model.live="setScores.{{ $i }}.p2"
                            type="number" min="0" max="30" placeholder="0"
                            class="input-sm text-center font-mono font-bold text-lg"
                            :disabled="$isLocked" />
                    </div>

                    <div class="flex-none w-6 flex justify-center">
                        @if ($isLocked)
                            <x-icon name="o-lock-closed" class="w-4 h-4 opacity-20" />
                        @elseif ($setDone)
                            <x-icon name="o-check-circle" class="w-6 h-6 text-success" />
                        @endif
                    </div>
                </div>
            @endfor
        </div>

        {{-- QR code for mobile entry (only shown when a table is linked) --}}
        @if ($this->selectedTableId)
            @php
                $qrUrl   = route('tournament.table.score', [$tournament, $this->selectedTableId]);
                $qrLarge = new \Endroid\QrCode\QrCode($qrUrl, size: 160, margin: 4);
                $writer  = new \Endroid\QrCode\Writer\SvgWriter;
                $svgQr   = substr($writer->write($qrLarge)->getString(), 22);
            @endphp
            <div class="mt-6 pt-6 border-t border-base-300 flex flex-col items-center gap-2">
                <p class="text-[10px] uppercase font-bold opacity-40 tracking-widest">{{ __('Mobile score entry') }}</p>
                <a href="{{ $qrUrl }}" target="_blank"
                    class="opacity-60 hover:opacity-100 transition-opacity p-2 bg-white rounded-xl inline-block shadow-sm">
                    {!! $svgQr !!}
                </a>
            </div>
        @endif

        <x-slot:actions>
            <x-button label="{{ __('Cancel') }}" @click="$wire.scoreDrawer = false" />
            <x-button label="{{ __('Submit score') }}" icon="o-check" class="btn-primary"
                wire:click="submitScore" spinner="submitScore" />
        </x-slot:actions>
    @endif

</x-drawer>
