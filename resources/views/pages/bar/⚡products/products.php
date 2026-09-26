<?php

declare(strict_types=1);

namespace Resources\views\Pages\Bar\Products;

use App\Domains\Bar\Models\BarCategory;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Services\StockService;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use App\Support\LocaleSort;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Mary\Traits\Toast;

/*
|--------------------------------------------------------------------------
| Bar — produits et stock
|--------------------------------------------------------------------------
|
| L'écran remplaçait une tuile par produit : deux champs, un toggle et deux
| boutons, soit ~180 px de haut chacun. Faire l'inventaire d'une quarantaine de
| références demandait autant de défilement qu'il y a d'étagères, et comparer
| deux nombres voisins était impossible — ils n'étaient jamais à l'écran
| ensemble.
|
| C'est donc une grille de saisie, pas une liste : les lignes ne portent aucune
| action nommée, seulement des contrôles compacts. Deux conséquences assumées :
|
| - le tableau reste un tableau sous `lg` au lieu de devenir des cartes. La règle
|   du jumeau mobile existe parce qu'une ligne porteuse d'actions nommées pousse
|   celles-ci hors de l'écran ; ici la raison ne s'applique pas, et des cartes
|   rendraient précisément la densité qu'on est venu chercher. Voir docs/DESIGN.md.
| - la saisie s'enregistre au `change` du champ, sans bouton. Motif de
|   `subscriptions/⚡roster`. Au `change` et non à la frappe : taper « 48 » par
|   dessus « 4 » ne doit écrire qu'un mouvement de stock, pas deux.
|
| Ce qui coûte cher reste derrière le nom, dans un tiroir avec un bouton
| Enregistrer : le prix, le nom, la catégorie, la suppression. Un prix mal frappé
| se propage à toutes les ventes de la soirée et ne se voit nulle part.
|
*/
new class extends Component
{
    use HasBreadcrumbs, Toast;

    public ?int $categoryId = null;

    public bool $deleteModal = false;

    public bool $drawer = false;

    /** Produit ouvert dans le tiroir ; null = création. */
    public ?int $editingId = null;

    public string $name = '';

    /**
     * Ordre d'affichage — une navigation, pas un filtre (R2).
     *
     * `category` suit les rayons : c'est l'ordre d'un inventaire, où l'on descend
     * l'étagère de gauche à droite. `criticality` remonte les stocks les plus bas :
     * c'est l'ordre d'une liste de courses. Les deux tâches sont réelles et n'ont
     * pas le même ordre, donc l'écran porte les deux.
     */
    #[Url]
    public string $order = 'category';

    public string $packLabel = '';

    public string $packSize = '1';

    public string $price = '';

    public string $search = '';

    public function delete(): void
    {
        $this->deleteModal = false;

        $product = BarProduct::withStock()->findOrFail($this->editingId);

        // Le stock doit être à zéro : supprimer un produit encore en rayon
        // laisserait des mouvements orphelins et une caisse qui ne tombe pas juste.
        if ($product->stock > 0) {
            $this->error(__('A product still in stock cannot be deleted. Set it unavailable instead.'));

            return;
        }

        $product->delete();

        $this->drawer = false;
        $this->success(__('Product deleted.'));
    }

    public function openCreate(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->price = '';
        $this->packSize = '1';
        $this->packLabel = '';
        $this->categoryId = BarCategory::query()->orderBy('name')->value('id');
        $this->resetValidation();
        $this->drawer = true;
    }

    public function openProduct(int $productId): void
    {
        $product = BarProduct::findOrFail($productId);

        $this->editingId = $product->id;
        $this->name = $product->name;
        $this->price = number_format($product->sale_price / 100, 2, ',', '');
        $this->categoryId = $product->category_id;
        $this->packSize = (string) $product->pack_size;
        $this->packLabel = (string) $product->pack_label;
        $this->resetValidation();
        $this->drawer = true;
    }

    public function render(): View
    {
        return $this->view();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => [
                'required', 'string', 'max:150',
                'unique:bar_products,name' . ($this->editingId !== null ? ',' . $this->editingId : ''),
            ],
            'price' => ['required', 'string', 'regex:/^\d+(?:[\.,]\d{1,2})?$/'],
            'categoryId' => ['required', 'exists:bar_categories,id'],
            'packSize' => ['required', 'integer', 'min:1', 'max:1000'],
            'packLabel' => ['nullable', 'string', 'max:30'],
        ]);

        $payload = [
            'name' => $validated['name'],
            'sale_price' => cents($validated['price']),
            'category_id' => (int) $validated['categoryId'],
            'pack_size' => (int) $validated['packSize'],
            'pack_label' => trim((string) $validated['packLabel']) ?: null,
        ];

        if ($this->editingId === null) {
            $payload['is_available'] = 1;
            BarProduct::query()->create($payload);
            $this->success(__('Product created.'));
        } else {
            BarProduct::query()->findOrFail($this->editingId)->update($payload);
            $this->success(__('Product updated.'));
        }

        $this->drawer = false;
    }

    /**
     * Rendre un produit disponible ou non — le geste du service, pas de l'inventaire.
     *
     * C'est ce qu'on fait quand le fût est vide et qu'il faut le sortir de la carte
     * tout de suite. Il reste donc en ligne, à un tap.
     */
    public function updateAvailability(int $productId, bool $available): void
    {
        BarProduct::query()->findOrFail($productId)->update(['is_available' => $available ? 1 : 0]);

        $this->success($available ? __('Product available again.') : __('Product set unavailable.'));
    }

    /**
     * Jusqu'où remonter le stock quand on fait les courses.
     *
     * Vidé, le produit sort du réassort : au contraire du seuil, un max absent ne
     * retombe sur aucun défaut, il dit « on ne rachète pas ».
     */
    public function updateMaxStock(int $productId, ?string $maxStock): void
    {
        $value = ($maxStock === null || trim($maxStock) === '') ? null : max(0, (int) $maxStock);

        BarProduct::query()->findOrFail($productId)->update(['max_stock' => $value]);

        $this->success(__('Restocking target updated.'));
    }

    /**
     * Aligner le stock sur ce qui a été compté sur l'étagère.
     *
     * Le champ porte un comptage, pas un mouvement : l'écart devient une entrée ou
     * une sortie FIFO (StockService::adjustStockTo). Un champ vidé ne veut rien
     * dire — on ne compte pas « rien », on compte zéro — donc on l'ignore plutôt
     * que de le lire comme 0 et de vider le rayon par accident.
     */
    public function updateStock(int $productId, ?string $counted, StockService $stockService): void
    {
        if ($counted === null || trim($counted) === '') {
            return;
        }

        $countedStock = (int) $counted;

        if ($countedStock < 0) {
            $this->error(__('A counted stock cannot be negative.'));

            return;
        }

        $product = BarProduct::withStock()->findOrFail($productId);
        $currentStock = $product->stock;

        $written = DB::transaction(fn (): bool => $stockService->adjustStockTo(
            $productId,
            $countedStock,
            $currentStock,
            'Inventory count',
            auth()->id(),
        ));

        if ($written) {
            $this->success(__(':product: :from → :to', [
                'product' => $product->name,
                'from' => $currentStock,
                'to' => $countedStock,
            ]));
        }
    }

    /**
     * Le seuil sous lequel le produit s'annonce comme bas.
     *
     * Vidé, il retombe sur le défaut du bar — c'est pourquoi la colonne accepte le
     * vide, là où le stock ne l'accepte pas : « pas de seuil propre » est une
     * réponse, « pas de stock » n'en est pas une.
     */
    public function updateThreshold(int $productId, ?string $threshold): void
    {
        $value = ($threshold === null || trim($threshold) === '') ? null : max(0, (int) $threshold);

        BarProduct::query()->findOrFail($productId)->update(['low_stock_threshold' => $value]);

        $this->success(__('Alert threshold updated.'));
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'categories' => $this->categoriesForSelect(),
            'groups' => $this->groups(),
            'headers' => $this->headers(),
            'lowStockCount' => $this->products()->filter(fn (BarProduct $p): bool => $p->is_low_stock)->count(),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()->home()->bar()->current(__('Products'));
    }

    /**
     * @return Collection<int, array{id: int, name: string}>
     */
    protected function categoriesForSelect(): Collection
    {
        return LocaleSort::byKey(
            BarCategory::query()->get()->map(fn (BarCategory $c): array => ['id' => $c->id, 'name' => $c->name]),
            'name'
        );
    }

    /**
     * Les sections du tableau.
     *
     * En ordre de rayon, une section par catégorie, chacune titrée — c'est ce qui
     * permet de suivre l'étagère. En ordre de criticité, une seule section sans
     * titre : découper par catégorie y détruirait l'information qu'on cherche,
     * puisque le produit le plus critique peut être dans n'importe quelle.
     *
     * @return Collection<int, array{label: string|null, products: Collection<int, BarProduct>}>
     */
    protected function groups(): Collection
    {
        $products = $this->products();

        if ($this->order === 'criticality') {
            return collect([[
                'label' => null,
                'products' => $products
                    ->sortBy([
                        fn (BarProduct $a, BarProduct $b): int => ($a->stock - $a->effective_low_stock_threshold)
                            <=> ($b->stock - $b->effective_low_stock_threshold),
                        fn (BarProduct $a, BarProduct $b): int => $a->stock <=> $b->stock,
                    ])
                    ->values(),
            ]]);
        }

        return LocaleSort::by(
            $products->groupBy('category_id')->map(fn (Collection $rows): array => [
                'label' => $rows->first()->category->name,
                'products' => LocaleSort::by($rows, fn (BarProduct $p): string => $p->name),
            ])->values(),
            fn (array $group): string => (string) $group['label']
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function headers(): array
    {
        // Les libellés courts ne sont pas une coquetterie : dans un tableau, c'est
        // l'en-tête qui impose la largeur de sa colonne, pas son contenu. Mesuré sur
        // un écran de 390 px, « Disponible » réservait 106 px pour une bascule de
        // 40 px — à lui seul un tiers du défilement latéral. « Carte » dit la même
        // chose au barman : le produit est-il sur la carte ce soir.
        //
        // `w-1` demande à la colonne de se réduire à son contenu ; c'est la classe
        // que Mary emploie elle-même pour ses colonnes de contrôle.
        return [
            ['key' => 'name', 'label' => __('Product'), 'sortable' => false, 'class' => 'max-w-0 w-full'],
            ['key' => 'price', 'label' => __('Price'), 'sortable' => false, 'class' => 'hidden lg:table-cell text-end w-1'],
            ['key' => 'stock', 'label' => __('Stock'), 'sortable' => false, 'class' => 'text-end w-1'],
            // « Min » plutôt que « Seuil » : le même chiffre déclenche l'alerte au
            // comptoir et l'entrée dans la liste de courses, et il fait paire avec Max.
            ['key' => 'threshold', 'label' => __('Min'), 'sortable' => false, 'class' => 'hidden lg:table-cell text-end w-1'],
            ['key' => 'max', 'label' => __('Max'), 'sortable' => false, 'class' => 'hidden lg:table-cell text-end w-1'],
            ['key' => 'available', 'label' => __('On menu'), 'sortable' => false, 'class' => 'text-end w-1'],
        ];
    }

    /**
     * @return Collection<int, BarProduct>
     */
    protected function products(): Collection
    {
        $search = trim($this->search);

        return BarProduct::query()
            ->withStock()
            ->with('category')
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->get();
    }
};
