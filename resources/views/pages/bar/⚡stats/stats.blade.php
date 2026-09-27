<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="__('Bar sales')"
        :subtitle="__('What sells, what sleeps: a guide for the next purchases.')" />

    {{--
        La période est une navigation, pas un filtre (R2) : elle vaut toujours
        exactement une valeur et titre ce qui suit. Les dates restent visibles et
        modifiables : en saisir une, c'est quitter le preset.
    --}}
    <div class="mb-4 flex flex-wrap items-end gap-x-3 gap-y-2">
        <div class="join flex-wrap">
            @foreach ($presets as $key => $label)
                <button type="button" wire:click="$set('period', '{{ $key }}')"
                    wire:key="preset-{{ $key }}"
                    @class([
                        'join-item btn btn-sm tap-min',
                        'btn-primary' => $period === $key,
                        'btn-outline' => $period !== $key,
                    ])>
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="flex items-end gap-2">
            <x-input :label="__('From')" type="date" wire:model.live="firstDay" class="input-sm" />
            <x-input :label="__('To')" type="date" wire:model.live="lastDay" class="input-sm" />
        </div>
    </div>

    <p class="text-subtle mb-4 text-xs">
        {{ __('Paid orders and offered drinks. Compared with the :days days just before.', ['days' => $days]) }}
    </p>

    <div class="space-y-4">
        @foreach ($report as $group)
            <div wire:key="category-{{ $group['category'] }}">
                <h2 class="text-muted mb-2 text-xs font-bold uppercase tracking-widest">
                    {{ $group['category'] }}
                    <span class="text-subtle tabular-nums">· {{ $group['units'] }} · {{ euros($group['revenue']) }}</span>
                </h2>

                <x-card class="!p-2 lg:!p-5">
                    <table class="table-sm table">
                        <thead>
                            <tr>
                                <th class="w-full">{{ __('Product') }}</th>
                                <th class="text-end">{{ __('Sold') }}</th>
                                <th class="hidden text-end sm:table-cell">{{ __('Per week') }}</th>
                                <th class="text-end">{{ __('Trend') }}</th>
                                <th class="hidden text-end lg:table-cell">{{ __('Takings') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($group['products'] as $product)
                                <tr wire:key="product-{{ $product['id'] }}" @class(['text-muted' => $product['units'] === 0])>
                                    <td class="max-w-0">
                                        <span class="block truncate font-medium">{{ $product['name'] }}</span>
                                    </td>
                                    <td class="text-end tabular-nums">
                                        {{-- Zéro se dit en toutes lettres : c'est la ligne que le
                                        comité est venu chercher, un produit qui risque de périmer. --}}
                                        @if ($product['units'] === 0)
                                            <span class="badge badge-warning badge-soft badge-sm whitespace-nowrap">{{ __('Not sold') }}</span>
                                        @else
                                            <span class="font-bold">{{ $product['units'] }}</span>
                                        @endif
                                    </td>
                                    <td class="hidden text-end tabular-nums sm:table-cell">
                                        {{ \Illuminate\Support\Number::format($product['weekly'], maxPrecision: 1, locale: app()->getLocale()) }}
                                    </td>
                                    <td class="whitespace-nowrap text-end tabular-nums">
                                        @if ($product['change'] === null)
                                            <span class="text-subtle">{{ $product['units'] > 0 ? __('New') : '—' }}</span>
                                        @elseif ($product['change'] > 0)
                                            <span class="text-success">↑ {{ $product['change'] }} %</span>
                                        @elseif ($product['change'] < 0)
                                            <span class="text-error">↓ {{ abs($product['change']) }} %</span>
                                        @else
                                            <span class="text-subtle">=</span>
                                        @endif
                                    </td>
                                    <td class="hidden text-end tabular-nums lg:table-cell">{{ euros($product['revenue']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-card>
            </div>
        @endforeach
    </div>
</div>
