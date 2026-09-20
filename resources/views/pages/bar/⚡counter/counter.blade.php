<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    @if ($isChoosing)
        {{--
            Pour qui sert-on ? La question vient avant le catalogue.

            Nommer à la validation laissait servir à l'aveugle : le comptoir ne disait
            jamais sur quelle ardoise on travaillait, et deux barmen pouvaient en ouvrir
            deux du même nom sans le savoir. Posée ici, elle rend aussi « nouvelle
            commande » et « modifier une commande » identiques — dans les deux cas on
            choisit une ardoise.
        --}}
        <div class="max-w-2xl space-y-4">
            <x-header progress-indicator separator :title="__('New order')"
                :subtitle="__('Who is this round for?')" />

            <x-card>
                <x-form wire:submit="openTab" no-separator>
                    <div class="flex flex-wrap items-end gap-2.5">
                        <div class="min-w-[12rem] flex-1">
                            <x-input :label="__('Tab name')" wire:model="tabNameInput"
                                maxlength="64" autofocus
                                :placeholder="__('e.g. Alpa A, Gilles, table 3')" />
                        </div>

                        <x-button :label="__('Start')" icon="o-play" type="submit"
                            class="btn-primary tap-comfort" spinner="openTab" />
                    </div>
                </x-form>

                <p class="text-subtle mt-3 flex items-start gap-1.5 text-xs">
                    <x-icon name="o-information-circle" class="mt-0.5 h-4 w-4 shrink-0" />
                    {{ __('A name already open is joined, not duplicated — so a round can be added to from any device.') }}
                </p>
            </x-card>

            {{-- Le nom qu'on cherche se lit plus vite qu'il ne se tape, et la liste dit
            aussi ce qu'on doit à chacun. --}}
            @if ($this->openTabs->isNotEmpty())
                <x-card>
                    <h2 class="text-muted mb-3 text-xs font-bold uppercase tracking-widest">
                        {{ __('Open tabs') }}
                        <span class="text-subtle tabular-nums">· {{ $this->openTabs->count() }}</span>
                    </h2>

                    <div class="divide-base-300 divide-y">
                        @foreach ($this->openTabs as $tab)
                            <button type="button" wire:key="tab-{{ $tab->id }}"
                                wire:click="$set('tabNameInput', @js($tab->name)); $wire.openTab()"
                                class="tap-comfort hover:bg-base-200 -mx-2 flex w-full items-center gap-3 rounded-lg px-2 py-2 text-start">
                                <span class="min-w-0 flex-1 truncate font-semibold">{{ $tab->name }}</span>
                                <span class="shrink-0 font-bold tabular-nums">{{ euros($tab->total_price) }}</span>
                                <span class="text-subtle hidden shrink-0 text-xs tabular-nums sm:inline">
                                    {{ $tab->created_at->format('H:i') }}
                                </span>
                            </button>
                        @endforeach
                    </div>
                </x-card>
            @endif

            {{--
                Le client de passage n'ouvre rien : il commande, il paie, il part. Lui
                demander un nom mettrait une saisie clavier sur le geste le plus
                fréquent du bar, pour une information que personne ne relirait.
            --}}
            <x-button :label="__('Walk-in — pay right away')" icon="o-bolt"
                wire:click="openWalkIn" class="btn-outline tap-comfort w-full" spinner="openWalkIn" />
        </div>
    @else
        <div class="max-w-5xl space-y-4 pb-24">
            {{-- L'en-tête dit sur quelle ardoise on sert : c'était le défaut le plus
            sournois de l'ancien écran, où cinq éléments affirmaient « nouvelle
            commande » alors qu'on réécrivait la commande d'un client. --}}
            <x-header progress-indicator separator
                :title="$this->tabName ?? __('Walk-in')"
                :subtitle="$this->tabName
                    ? __('Everything you add lands on this tab.')
                    : __('To be paid right away — nothing stays open.')">
                <x-slot:actions>
                    <x-button :label="__('Change')" icon="o-arrow-uturn-left"
                        wire:click="leaveTab" class="btn-ghost btn-sm tap-comfort" />
                </x-slot:actions>
            </x-header>

            <x-input wire:model.live.debounce.200ms="search" :placeholder="__('Search products')"
                icon="o-magnifying-glass" clearable />

            @foreach ($this->catalogue as $category)
                <x-bar.category-panel :key="'cat-' . $category->id" :label="$category->name"
                    icon="o-cube" icon-class="text-primary" :count="$category->products->count()">
                    @forelse ($category->products as $product)
                        <x-bar.product-row :key="'prod-' . $product->id"
                            :product="$product" :state="$this->stateOf($product)" />
                    @empty
                        <p class="text-muted px-4 py-4 text-sm">{{ __('No products in this category.') }}</p>
                    @endforelse
                </x-bar.category-panel>
            @endforeach
        </div>

        {{--
            La pilule du panier.

            Servir, c'est faire défiler un catalogue en ajoutant au fil de l'eau : une
            barre de validation posée en haut de page remonte hors de vue dès le
            deuxième produit. La pilule reste sous le pouce. Même motif que
            <x-admin.shared.selection-pill> — `pointer-events-none` sur le conteneur
            pleine largeur, réactivé sur la pilule — pour que le reste de la page reste
            cliquable au travers. Le `pb-24` ci-dessus lui réserve sa place.
        --}}
        @if ($this->cartCount > 0)
            <div class="pointer-events-none fixed inset-x-0 bottom-6 z-50 flex justify-center px-4"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100">
                <div class="border-base-300 bg-base-100 pointer-events-auto flex max-w-2xl flex-wrap items-center gap-3 rounded-2xl border px-4 py-2.5 shadow-2xl">
                    <p class="text-sm">
                        @if ($this->tabName)
                            <span class="font-semibold">{{ $this->tabName }}</span>
                            <span class="text-subtle">·</span>
                        @endif
                        <span class="font-bold tabular-nums">{{ $this->cartCount }}</span>
                        article{{ $this->cartCount > 1 ? 's' : '' }}
                        <span class="text-subtle">·</span>
                        <span class="font-bold tabular-nums">{{ euros($this->totalPrice) }}</span>
                    </p>

                    <a href="{{ route('bar.cart.show') }}" wire:navigate
                        class="btn btn-primary btn-sm tap-comfort gap-2">
                        <x-icon name="o-shopping-cart" class="h-4 w-4" />
                        {{ $this->tabName ? __('Review the tab') : __('Review the order') }}
                    </a>
                </div>
            </div>
        @endif
    @endif
</div>
