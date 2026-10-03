<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator
        :title="__('Inventory of :date', ['date' => $inventory->opened_at->translatedFormat('j F Y')])"
        :subtitle="__('Opened by :opener on :opened, validated by :closer on :closed.', [
            'opener' => $inventory->opener->full_name,
            'opened' => $inventory->opened_at->translatedFormat('l j F, H:i'),
            'closer' => $inventory->closer?->full_name,
            'closed' => $inventory->closed_at?->translatedFormat('l j F, H:i'),
        ])" />

    <div class="mb-4 grid gap-3 sm:grid-cols-3">
        <x-stat :title="__('Missing')" :value="$totals['missing'] > 0 ? '−' . $totals['missing'] : '0'" icon="o-arrow-trending-down" />
        <x-stat :title="__('Surplus')" :value="$totals['surplus'] > 0 ? '+' . $totals['surplus'] : '0'" icon="o-arrow-trending-up" />
        <x-stat :title="__('Value at the selling price')" :value="euros($totals['value'])" icon="o-banknotes" />
    </div>

    @if ($inventory->comment)
        <x-alert class="mb-4" icon="o-chat-bubble-left-ellipsis" :title="$inventory->comment" />
    @endif

    <x-card class="!p-2 lg:!p-5">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th class="w-full">{{ __('Product') }}</th>
                        <th class="text-end">{{ __('Expected') }}</th>
                        <th class="text-end">{{ __('Counted') }}</th>
                        <th class="text-end">{{ __('Gap') }}</th>
                        <th class="hidden text-end sm:table-cell">{{ __('Value') }}</th>
                        <th>{{ __('What happened') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lines as $line)
                        <tr wire:key="line-{{ $line->id }}">
                            <td>
                                <p class="font-semibold">{{ $line->product->name }}</p>
                                @if ($line->note)
                                    <p class="text-subtle text-xs">{{ $line->note }}</p>
                                @endif
                            </td>
                            <td class="text-end tabular-nums">{{ $line->expected }}</td>
                            <td class="text-end tabular-nums">{{ $line->counted }}</td>
                            <td @class(['text-end tabular-nums font-semibold', 'text-error' => $line->gap < 0, 'text-success' => $line->gap > 0, 'text-subtle font-normal' => $line->gap === 0])>
                                {{ $line->gap > 0 ? '+' . $line->gap : $line->gap }}
                            </td>
                            <td class="hidden text-end tabular-nums sm:table-cell">
                                {{ $line->gap !== 0 && $line->cause !== \App\Domains\Shared\Enums\BarInventoryCause::AddedToBar ? euros($line->gap * (int) $line->unit_price) : '—' }}
                            </td>
                            <td>
                                @if ($line->cause)
                                    <span class="badge badge-ghost whitespace-nowrap">{{ $line->cause->label() }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-subtle">{{ __('No gap: every product counted was right.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($hiddenCount > 0)
            <p class="text-subtle px-2 pt-3 text-sm">
                {{ trans_choice(':count product without a gap, hidden.|:count products without a gap, hidden.', $hiddenCount) }}
                <button type="button" class="link link-primary" wire:click="$set('showAll', true)">{{ __('Show all') }}</button>
            </p>
        @endif
    </x-card>
</div>
