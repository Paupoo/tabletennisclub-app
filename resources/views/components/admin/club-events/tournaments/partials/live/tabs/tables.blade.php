<div wire:poll.10s class="mt-6">
    @if ($this->tables->isEmpty())
        <div class="flex flex-col items-center py-20 opacity-30">
            <x-icon name="o-squares-2x2" class="w-12 h-12 mb-3" />
            <p class="text-sm">{{ __('No tables linked to this tournament.') }}</p>
        </div>
    @else
        @foreach ($this->tables as $roomName => $roomTables)
            <div class="mb-10">
                <div class="flex items-center gap-3 mb-6">
                    <x-icon name="o-map-pin" class="w-5 h-5 text-base-content/40" />
                    <span class="text-lg font-black tracking-tighter uppercase">{{ $roomName }}</span>
                    <div class="h-px bg-base-300 grow"></div>
                    <span class="text-xs opacity-40">
                        {{ $roomTables->where('is_free', true)->count() }} {{ __('free') }} /
                        {{ $roomTables->count() }} {{ __('total') }}
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    @foreach ($roomTables as $table)
                        @php
                            $tableUrl = route('tournament.table.score', [$tournament, $table['id']]);
                            $qrSmall  = new \Endroid\QrCode\QrCode($tableUrl, size: 80, margin: 2);
                            $writer   = new \Endroid\QrCode\Writer\SvgWriter;
                            $svgSmall = substr($writer->write($qrSmall)->getString(), 22);
                        @endphp

                        <x-card wire:key="table-{{ $table['id'] }}" shadow
                            class="border {{ $table['is_free'] ? 'bg-base-200/40 border-base-300' : 'bg-base-100 border-primary/20' }} relative">

                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <div class="text-[10px] uppercase font-bold opacity-40">{{ __('Table') }}</div>
                                    <div class="text-xl font-black truncate max-w-25">{{ $table['name'] }}</div>
                                </div>

                                @if ($table['is_free'])
                                    <x-badge value="{{ __('FREE') }}" class="badge-success badge-sm font-bold" />
                                @else
                                    @php
                                        $elapsed = $table['match_started_at']
                                            ? \Carbon\Carbon::parse($table['match_started_at'])->diffForHumans(short: true)
                                            : null;
                                    @endphp
                                    <x-badge value="{{ $elapsed ?? '—' }}" class="badge-ghost badge-xs" icon="o-clock" />
                                @endif
                            </div>

                            <div class="space-y-3">
                                @if (! $table['is_free'] && $table['match'])
                                    @php $match = $table['match']; @endphp
                                    <div class="bg-base-200 rounded-lg p-2 border border-base-300">
                                        <div class="text-[11px] font-bold truncate">{{ $match->player1?->full_name ?? '—' }}</div>
                                        <div class="flex items-center gap-2 my-1">
                                            <div class="h-px grow bg-base-300"></div>
                                            <span class="text-[9px] font-black opacity-30 italic">VS</span>
                                            <div class="h-px grow bg-base-300"></div>
                                        </div>
                                        <div class="text-[11px] text-right font-bold truncate">{{ $match->player2?->full_name ?? '—' }}</div>
                                    </div>

                                    @if ($match->sets->count())
                                        <div class="flex flex-wrap justify-center gap-1">
                                            @foreach ($match->sets as $set)
                                                <x-badge
                                                    value="{{ $set->player1_score }}-{{ $set->player2_score }}"
                                                    class="badge-info badge-soft font-mono text-[10px] px-2" />
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="flex gap-2 pt-1">
                                        <x-button label="{{ __('Score') }}" icon="o-pencil"
                                            class="btn-ghost btn-xs flex-1 bg-base-200"
                                            wire:click="openScoreEntry({{ $match->id }}, {{ $table['id'] }})" />
                                    </div>
                                @else
                                    <div class="py-4 flex flex-col items-center justify-center border-2 border-dashed border-base-300 rounded-lg gap-3">
                                        <x-button
                                            label="{{ __('Launch') }}"
                                            icon="o-play"
                                            class="btn-outline btn-sm text-success"
                                            wire:click="openLaunchDrawer({{ $table['id'] }})" />
                                    </div>
                                @endif

                                {{-- QR code — direct link to mobile score page --}}
                                <a href="{{ $tableUrl }}" target="_blank"
                                    class="w-full flex justify-center pt-1 opacity-40 hover:opacity-90 transition-opacity"
                                    title="{{ __('Open mobile score page') }}">
                                    {!! $svgSmall !!}
                                </a>
                            </div>
                        </x-card>
                    @endforeach
                </div>
            </div>
        @endforeach
    @endif
</div>
