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

    @if ($closing && $isMine)
        {{--
            Le retour du magasin. Pré-rempli par les cases cochées : on ne touche
            qu'aux exceptions. Les boutons − / + changent le champ côté navigateur,
            sans aller-retour au serveur — tout part en une fois à la validation.
        --}}
        <x-card class="mb-4" :title="__('What did you buy?')"
            :subtitle="__('In packs. 0 = not found. The units enter the stock when you confirm.')">
            <ul class="divide-base-300 divide-y">
                @foreach ($trip->lines as $line)
                    @continue($line->section === 'extra')
                    <li class="flex items-center gap-3 py-2" wire:key="bought-{{ $line->id }}">
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold">{{ $line->product->name }}</p>
                            <p class="text-subtle text-xs">
                                {{ $line->pack_label ? __(':label of :size', ['label' => $line->pack_label, 'size' => $line->pack_size]) : __('packs of :size', ['size' => $line->pack_size]) }}
                                · {{ __('proposed: :packs', ['packs' => $line->proposed_packs]) }}
                            </p>
                        </div>
                        <x-bar.pack-stepper model="bought.{{ $line->id }}" :label="$line->product->name" />
                    </li>
                @endforeach

                @foreach ($extras as $productId => $packs)
                    @php
                        $extra = $extraProducts[$productId] ?? null;
                    @endphp
                    @continue($extra === null)
                    <li class="flex items-center gap-3 py-2" wire:key="extra-{{ $productId }}">
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold">{{ $extra->name }}</p>
                            <p class="text-subtle text-xs">
                                {{ __('Not on the list') }} ·
                                {{ $extra->pack_label ? __(':label of :size', ['label' => $extra->pack_label, 'size' => $extra->pack_size]) : __('packs of :size', ['size' => $extra->pack_size]) }}
                            </p>
                        </div>
                        <x-bar.pack-stepper model="extras.{{ $productId }}" :label="$extra->name" />
                        <x-button icon="o-x-mark" class="btn-ghost btn-sm btn-circle" wire:click="removeExtra({{ $productId }})"
                            :tooltip="__('Remove')" />
                    </li>
                @endforeach
            </ul>

            <div class="mt-3 flex items-end gap-2">
                <x-select :label="__('Something else?')" :options="$extraOptions" wire:model="extraProductId"
                    :placeholder="__('Choose a bar product')" class="select-sm" />
                <x-button class="btn-sm" icon="o-plus" :label="__('Add')" wire:click="addExtra" />
            </div>

            {{--
                La dernière question. Payé de sa poche : la note de frais part avec
                la clôture, pré-remplie ; il ne reste que le total et la photo du
                ticket. Un mineur, ou quelqu'un qui agit pour un autre, ne voit pas
                cette option — la même règle que sur l'écran des notes de frais.
            --}}
            <div class="border-base-300 mt-4 border-t pt-4">
                <p class="mb-2 font-semibold">{{ __('Who paid?') }}</p>
                <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                    @foreach (array_filter([
                        'me' => $canClaim ? __('I paid, I want to be refunded') : null,
                        'club' => __('The club (card, cash box)'),
                        'nobody' => __('Nobody, it is a gift'),
                    ]) as $value => $label)
                        <button type="button" wire:click="$set('paidBy', '{{ $value }}')" wire:key="paid-by-{{ $value }}"
                            @class([
                                'btn btn-sm tap-min justify-start',
                                'btn-primary' => $paidBy === $value,
                                'btn-outline' => $paidBy !== $value,
                            ])>
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
                @error('paidBy')
                    <p class="text-error mt-1 text-sm">{{ $message }}</p>
                @enderror

                @if ($paidBy === 'me')
                    <div class="mt-4 space-y-3">
                        <x-input wire:model="ticketAmount" :label="__('Receipt total')" inputmode="decimal" suffix="€"
                            :hint="__('VAT included, as on the receipt')" />

                        <div>
                            <x-file wire:model="ticketFiles" :label="__('Receipt')" multiple accept=".pdf,.jpg,.jpeg,.png,.webp"
                                :hint="__('A photo of the receipt is enough. PDF, JPG, PNG or WebP, 10 MB each.')" />
                            @error('ticketFiles.*')
                                <p class="text-error mt-1 text-sm">{{ $message }}</p>
                            @enderror
                            @if (count($ticketFiles) > 0)
                                <ul class="mt-2 space-y-1 text-sm">
                                    @foreach ($ticketFiles as $index => $upload)
                                        <li wire:key="ticket-file-{{ $index }}" class="flex items-center justify-between gap-2">
                                            <span class="truncate">{{ $upload->getClientOriginalName() }}</span>
                                            <x-button icon="o-x-mark" class="btn-ghost btn-xs" :title="__('Remove')"
                                                wire:click="removeTicketFile({{ $index }})" />
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        <x-input wire:model="refundIban" :label="__('Refund account (IBAN)')"
                            :hint="__('Prefilled from your profile; change it if the money should go elsewhere.')" />

                        <p class="text-subtle text-xs">{{ __('The expense report is submitted when you confirm, with what you bought in its description. The treasury takes it from there.') }}</p>
                    </div>
                @endif
            </div>

            <x-slot:actions>
                <x-button :label="__('Back to the list')" wire:click="$set('closing', false)" />
                <x-button class="btn-primary" icon="o-check" :label="__('Confirm and fill the stock')"
                    wire:click="close" spinner="close" />
            </x-slot:actions>
        </x-card>
    @else
        <div class="mb-4 flex flex-wrap gap-2">
            @if ($isMine)
                <x-button class="btn-primary w-full sm:w-auto" icon="o-home"
                    :label="__('I am back: enter what I bought')" wire:click="openClosing" spinner="openClosing" />
            @endif

            @if ($listText !== '')
                {{-- Le texte voyage dans un attribut plutôt que par un aller-retour :
                le presse-papiers exige un geste de l'utilisateur, qu'un appel réseau
                intercalé ferait perdre sur Safari. --}}
                <button type="button" class="btn btn-outline btn-sm tap-min"
                    x-data="{ copied: false }"
                    data-list="{{ $listText }}"
                    @click="navigator.clipboard.writeText($el.dataset.list).then(() => { copied = true; setTimeout(() => copied = false, 2000) })">
                    <x-icon name="o-clipboard-document" class="h-4 w-4" />
                    <span x-show="! copied">{{ __('Copy the list') }}</span>
                    <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                </button>
            @endif
        </div>

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
    @endif
</div>
