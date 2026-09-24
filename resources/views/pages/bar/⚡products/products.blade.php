<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="__('Products')"
        :subtitle="__('Count the shelf, set the price, tune the alert threshold.')">
        <x-slot:actions>
            <x-button class="btn-primary btn-sm" icon="o-plus" :label="__('Add')" wire:click="openCreate" />
        </x-slot:actions>
    </x-header>

    <x-input wire:model.live.debounce.200ms="search" :placeholder="__('Search products')"
        icon="o-magnifying-glass" clearable />

    {{--
        L'ordre d'affichage est une navigation, pas un filtre (R2) : il change ce
        que la page montre, il vaut exactement une valeur, et `clearFilters()` ne
        saurait pas le vider. Il reste donc visible au-dessus du contenu, et son
        libellé titre ce qui suit.
    --}}
    <div class="mb-4 flex flex-wrap items-center gap-x-3 gap-y-2">
        <span class="text-muted text-xs font-bold uppercase tracking-widest">{{ __('Order') }}</span>

        <div class="join">
            <button type="button" wire:click="$set('order', 'category')"
                @class([
                    'join-item btn btn-sm tap-min',
                    'btn-primary' => $order === 'category',
                    'btn-outline' => $order !== 'category',
                ])>
                <x-icon name="o-squares-2x2" class="h-4 w-4" />
                {{ __('By shelf') }}
            </button>

            <button type="button" wire:click="$set('order', 'criticality')"
                @class([
                    'join-item btn btn-sm tap-min',
                    'btn-primary' => $order === 'criticality',
                    'btn-outline' => $order !== 'criticality',
                ])>
                <x-icon name="o-exclamation-triangle" class="h-4 w-4" />
                {{ __('By urgency') }}
            </button>
        </div>

        @if ($lowStockCount > 0)
            <span class="badge badge-warning badge-soft badge-sm font-bold tabular-nums">
                {{ __(':count to restock', ['count' => $lowStockCount]) }}
            </span>
        @endif
    </div>

    @if ($groups->isEmpty())
        <x-card>
            @if (trim($search) !== '')
                <x-empty-state
                    icon="o-magnifying-glass"
                    :heading="__('No results match your search criteria.')" />
            @else
                {{-- Le slot, et non `buttonText` + `href` : <x-empty-state> rend le bouton
            comme un lien et laisse tomber le reste du sac d'attributs, donc un
            `wire:click` posé à côté de `href` ne se déclencherait jamais. --}}
                <x-empty-state
                    icon="o-cube"
                    :heading="__('No product yet')"
                    :message="__('Add the first product to build the bar menu.')">
                    <x-button :label="__('Add a product')" icon="o-plus"
                        class="btn-primary btn-sm" wire:click="openCreate" />
                </x-empty-state>
            @endif
        </x-card>
    @else
        <div class="space-y-4">
            @foreach ($groups as $group)
                <div wire:key="group-{{ $group['label'] ?? 'all' }}">
                    @if ($group['label'])
                        <h2 class="text-muted mb-2 text-xs font-bold uppercase tracking-widest">
                            {{ $group['label'] }}
                            <span class="text-subtle tabular-nums">· {{ $group['products']->count() }}</span>
                        </h2>
                    @endif

                    {{--
                        `!p-2` sous `lg` : le rembourrage par défaut de <x-card> est
                        `p-5`, et avec le `p-5` de la zone de contenu ce sont 80 px des
                        390 d'un téléphone — 21 % de l'écran — dépensés avant le premier
                        chiffre. Un tableau d'inventaire a besoin de sa largeur ; une
                        carte de lecture peut se permettre ses marges.
                    --}}
                    <x-card class="!p-2 lg:!p-5">
                        {{--
                            Le même tableau à toutes les largeurs, contre la règle du
                            jumeau en cartes : une carte par produit est exactement la
                            densité que cet écran existe pour supprimer. Les colonnes
                            qui ne servent pas debout — prix, seuil — se replient sous
                            `lg` et restent atteignables par le tiroir. Voir DESIGN.md.
                        --}}
                        <x-table :headers="$headers" :rows="$group['products']" class="table-sm">
                            @scope('cell_name', $product)
                                {{-- Le nom est la porte du tiroir : c'est la cible la plus
                                large de la ligne, et elle n'a besoin d'aucune icône pour
                                s'annoncer puisqu'elle porte déjà le nom du produit. --}}
                                <button type="button"
                                    wire:click="openProduct({{ $product->id }})"
                                    class="tap-comfort -mx-2 flex w-full min-w-0 justify-start gap-2 rounded-lg px-2 text-start font-medium hover:underline">
                                    <span class="truncate">{{ $product->name }}</span>
                                    @unless ($product->is_available)
                                        <x-icon name="o-eye-slash" class="text-base-content/50 h-4 w-4 shrink-0"
                                            :title="__('Off menu')" />
                                    @endunless
                                </button>
                            @endscope

                            @scope('cell_price', $product)
                                <span class="tabular-nums">{{ euros($product->sale_price) }}</span>
                            @endscope

                            @scope('cell_stock', $product)
                                <div class="flex items-center justify-end gap-1.5">
                                    @if ($product->is_low_stock)
                                        <x-icon name="o-exclamation-triangle"
                                            class="text-warning h-4 w-4 shrink-0"
                                            :title="__('At or below its alert threshold')" />
                                    @endif

                                    {{-- `wire:change` et non `.live` : taper « 48 » par-dessus
                                    « 4 » ne doit écrire qu'un seul mouvement de stock. --}}
                                    <input type="number" min="0" inputmode="numeric"
                                        wire:key="stock-{{ $product->id }}"
                                        value="{{ $product->stock }}"
                                        wire:change="updateStock({{ $product->id }}, $event.target.value)"
                                        aria-label="{{ __('Counted stock for :product', ['product' => $product->name]) }}"
                                        class="input input-bordered input-sm tap-comfort w-14 text-end tabular-nums lg:w-20">
                                </div>
                            @endscope

                            @scope('cell_threshold', $product)
                                <input type="number" min="0" inputmode="numeric"
                                    wire:key="threshold-{{ $product->id }}"
                                    value="{{ $product->low_stock_threshold }}"
                                    placeholder="{{ $product->effective_low_stock_threshold }}"
                                    wire:change="updateThreshold({{ $product->id }}, $event.target.value)"
                                    aria-label="{{ __('Alert threshold for :product', ['product' => $product->name]) }}"
                                    class="input input-bordered input-sm tap-comfort w-14 text-end tabular-nums lg:w-20">
                            @endscope

                            @scope('cell_available', $product)
                                {{--
                                    La bascule est enveloppée d'un label `tap-comfort`
                                    plutôt qu'agrandie : `toggle-sm` mesure 33×20 px, sous
                                    le plancher de 44 px que le projet tient partout
                                    ailleurs, et grossir le dessin gonflerait la colonne.
                                    `tap-comfort` grandit la zone de contact et laisse le
                                    dessin tranquille — c'est ce pour quoi l'utilitaire
                                    existe.
                                --}}
                                <div class="flex justify-end">
                                    <label class="tap-comfort cursor-pointer">
                                        <input type="checkbox" class="toggle toggle-primary"
                                            wire:key="available-{{ $product->id }}"
                                            @checked((int) $product->is_available === 1)
                                            wire:change="updateAvailability({{ $product->id }}, $event.target.checked)"
                                            aria-label="{{ __('Available: :product', ['product' => $product->name]) }}">
                                    </label>
                                </div>
                            @endscope
                        </x-table>
                    </x-card>
                </div>
            @endforeach
        </div>
    @endif

    {{--
        Prix, nom, catégorie et suppression vivent ici, derrière un bouton
        Enregistrer, et non dans la grille : un prix mal frappé s'applique à toutes
        les ventes suivantes et ne se voit nulle part. Le stock, lui, se re-compte.
    --}}
    <x-drawer wire:model="drawer" right with-close-button class="w-full max-w-sm"
        :title="$editingId ? __('Edit product') : __('New product')">
        <x-form wire:submit="save">
            <x-input :label="__('Name')" wire:model="name" required />

            <x-select :label="__('Category')" :options="$categories" wire:model="categoryId" required />

            <x-input :label="__('Price')" wire:model="price" suffix="€" inputmode="decimal"
                :hint="__('Comma or dot, two decimals at most.')" required />

            <x-slot:actions>
                <x-button :label="__('Cancel')" wire:click="$set('drawer', false)" type="button" />
                <x-button :label="__('Save')" type="submit" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-form>

        @if ($editingId)
            <div class="border-base-300 mt-6 border-t pt-4">
                {{--
                    Pas de bouton grisé avec une infobulle : la raison est écrite en
                    clair, parce qu'une infobulle n'existe pas sous un pouce. Le bouton
                    reste actif et c'est le serveur qui refuse, avec son message.
                --}}
                <x-button :label="__('Delete this product')" icon="o-trash"
                    class="btn-outline btn-error btn-sm w-full"
                    wire:click="$set('deleteModal', true)" />

                <p class="text-subtle mt-2 flex items-start gap-1.5 text-xs">
                    <x-icon name="o-information-circle" class="mt-0.5 h-4 w-4 shrink-0" />
                    {{ __('Only a product at zero stock can be deleted. To take one off the menu, switch Available off.') }}
                </p>
            </div>
        @endif
    </x-drawer>

    <x-confirm-modal model="deleteModal" :title="__('Delete this product permanently?')"
        :confirmLabel="__('Delete this product')" confirmAction="delete" :open="$deleteModal">
        <p>{{ __('Only a product at zero stock can be deleted. To take one off the menu, switch Available off.') }}</p>
    </x-confirm-modal>

</div>
