<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="__('Inventories')"
        :subtitle="__('Every correction of the stock, who made it and when.')">
        <x-slot:actions>
            @if ($canCount)
                <x-button class="btn-primary" :icon="$inProgress ? 'o-arrow-right' : 'o-plus'"
                    :label="$inProgress ? __('Resume the inventory') : __('New inventory')"
                    :link="route('bar.inventories.current')" />
            @endif
        </x-slot:actions>
    </x-header>

    @if ($inProgress)
        <x-alert class="alert-info mb-4" icon="o-clipboard-document-check">
            {{ __('An inventory is in progress, opened by :name on :date · :count products counted.', [
                'name' => $inProgress->opener->full_name,
                'date' => $inProgress->opened_at->translatedFormat('l j F, H:i'),
                'count' => $inProgress->lines_count,
            ]) }}
        </x-alert>
    @endif

    <x-card class="!p-2 lg:!p-5">
        @if ($inventories->isEmpty())
            <x-empty-state icon="o-clipboard-document-check" :heading="__('No inventory yet')"
                :message="__('The first one starts from the button above.')" />
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('By') }}</th>
                            <th class="text-end">{{ __('Products') }}</th>
                            <th class="text-end">{{ __('Missing') }}</th>
                            <th class="hidden text-end sm:table-cell">{{ __('Surplus') }}</th>
                            <th class="hidden text-end md:table-cell">{{ __('Value at the selling price') }}</th>
                            <th>{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($inventories as $inventory)
                            @php $total = $totals[$inventory->id]; @endphp
                            <tr wire:key="inventory-{{ $inventory->id }}">
                                <td class="whitespace-nowrap">
                                    @if ($inventory->status === \App\Domains\Bar\Models\BarInventory::STATUS_VALIDATED)
                                        <a class="link link-primary" href="{{ route('bar.inventories.show', $inventory) }}" wire:navigate>{{ $inventory->opened_at->translatedFormat('D j M Y') }}</a>
                                    @else
                                        {{ $inventory->opened_at->translatedFormat('D j M Y') }}
                                    @endif
                                </td>
                                <td>{{ $inventory->opener->full_name }}</td>
                                <td class="text-end tabular-nums">{{ $inventory->lines_count }}</td>
                                <td class="text-end tabular-nums {{ $total['missing'] > 0 ? 'text-error font-semibold' : 'text-subtle' }}">{{ $total['missing'] > 0 ? '−' . $total['missing'] : '—' }}</td>
                                <td class="hidden text-end tabular-nums sm:table-cell {{ $total['surplus'] > 0 ? 'text-success font-semibold' : 'text-subtle' }}">{{ $total['surplus'] > 0 ? '+' . $total['surplus'] : '—' }}</td>
                                <td class="hidden text-end tabular-nums md:table-cell">{{ $inventory->status === \App\Domains\Bar\Models\BarInventory::STATUS_VALIDATED ? euros($total['value']) : '—' }}</td>
                                <td>
                                    @switch($inventory->status)
                                        @case(\App\Domains\Bar\Models\BarInventory::STATUS_IN_PROGRESS)
                                            <span class="badge badge-info badge-soft whitespace-nowrap">{{ __('In progress') }}</span>
                                            @break
                                        @case(\App\Domains\Bar\Models\BarInventory::STATUS_CANCELLED)
                                            <span class="badge badge-ghost whitespace-nowrap">{{ __('Cancelled by :name', ['name' => $inventory->closer?->full_name]) }}</span>
                                            @break
                                        @default
                                            <span class="badge badge-ghost whitespace-nowrap">{{ __('Validated') }}</span>
                                    @endswitch
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $inventories->links() }}</div>
        @endif
    </x-card>
</div>
