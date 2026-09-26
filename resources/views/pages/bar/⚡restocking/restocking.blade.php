<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="__('Shopping')"
        :subtitle="__('What to buy to fill the bar, in packs.')" />

    @if ($trip === null)
        @if ($sections['to_buy'] === [])
            <x-card class="mb-4">
                <x-empty-state icon="o-check-circle" :heading="__('Nothing needs buying right now.')"
                    :message="__('Every product is above its min.')" />
            </x-card>
        @else
            <x-button class="btn-primary mb-4 w-full sm:w-auto" icon="o-shopping-cart"
                :label="__('I am doing the shopping')" wire:click="start" spinner="start" />
        @endif
    @endif

    @if ($trip !== null)
        <x-alert :class="'mb-4 ' . ($isMine ? 'alert-info' : 'alert-warning')" icon="o-shopping-cart">
            <p class="font-semibold">
                {{ $isMine ? __('You are doing the shopping') : __(':name is doing the shopping', ['name' => $trip->shopper->full_name]) }}
            </p>
            <p class="text-sm">{{ __('Since :date', ['date' => $trip->started_at->translatedFormat('l j F, H:i')]) }}</p>

            <x-slot:actions>
                @unless ($isMine)
                    <x-button class="btn-sm" :label="__('Take over')" wire:click="$set('takeOverModal', true)" />
                @endunless
                <x-button class="btn-ghost btn-sm" :label="__('Give up')" wire:click="$set('abandonModal', true)" />
            </x-slot:actions>
        </x-alert>

        {{-- Rien n'interdit de reprendre ou d'abandonner la tournée d'un autre : on
        conseille seulement de l'appeler d'abord, ses coordonnées sous les yeux. --}}
        @unless ($isMine)
            <x-confirm-modal model="takeOverModal" :title="__('Take over the trip of :name?', ['name' => $trip->shopper->full_name])"
                :confirmLabel="__('Take over')" confirmAction="takeOver" :open="$takeOverModal">
                <x-bar.restocking-contact :shopper="$trip->shopper" />
            </x-confirm-modal>
        @endunless

        <x-confirm-modal model="abandonModal" :title="__('Abandon the trip?')"
            :confirmLabel="__('Give up')" confirmAction="abandon" :open="$abandonModal">
            @if ($isMine)
                <p>{{ __('The list becomes free for someone else. What is ticked is lost.') }}</p>
            @else
                <x-bar.restocking-contact :shopper="$trip->shopper" />
            @endif
        </x-confirm-modal>
    @endif

    @foreach (['to_buy' => __('To buy'), 'if_room' => __('If you have room')] as $section => $title)
        @if ($sections[$section] !== [])
            <section class="mb-6" wire:key="section-{{ $section }}">
                <h2 class="mb-2 text-sm font-bold">{{ $title }}</h2>

                <div class="space-y-3">
                    @foreach ($sections[$section] as $category => $lines)
                        <x-card class="!p-2 sm:!p-4" wire:key="{{ $section }}-{{ $category }}">
                            <h3 class="text-muted mb-1 px-2 text-xs font-bold uppercase tracking-widest">{{ $category }}</h3>

                            <ul class="divide-base-300 divide-y">
                                @foreach ($lines as $line)
                                    <li class="flex items-center gap-3 px-2 py-2" wire:key="line-{{ $section }}-{{ $line['name'] }}">
                                        @if ($isMine)
                                            {{-- La case entière est la cible : un pouce, un caddie. --}}
                                            <label class="tap-comfort cursor-pointer">
                                                <input type="checkbox" class="checkbox checkbox-primary"
                                                    wire:key="cart-{{ $line['line_id'] }}"
                                                    @checked($line['in_cart'])
                                                    wire:change="toggleInCart({{ $line['line_id'] }}, $event.target.checked)"
                                                    aria-label="{{ __('In the cart: :product', ['product' => $line['name']]) }}">
                                            </label>
                                        @endif
                                        <div @class(['min-w-0 flex-1', 'opacity-50 line-through' => $line['in_cart']])>
                                            <p class="truncate text-base font-semibold">{{ $line['name'] }}</p>
                                            <p class="text-subtle text-xs tabular-nums">
                                                {{ __(':units units · stock :stock / max :max', ['units' => $line['units'], 'stock' => $line['stock'], 'max' => $line['max']]) }}
                                            </p>
                                        </div>
                                        <span class="whitespace-nowrap text-lg font-bold tabular-nums">{{ $line['packs_label'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </x-card>
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach
</div>
