<div data-bar-counter>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    {{--
        Mode comptoir : sur téléphone, le fil d'Ariane disparaît (règle
        `[data-bar-counter]` de app.css) et l'écran commence par la barre d'outils. Elle reste collée sous
        la barre de navigation : les rayons et les ardoises se rejoignent sans remonter.
    --}}
    <div class="bg-base-200 border-base-300 sticky top-15 z-10 -mx-5 -mt-5 border-b px-5 pb-2 pt-2 lg:top-0 lg:-mx-10 lg:mt-0 lg:px-10"
        x-data="{ searching: @js($search !== '') }">
        <div class="flex items-center gap-2">
            @if ($this->editingTab)
                {{-- Le nom de l'ardoise qu'on sert : sans lui, rien ne dit où partira la tournée. --}}
                <div class="bg-primary/10 text-primary flex min-w-0 flex-1 items-center gap-1 rounded-full py-1 pe-1 ps-3 text-sm font-bold">
                    <span class="min-w-0 flex-1 truncate">→ {{ $this->editingTab }}</span>
                    <button type="button" wire:click="leaveTab" class="tap-min grid h-8 w-8 shrink-0 place-items-center rounded-full"
                        aria-label="{{ __('Stop serving on this tab') }}">
                        <x-icon name="o-x-mark" class="h-4 w-4" />
                    </button>
                </div>
            @else
                <h1 class="min-w-0 flex-1 truncate text-lg font-bold tracking-tight">{{ __('New order') }}</h1>
            @endif

            <button type="button" class="btn btn-ghost btn-sm tap-comfort btn-square" x-on:click="searching = ! searching; $nextTick(() => searching && $refs.search.focus())"
                :aria-expanded="searching" aria-label="{{ __('Search products') }}">
                <x-icon name="o-magnifying-glass" class="h-5 w-5" />
            </button>

            <button type="button" wire:click="showTabs" class="btn btn-outline btn-sm tap-comfort shrink-0 gap-1.5">
                {{ __('Tabs') }}
                <span class="badge badge-primary badge-sm font-bold tabular-nums">{{ $this->openTabs->count() }}</span>
            </button>
        </div>

        <div x-show="searching" x-cloak class="mt-2">
            <input type="search" x-ref="search" wire:model.live.debounce.200ms="search"
                class="input input-bordered tap-comfort w-full" placeholder="{{ __('Search products') }}"
                aria-label="{{ __('Search products') }}">
        </div>

        @if ($search === '' && $this->catalogue->count() > 1)
            <nav class="-mx-1 mt-2 flex gap-1.5 overflow-x-auto px-1 pb-0.5" aria-label="{{ __('Categories') }}">
                @foreach ($this->catalogue as $category)
                    <a href="#bar-cat-{{ $category->id }}" class="btn btn-outline btn-sm tap-comfort shrink-0 rounded-full">
                        {{ $category->name }}
                    </a>
                @endforeach
            </nav>
        @endif
    </div>

    {{--
        La carte en tuiles. Toute la tuile est la cible : un tap, une consommation. Le
        retrait reste à portée sur la tuile même, mais en petit, parce qu'il est rare.
    --}}
    <div class="mt-4 space-y-5 pb-4">
        @forelse ($this->catalogue as $category)
            <section id="bar-cat-{{ $category->id }}" class="scroll-mt-40 lg:scroll-mt-28" wire:key="cat-{{ $category->id }}">
                <h2 class="text-muted mb-2 text-xs font-bold uppercase tracking-widest">{{ $category->name }}</h2>

                <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-6">
                    @foreach ($category->products as $product)
                        @php
                            $state = $this->stateOf($product);
                        @endphp

                        <div wire:key="tile-{{ $product->id }}" class="relative">
                            <button type="button" wire:click="add({{ $product->id }})"
                                @disabled($state->disablePlus)
                                aria-label="{{ __('Add one :product', ['product' => $product->name]) }}"
                                @class([
                                    'bar-tile flex min-h-[4.75rem] w-full flex-col justify-between gap-1 rounded-xl border p-2 text-start transition active:scale-[.97]',
                                    'border-primary bg-primary/10 ring-primary ring-1' => $state->qty > 0,
                                    'border-base-300 bg-base-100' => $state->qty === 0,
                                    'opacity-50' => $state->isUnavailable || $state->isOut,
                                ])>
                                <span class="line-clamp-2 text-sm font-semibold leading-tight">{{ $product->name }}</span>
                                @if ($state->note)
                                    <span class="text-warning-content text-xs font-bold">{{ $state->note }}</span>
                                @else
                                    <span class="text-muted text-xs tabular-nums">{{ euros($product->sale_price) }}</span>
                                @endif
                            </button>

                            @if ($state->qty > 0)
                                <span class="bg-primary text-primary-content pointer-events-none absolute -end-1 -top-1.5 grid h-6 min-w-6 place-items-center rounded-full px-1.5 text-xs font-black tabular-nums">
                                    {{ $state->qty }}
                                </span>
                                <button type="button" wire:click="remove({{ $product->id }})"
                                    class="border-base-300 bg-base-100 absolute bottom-1 end-1 grid h-8 w-8 place-items-center rounded-lg border font-black"
                                    aria-label="{{ __('Remove one :product', ['product' => $product->name]) }}">
                                    <x-icon name="o-minus" class="h-4 w-4" />
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @empty
            <p class="text-muted py-8 text-center text-sm">{{ __('No product matches this search.') }}</p>
        @endforelse
    </div>

    {{--
        Le pied du comptoir, collé sous le pouce : la barre d'annulation pendant les
        10 secondes d'un cash ou d'un verre du club, puis « Terminer ». Collé
        (`sticky`) et non fixé : le drawer du layout porte un transform qui piège tout
        descendant en `position: fixed`.
    --}}
    <div class="bg-base-200 sticky bottom-0 z-10 -mx-5 space-y-2 px-5 pb-[max(.75rem,env(safe-area-inset-bottom))] pt-2 lg:-mx-10 lg:px-10">
        @if ($this->pending)
            <div wire:key="pending-{{ $this->pending['order_id'] }}" role="status"
                x-data="{
                    left: {{ $this->pendingSecondsLeft }},
                    timer: null,
                    init() {
                        this.timer = setInterval(() => {
                            this.left = Math.max(0, this.left - 1);
                            if (this.left === 0) { clearInterval(this.timer); $wire.confirmPending(); }
                        }, 1000);
                    },
                    destroy() { clearInterval(this.timer); },
                }"
                class="bg-base-content text-base-100 relative flex items-center gap-3 overflow-hidden rounded-xl py-1.5 pe-1.5 ps-4">
                <span class="min-w-0 flex-1 truncate text-sm font-semibold">{{ $this->pending['label'] }}</span>
                <button type="button" wire:click="cancelPending" class="btn btn-warning tap-comfort shrink-0">
                    {{ __('Cancel') }} · <span class="tabular-nums" x-text="left">{{ $this->pendingSecondsLeft }}</span>
                </button>
                <span class="bg-warning absolute bottom-0 start-0 h-0.5 transition-[width] duration-1000 ease-linear"
                    :style="`width: ${left * {{ 100 / $undoSeconds }}}%`"></span>
            </div>
        @endif

        @if ($this->cartCount > 0)
            <button type="button" wire:click="openFinishSheet" class="btn btn-primary tap-comfort h-14 w-full justify-between rounded-xl px-4 text-base">
                <span>
                    {{ $this->editingTab ? __('Finish :name', ['name' => $this->editingTab]) : __('Finish') }}
                    · {{ trans_choice(':count article|:count articles', $this->cartCount, ['count' => $this->cartCount]) }}
                </span>
                <span class="font-black tabular-nums">{{ euros($this->totalPrice) }}</span>
            </button>
        @endif
    </div>

    {{-- ─── Terminer : le récapitulatif, et où va la tournée ─── --}}
    <x-app-modal wire:model="finishSheet" :title="__('Finish the order')" :open="$finishSheet"
        class="modal-bottom sm:modal-middle">
        <div class="divide-base-300 divide-y">
            @foreach ($this->catalogue->flatMap->products->filter(fn ($product) => isset($this->cart[$product->id])) as $product)
                <div wire:key="line-{{ $product->id }}" class="flex items-center gap-2 py-1.5 text-sm">
                    <span class="w-7 shrink-0 font-bold tabular-nums">{{ $this->cart[$product->id] }}×</span>
                    <span class="min-w-0 flex-1 truncate">{{ $product->name }}</span>
                    <span class="shrink-0 tabular-nums">{{ euros($product->sale_price * $this->cart[$product->id]) }}</span>
                    <button type="button" wire:click="remove({{ $product->id }})"
                        class="border-base-300 grid h-9 w-9 shrink-0 place-items-center rounded-lg border"
                        aria-label="{{ __('Remove one :product', ['product' => $product->name]) }}">
                        <x-icon name="o-minus" class="h-4 w-4" />
                    </button>
                </div>
            @endforeach
        </div>

        <div class="border-base-content mt-2 flex items-baseline justify-between border-t-2 pt-3">
            <span class="text-muted text-xs font-bold uppercase tracking-widest">{{ __('Total') }}</span>
            <span class="text-3xl font-black tabular-nums tracking-tight">{{ euros($this->totalPrice) }}</span>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-2">
            @if ($this->canPay)
                <p class="text-muted col-span-2 text-xs font-bold uppercase tracking-widest">{{ __('Settle now') }}</p>
                <x-button :label="__('Pay cash')" icon="o-banknotes" wire:click="payCash" spinner="payCash"
                    class="btn-primary tap-comfort h-14 text-base" />
                <x-button :label="__('QR code')" icon="o-qr-code" wire:click="payQr" spinner="payQr"
                    class="btn-outline tap-comfort h-14 text-base" />
                <p class="text-muted col-span-2 mt-1 text-xs font-bold uppercase tracking-widest">{{ __('Or') }}</p>
            @endif

            @if ($this->editingTab)
                <x-button :label="__('Save :name', ['name' => $this->editingTab])" icon="o-check"
                    wire:click="putOnTab(null)" spinner="putOnTab"
                    class="btn-outline tap-comfort {{ $this->canPay ? '' : 'col-span-2' }} h-14 text-base" />
            @else
                <x-button :label="__('Put on a tab')" icon="o-clipboard-document-list" wire:click="openTabSheet"
                    class="btn-outline tap-comfort {{ $this->canPay ? '' : 'col-span-2' }} h-14 text-base" />
            @endif

            @if ($this->canPay)
                <x-button :label="__('Club round')" icon="o-gift" wire:click="openClubSheet"
                    class="btn-warning tap-comfort h-14 text-base" />
            @endif
        </div>
    </x-app-modal>

    {{-- ─── Sur quelle ardoise ? ─── --}}
    <x-app-modal wire:model="tabSheet" :title="__('On which tab?')" :open="$tabSheet"
        class="modal-bottom sm:modal-middle">
        <div class="space-y-2">
            @foreach ($this->openTabs as $tab)
                <button type="button" wire:key="pick-{{ $tab->id }}" wire:click="putOnTab(@js($tab->name))"
                    class="border-base-300 bg-base-100 tap-comfort flex min-h-[3.25rem] w-full items-center gap-3 rounded-xl border px-3 text-start">
                    <span class="min-w-0 flex-1 truncate font-semibold">{{ $tab->name }}</span>
                    <span class="shrink-0 font-bold tabular-nums">{{ euros($tab->total_price) }}</span>
                </button>
            @endforeach
        </div>

        <form wire:submit="putOnTab(null)" class="mt-4">
            <label for="bar-new-tab" class="text-muted text-xs font-bold uppercase tracking-widest">{{ __('New tab') }}</label>
            <div class="mt-1 flex gap-2">
                <input id="bar-new-tab" type="text" wire:model.live.debounce.150ms="tabNameInput" maxlength="64"
                    class="input input-bordered tap-comfort min-w-0 flex-1" autocomplete="off"
                    placeholder="{{ __('Name (team, first name, table…)') }}">
                <x-button :label="__('Create')" type="submit" spinner="putOnTab" class="btn-primary tap-comfort" />
            </div>
        </form>

        @if ($this->tabSuggestions->isNotEmpty())
            <div class="mt-2 flex flex-wrap gap-1.5">
                @foreach ($this->tabSuggestions as $suggestion)
                    <button type="button" wire:key="sugg-{{ $loop->index }}" wire:click="putOnTab(@js($suggestion['name']))"
                        @class([
                            'btn btn-sm tap-comfort rounded-full',
                            'btn-outline btn-primary' => $suggestion['total'] !== null,
                            'btn-ghost bg-base-200' => $suggestion['total'] === null,
                        ])>
                        @if ($suggestion['total'] !== null)
                            {{ __('Add to :name · :amount', ['name' => $suggestion['name'], 'amount' => euros($suggestion['total'])]) }}
                        @else
                            {{ $suggestion['name'] }}
                        @endif
                    </button>
                @endforeach
            </div>
        @endif

        <p class="text-subtle mt-3 text-xs">{{ __('If this name is already open, the order is added to that tab.') }}</p>
    </x-app-modal>

    {{-- ─── Verre du club ─── --}}
    <x-app-modal wire:model="clubSheet" :title="__('Club round for…')" :open="$clubSheet"
        class="modal-bottom sm:modal-middle">
        <div class="space-y-2" x-data="{ other: false }">
            @forelse ($this->clubTeams as $team)
                @php
                    $tooMany = $this->cartCount > $team['cap'];
                @endphp
                <button type="button" wire:key="team-{{ $loop->index }}"
                    wire:click="giveClubRound(@js($team['name']), false)"
                    @disabled($team['served_at'] !== '' || $tooMany)
                    class="border-warning bg-warning/10 tap-comfort flex min-h-[3.25rem] w-full flex-col items-start justify-center rounded-xl border px-3 py-1.5 text-start disabled:opacity-50">
                    <span class="font-semibold">{{ $team['name'] }}</span>
                    <span class="text-muted text-xs">
                        @if ($team['served_at'] !== '')
                            {{ __('Already served at :time', ['time' => $team['served_at']]) }}
                        @elseif ($tooMany)
                            {{ __('Max :cap: remove :count', ['cap' => $team['cap'], 'count' => $this->cartCount - $team['cap']]) }}
                        @else
                            {{ $team['category'] !== '' ? $team['category'] . ' · ' : '' }}{{ __('max :cap', ['cap' => $team['cap']]) }}
                        @endif
                    </span>
                </button>
            @empty
                <p class="text-muted text-sm">{{ __('No visiting team is expected today.') }}</p>
            @endforelse

            <button type="button" x-show="! other" x-on:click="other = true; $nextTick(() => $refs.other.focus())"
                class="text-primary tap-min py-2 text-sm font-semibold">
                {{ __('Other beneficiary…') }}
            </button>

            <form x-show="other" x-cloak wire:submit="giveOtherClubRound" class="space-y-1">
                <label for="bar-other-beneficiary" class="sr-only">{{ __('Beneficiary') }}</label>
                <div class="flex gap-2">
                    <input id="bar-other-beneficiary" x-ref="other" type="text" wire:model="otherBeneficiary" maxlength="255"
                        class="input input-bordered tap-comfort min-w-0 flex-1" placeholder="{{ __('Beneficiary') }}">
                    <x-button label="OK" type="submit" spinner="giveOtherClubRound" class="btn-warning tap-comfort" />
                </div>
                <p class="text-subtle text-xs">{{ __('Four articles at most; the name stays on the order.') }}</p>
            </form>
        </div>
    </x-app-modal>

    {{-- ─── Les ardoises ouvertes ─── --}}
    <x-app-modal wire:model="tabsSheet" :title="__('Open tabs')" :open="$tabsSheet"
        class="modal-bottom sm:modal-middle">
        <div class="space-y-2">
            @forelse ($this->openTabs as $tab)
                <button type="button" wire:key="tab-{{ $tab->id }}" wire:click="showTab({{ $tab->id }})"
                    class="border-base-300 bg-base-100 tap-comfort flex min-h-[3.25rem] w-full items-center gap-3 rounded-xl border px-3 text-start">
                    <span class="min-w-0 flex-1 truncate font-semibold">{{ $tab->name }}</span>
                    <span class="shrink-0 font-bold tabular-nums">{{ euros($tab->total_price) }}</span>
                </button>
            @empty
                <p class="text-muted text-sm">{{ __('No tab is open.') }}</p>
            @endforelse
        </div>
    </x-app-modal>

    {{-- ─── La fiche d'une ardoise : ajouter ou régler ─── --}}
    <x-app-modal wire:model="tabCardSheet" :title="$this->tabCard?->name ?? __('Tab')" :open="$tabCardSheet"
        class="modal-bottom sm:modal-middle">
        @if ($this->tabCard)
            <div class="divide-base-300 divide-y">
                @foreach ($this->tabCard->items as $item)
                    <div class="flex items-center gap-2 py-1.5 text-sm">
                        <span class="w-7 shrink-0 font-bold tabular-nums">{{ $item->quantity }}×</span>
                        <span class="min-w-0 flex-1 truncate">{{ $item->product?->name }}</span>
                        <span class="shrink-0 tabular-nums">{{ euros($item->total_price) }}</span>
                    </div>
                @endforeach
            </div>

            <div class="border-base-content mt-2 flex items-baseline justify-between border-t-2 pt-3">
                <span class="text-muted text-xs font-bold uppercase tracking-widest">{{ __('To settle') }}</span>
                <span class="text-3xl font-black tabular-nums tracking-tight">{{ euros($this->tabCard->total_price) }}</span>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-2">
                <x-button :label="__('Add')" icon="o-plus" wire:click="addToTab({{ $this->tabCard->id }})"
                    class="btn-outline tap-comfort col-span-2 h-14 text-base" />
                @if ($this->canPay)
                    <x-button :label="__('Pay cash')" icon="o-banknotes" wire:click="settleTabCash({{ $this->tabCard->id }})"
                        class="btn-primary tap-comfort h-14 text-base" />
                    <x-button :label="__('QR code')" icon="o-qr-code" wire:click="settleTabQr({{ $this->tabCard->id }})"
                        class="btn-outline tap-comfort h-14 text-base" />
                @endif
            </div>
        @else
            <p class="text-muted text-sm">{{ __('This tab is no longer open.') }}</p>
        @endif
    </x-app-modal>
</div>
