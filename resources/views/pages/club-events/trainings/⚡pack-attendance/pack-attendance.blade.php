{{-- ================================================================
     ATTENDANCE MATRIX — one grid answers both questions:
     a hollow column is a session nobody came to, a hollow row is a
     member who pays and never shows up.
================================================================ --}}
<div>
    @php
        $matrix = $this->attendanceMatrix;
        $rateTone = fn (?int $rate) => match (true) {
            $rate === null => 'text-base-content/30',
            $rate >= 70 => 'text-success',
            $rate >= 40 => 'text-warning',
            default => 'text-error',
        };
    @endphp

    <div class="mb-3 flex items-center justify-end">
        <label class="flex cursor-pointer items-center gap-2 text-xs text-base-content/60">
            <x-checkbox wire:model.live="showAllSessions" />
            {{ __('Show the whole season') }}
        </label>
    </div>

    @if (empty($matrix['sessions']))
        <div class="rounded-xl border border-dashed border-base-300 py-16 text-center text-base-content/40">
            <x-icon class="mx-auto mb-2 h-10 w-10" name="o-check-circle" />
            <p>{{ __('No session held yet.') }}</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-base-300 bg-base-100">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th class="sticky left-0 z-10 bg-base-100">{{ __('Member') }}</th>
                        @foreach ($matrix['sessions'] as $column)
                            <th @class([
                                'text-center text-xs font-medium',
                                'text-base-content/30 line-through' => $column['cancelled'],
                            ])>
                                {{ \Carbon\Carbon::parse($column['date'])->format('d/m') }}
                            </th>
                        @endforeach
                        <th class="text-center">{{ __('Rate') }}</th>
                    </tr>
                </thead>

                <tbody>
                    {{-- Membres, puis non-membres du stage (attendus eux aussi), puis
                         les présents non inscrits, chaque groupe séparé d'un trait. --}}
                    @php
                        $rows = [
                            ...array_map(fn (array $row): array => $row + ['kind' => 'member'], $matrix['members']),
                            ...array_map(fn (array $row): array => $row + ['kind' => 'external'], $matrix['externals'] ?? []),
                            ...array_map(fn (array $row): array => $row + ['kind' => 'walkIn'], $matrix['walkIns']),
                        ];
                    @endphp
                    @foreach ($rows as $index => $row)
                        @php $startsGroup = $index > 0 && $rows[$index - 1]['kind'] !== $row['kind']; @endphp
                        <tr @class([
                            'border-t-2 border-accent/40' => $startsGroup && $row['kind'] === 'external',
                            'border-t-2 border-warning/40' => $startsGroup && $row['kind'] === 'walkIn',
                        ])>
                            <td class="sticky left-0 z-10 whitespace-nowrap bg-base-100">
                                {{ $row['name'] }}
                                @if ($row['kind'] === 'external')
                                    <x-badge class="badge-accent badge-soft badge-xs ml-1"
                                        :value="__('non-member')" />
                                @elseif ($row['kind'] === 'walkIn')
                                    <x-badge class="badge-warning badge-soft badge-xs ml-1"
                                        :value="__('not enrolled')" />
                                @endif
                            </td>

                            @foreach ($matrix['sessions'] as $column)
                                @php $cell = $row['cells'][$column['id']] ?? null; @endphp
                                <td class="text-center">
                                    @if ($column['cancelled'])
                                        <span class="text-base-content/20">—</span>
                                    @elseif (! $column['counted'])
                                        {{-- Hachuré : « pas pointé », à ne pas confondre avec « absent ». --}}
                                        <span class="inline-block h-3 w-3 rounded-sm bg-base-300/60"
                                            title="{{ __('Not recorded') }}"></span>
                                    @elseif ($cell === 'present')
                                        <x-icon class="h-4 w-4 text-success" name="o-check" />
                                    @elseif ($cell === 'excused')
                                        <x-icon class="h-4 w-4 text-warning" name="o-minus" />
                                    @elseif ($cell === 'absent')
                                        <x-icon class="h-4 w-4 text-error" name="o-x-mark" />
                                    @else
                                        <span class="text-base-content/20">·</span>
                                    @endif
                                </td>
                            @endforeach

                            <td @class(['text-center text-sm font-bold', $rateTone($row['rate'] ?? null)])>
                                {{ isset($row['rate']) ? $row['rate'] . '%' : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>

                <tfoot>
                    <tr>
                        <th class="sticky left-0 z-10 bg-base-100 text-xs font-bold uppercase tracking-wide text-base-content/50">
                            {{ __('Turnout') }}
                        </th>
                        @foreach ($matrix['sessions'] as $column)
                            <th @class(['text-center text-xs font-bold', $rateTone($column['rate'])])>
                                {{ $column['rate'] !== null ? $column['rate'] . '%' : '—' }}
                            </th>
                        @endforeach
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="mt-2 flex flex-wrap items-center gap-4 text-xs text-base-content/50">
            <span class="flex items-center gap-1">
                <x-icon class="h-3.5 w-3.5 text-success" name="o-check" /> {{ __('Present') }}
            </span>
            <span class="flex items-center gap-1">
                <x-icon class="h-3.5 w-3.5 text-warning" name="o-minus" /> {{ __('Excused') }}
            </span>
            <span class="flex items-center gap-1">
                <x-icon class="h-3.5 w-3.5 text-error" name="o-x-mark" /> {{ __('Absent') }}
            </span>
            <span class="flex items-center gap-1">
                <span class="inline-block h-3 w-3 rounded-sm bg-base-300/60"></span> {{ __('Not recorded') }}
            </span>
            <span class="flex items-center gap-1">
                <span class="text-base-content/20">—</span> {{ __('Cancelled session') }}
            </span>
        </div>
    @endif
</div>
