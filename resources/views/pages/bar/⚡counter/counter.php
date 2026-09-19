<?php

declare(strict_types=1);

namespace Resources\views\Pages\Bar\Counter;

use App\Domains\Bar\Models\BarCategory;
use App\Domains\Bar\Models\BarOrder;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Services\BarCartService;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use App\Support\LocaleSort;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

/*
|--------------------------------------------------------------------------
| Bar — le comptoir
|--------------------------------------------------------------------------
|
| Chaque « + » et chaque « − » était un POST suivi d'un rechargement complet de
| page. Mesuré sur la base de développement : 20 requêtes SQL pour 9 produits,
| parce que l'accesseur `stock` lançait deux SUM non mémorisés par lecture — donc
| une centaine sur un catalogue réel, PAR TAP. S'y ajoutaient la position de
| défilement perdue à chaque article et un toast de confirmation par ajout.
|
| C'est le seul écran de l'application où la latence fait partie de la tâche : on
| y est debout, une main occupée, avec quelqu'un en face. Un point de vente qui
| ralentit le service se fait contourner — on note sur un papier, et le stock
| devient faux, ce qui casse à son tour les pastilles, les favoris et la feuille
| de caisse.
|
| Deux états, un seul composant : tant qu'aucune ardoise n'est choisie, l'écran
| demande pour qui on sert ; ensuite il montre le catalogue. Les deux sont la même
| page parce que c'est la même question — « sur quoi je travaille » — et parce
| qu'une route qui change de vue selon la session se lit mal.
|
| Le retour d'information est l'état, pas un message : le compteur qui passe de 2
| à 3 EST la confirmation. Les toasts sont réservés à ce qui empêche l'action —
| rupture, plafond de stock, produit retiré de la carte.
|
*/
new class extends Component
{
    use HasBreadcrumbs, Toast;

    public string $search = '';

    public string $tabNameInput = '';

    public function add(int $productId, BarCartService $cartService): void
    {
        $result = $cartService->addProductToSessionCart($productId);

        // Seul l'échec parle. Trente toasts « ajouté au panier » dans une tournée
        // finiraient par masquer le seul qui compte.
        if ($result['status'] !== 'success') {
            $this->error($result['message']);
        }

        unset($this->cart, $this->catalogue);
    }

    #[Computed]
    public function cart(): array
    {
        return collect(session()->get('cart', []))
            ->map(fn ($qty): int => (int) $qty)
            ->filter(fn (int $qty): bool => $qty > 0)
            ->all();
    }

    #[Computed]
    public function cartCount(): int
    {
        return array_sum($this->cart);
    }

    /**
     * @return Collection<int, BarCategory>
     */
    #[Computed]
    public function catalogue(): Collection
    {
        $search = trim($this->search);

        return BarCategory::query()
            ->when($search !== '', fn ($query) => $query->whereHas(
                'products',
                fn ($productQuery) => $productQuery
                    ->where('name', 'like', "%{$search}%")
            ))
            ->with(['products' => fn ($q) => $q
                ->withStock()
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->orderBy('name')])
            ->orderBy('name')
            ->get();
    }

    public function leaveTab(BarCartService $cartService): void
    {
        $cartService->clearSessionCart();

        unset($this->cart, $this->openTabs);
    }

    public function openTab(BarCartService $cartService): void
    {
        $this->validate(['tabNameInput' => ['required', 'string', 'max:64']]);

        $result = $cartService->openTab($this->tabNameInput);

        if ($result['status'] !== 'success') {
            $this->error($result['message']);

            return;
        }

        // Rejoindre une ardoise mérite un mot : le panier se remplit de ce qu'elle
        // portait déjà, et c'est surprenant si rien ne l'annonce.
        if ($result['joined']) {
            $this->success($result['message']);
        }

        $this->tabNameInput = '';
        unset($this->cart, $this->openTabs);
    }

    /**
     * @return Collection<int, BarOrder>
     */
    #[Computed]
    public function openTabs(): Collection
    {
        return LocaleSort::by(
            BarOrder::query()->where('is_paid', 0)->whereNotNull('name')->get(),
            fn (BarOrder $order): string => (string) $order->name
        );
    }

    public function openWalkIn(BarCartService $cartService): void
    {
        $cartService->openWalkIn();

        unset($this->cart, $this->openTabs);
    }

    public function remove(int $productId, BarCartService $cartService): void
    {
        $cartService->removeProductFromSessionCart($productId);

        unset($this->cart, $this->catalogue);
    }

    public function updatedSearch(): void
    {
        unset($this->catalogue);
    }

    public function render(): View
    {
        return $this->view();
    }

    /**
     * L'état d'un produit dans la grille.
     *
     * Le libellé de stock porte quatre sens (indisponible, rupture, plafond du panier
     * atteint, stock bas) et chacun change la couleur de la pastille et l'activation
     * du « + ». Les résoudre en un seul endroit évite que les favoris et les
     * catégories divergent, comme c'était le cas — les favoris n'offraient pas de
     * retrait.
     *
     * @return object{qty:int, realStock:int, theoreticalStock:int, isUnavailable:bool,
     *                isStockLimit:bool, disablePlus:bool, badgeClass:string, badgeLabel:string}
     */
    public function stateOf(BarProduct $product): object
    {
        $qty = $this->cart[$product->id] ?? 0;
        $realStock = $product->stock;
        $isUnavailable = ! $product->is_available;
        $isStockLimit = $qty >= $realStock;

        $badgeClass = 'badge-success badge-soft';
        $badgeLabel = $realStock . ' en stock';

        if ($isUnavailable) {
            $badgeClass = 'badge-ghost';
            $badgeLabel = 'Indisponible';
        } elseif ($realStock === 0) {
            $badgeClass = 'badge-error badge-soft';
            $badgeLabel = 'Rupture de stock';
        } elseif ($isStockLimit) {
            $badgeClass = 'badge-warning badge-soft';
            $badgeLabel = 'Stock maximum atteint';
        } elseif ($realStock <= $product->effective_low_stock_threshold) {
            $badgeClass = 'badge-warning badge-soft';
            $badgeLabel = 'Plus que ' . $realStock;
        }

        return (object) [
            'qty' => $qty,
            'realStock' => $realStock,
            'theoreticalStock' => max(0, $realStock - $qty),
            'isUnavailable' => $isUnavailable,
            'isStockLimit' => $isStockLimit,
            'disablePlus' => $isUnavailable || $isStockLimit,
            'badgeClass' => $badgeClass,
            'badgeLabel' => $badgeLabel,
        ];
    }

    #[Computed]
    public function tabName(): ?string
    {
        $name = session()->get('bar_tab_name');

        return is_string($name) && $name !== '' ? $name : null;
    }

    #[Computed]
    public function totalPrice(): int
    {
        $cart = $this->cart;

        if ($cart === []) {
            return 0;
        }

        return (int) BarProduct::query()
            ->whereIn('id', array_keys($cart))
            ->get()
            ->sum(fn (BarProduct $p): int => (int) $p->sale_price * (int) ($cart[$p->id] ?? 0));
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'isChoosing' => $this->tabName === null && ! session()->get('bar_walk_in', false),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()->home()->bar()->current($this->tabName ?? __('New order'));
    }
};
