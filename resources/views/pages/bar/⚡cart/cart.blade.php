<div class="max-w-2xl space-y-4">
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <div>
        <a href="{{ route('bar.index') }}" wire:navigate
            class="text-primary tap-min mb-2 inline-flex gap-1.5 text-sm font-semibold hover:underline">
            <x-icon name="o-arrow-left" class="h-4 w-4" />
            {{ __('Keep serving') }}
        </a>
    </div>

    <x-header progress-indicator separator
        :title="$this->tabName ?? __('Walk-in')"
        :subtitle="$this->tabName
            ? __('The tab total, to save or to settle.')
            : __('Check the items, then take payment.')" />

    @if ($this->items->isEmpty())
        <x-card>
            <x-empty-state
                icon="o-shopping-cart"
                :heading="__('Nothing on this order yet')"
                :message="__('Add products to get started.')">
                <x-button :label="__('Choose products')" icon="o-plus"
                    class="btn-primary btn-sm" :link="route('bar.index')" wire:navigate />
            </x-empty-state>
        </x-card>
    @else
        <x-card>
            <div class="divide-base-300 divide-y">
                @foreach ($this->items as $item)
                    @php
                        $product = $item['product'];
                        $atStockLimit = $item['quantity'] >= $product->stock;
                    @endphp

                    <div wire:key="line-{{ $product->id }}" class="flex items-center gap-3 py-3 first:pt-0">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold">{{ $product->name }}</p>
                            @if ($atStockLimit)
                                <p class="text-warning mt-0.5 flex items-center gap-1.5 text-xs font-semibold">
                                    <x-icon name="o-exclamation-triangle" class="h-3.5 w-3.5" />
                                    {{ __('Stock maximum reached') }}
                                </p>
                            @else
                                <p class="text-subtle mt-0.5 text-xs tabular-nums">
                                    {{ euros($product->sale_price) }} {{ __('each') }} · {{ $product->stock }} {{ __('in stock') }}
                                </p>
                            @endif
                        </div>

                        {{-- Même compteur qu'au comptoir : trois cibles de 44 px,
                        manipulables d'une main, et une mise à jour sur place. --}}
                        <div class="border-base-300 bg-base-100 flex shrink-0 items-stretch overflow-hidden rounded-lg border">
                            <button type="button" wire:click="remove({{ $product->id }})"
                                class="tap-comfort text-primary disabled:text-base-content/30 h-11 w-11"
                                aria-label="{{ __('Remove one :product', ['product' => $product->name]) }}">
                                <x-icon name="o-minus" class="h-4 w-4" />
                            </button>

                            <span class="border-base-300 flex h-11 w-11 items-center justify-center border-x text-base font-bold tabular-nums">
                                {{ $item['quantity'] }}
                            </span>

                            <button type="button" wire:click="add({{ $product->id }})"
                                class="tap-comfort text-primary disabled:text-base-content/30 h-11 w-11"
                                @disabled($atStockLimit)
                                aria-label="{{ __('Add one :product', ['product' => $product->name]) }}">
                                <x-icon name="o-plus" class="h-4 w-4" />
                            </button>
                        </div>

                        <span class="w-20 shrink-0 text-end text-sm font-bold tabular-nums">
                            {{ euros($item['total_price']) }}
                        </span>
                    </div>
                @endforeach
            </div>

            {{-- Le total : le chiffre que la page existe pour montrer, et celui qu'on
            lit à voix haute au client, à un mètre, dans un hall bruyant. --}}
            <div class="border-base-content mt-4 flex items-center justify-between border-t-2 pt-4">
                <span class="text-muted text-xs font-bold uppercase tracking-widest">{{ __('Total') }}</span>
                <span class="text-3xl font-black tabular-nums tracking-tight">{{ euros($this->totalPrice) }}</span>
            </div>

            <div class="mt-4 space-y-2.5">
                <div class="flex flex-wrap gap-2.5">
                    <x-button :label="__('Empty the order')" icon="o-trash"
                        wire:click="clear"
                        wire:confirm="{{ __('Empty this order?') }}"
                        class="btn-outline btn-error tap-comfort min-w-[9rem] flex-1" />

                    @if ($this->tabName)
                        <x-button :label="__('Save the tab')" icon="o-check"
                            wire:click="validateOrder('validate')" spinner="validateOrder"
                            class="btn-primary tap-comfort min-w-[9rem] flex-1" />
                    @endif
                </div>

                <x-button :label="__('Pay now')" icon="o-credit-card"
                    wire:click="validateOrder('pay_now')" spinner="validateOrder"
                    class="{{ $this->tabName ? 'btn-secondary' : 'btn-primary' }} tap-comfort w-full" />
            </div>
        </x-card>
    @endif
</div>
