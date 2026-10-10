<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header progress-indicator separator :title="__('Coaching staff')"
        :subtitle="__('Sessions coached once attendance is recorded, and sparring presences confirmed by the coach.')">
        <x-slot:actions>
            <x-select class="select-sm" wire:model.live="period" :options="$this->periodOptions" />
            <x-dropdown :id="'staff-export'" right>
                <x-slot:trigger>
                    <x-button class="btn-ghost btn-sm" icon="o-arrow-down-tray" :label="__('Export')" />
                </x-slot:trigger>
                <x-menu-item :title="__('Excel (.xlsx)')" wire:click="export('xlsx')" />
                <x-menu-item :title="__('CSV')" wire:click="export('csv')" />
            </x-dropdown>
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            @if ($this->rows === [])
                <div class="py-10 text-center text-base-content/40">
                    <x-icon class="mx-auto mb-2 h-8 w-8" name="o-user-group" />
                    <p>{{ __('Nobody coached or sparred over this period.') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('Name') }}</th>
                                <th class="text-right">{{ __('Sessions coached') }}</th>
                                <th class="text-right">{{ __('Sparring presences') }}</th>
                                <th class="text-right">{{ __('Still to record') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->rows as $row)
                                <tr wire:key="staff-{{ $row['user_id'] }}" @class(['bg-base-200' => $detailUserId === $row['user_id']])>
                                    <td>
                                        <button type="button" class="cursor-pointer font-medium hover:text-primary"
                                            wire:click="showDetail({{ $row['user_id'] }})">{{ $row['name'] }}</button>
                                    </td>
                                    <td class="text-right tabular-nums">{{ $row['coached'] }}</td>
                                    <td class="text-right tabular-nums">{{ $row['sparred'] }}</td>
                                    <td class="text-right tabular-nums">
                                        @if ($row['pending'] > 0)
                                            <span class="badge badge-warning badge-soft">{{ $row['pending'] }}</span>
                                        @else
                                            0
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <x-card :title="$this->detailUser?->full_name ?? __('Detail')">
            @if (! $this->detailUser)
                <p class="text-sm text-subtle">{{ __('Choose a person to see their sessions one by one.') }}</p>
            @else
                <ul class="space-y-2 text-sm">
                    @forelse ($this->detail as $line)
                        <li class="flex items-center justify-between gap-3 border-b border-base-300 pb-2 last:border-0">
                            <div>
                                <p class="font-medium">{{ \Carbon\Carbon::parse($line['date'])->format('d/m/Y') }}</p>
                                <p class="text-xs text-base-content/60">
                                    {{ $line['pack'] }} · {{ $line['role'] === 'coach' ? __('Coach') : __('Sparring') }}
                                </p>
                            </div>
                            @php
                                [$label, $class] = match ($line['status']) {
                                    'counted' => [__('Recorded'), 'badge-success'],
                                    'present' => [__('Present'), 'badge-success'],
                                    'absent' => [__('Absent'), 'badge-error'],
                                    default => [__('To record'), 'badge-warning'],
                                };
                            @endphp
                            <x-badge :value="$label" class="{{ $class }} badge-soft" />
                        </li>
                    @empty
                        <li class="text-subtle">{{ __('No session over this period.') }}</li>
                    @endforelse
                </ul>
            @endif
        </x-card>
    </div>
</div>
