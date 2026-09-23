<?php

declare(strict_types=1);

namespace Resources\views\Pages\Bar\Cart;

use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Services\BarCartService;
use App\Domains\Shared\Enums\Permission;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

/*
|--------------------------------------------------------------------------
| Bar — le ticket
|--------------------------------------------------------------------------
|
| Le panier est le même objet que le ticket : ce qu'on relit avant d'enregistrer
| une ardoise ou de l'encaisser.
|
| Ses « + » et « − » suivent la même règle que ceux du comptoir — mise à jour sur
| place, sans rechargement — non parce qu'on y sert, mais parce qu'un écran où le
| même geste coûte tantôt rien tantôt une page entière apprend à s'en méfier.
|
| Les actions dépendent du mode : « laisser ouvert » n'a de sens que sur une
| ardoise nommée. Un client de passage n'a rien à laisser derrière lui, et lui
| montrer « Enregistrer » afficherait un bouton dont le serveur refuserait
| l'action, faute de nom.
|
*/
new class extends Component
{
    use HasBreadcrumbs, Toast;

    public function add(int $productId, BarCartService $cartService): void
    {
        $this->authorizeOrders();

        $result = $cartService->addProductToSessionCart($productId);

        if ($result['status'] !== 'success') {
            $this->error($result['message']);
        }

        unset($this->items, $this->totalPrice, $this->cartCount);
    }

    #[Computed]
    public function cartCount(): int
    {
        return (int) $this->items->sum('quantity');
    }

    public function clear(BarCartService $cartService): void
    {
        $this->authorizeOrders();

        $cartService->clearSessionCart();

        $this->redirectRoute('bar.index', navigate: true);
    }

    /**
     * @return Collection<int, array{product: BarProduct, quantity: int, total_price: int}>
     */
    #[Computed]
    public function items(): Collection
    {
        $cart = collect(session()->get('cart', []))
            ->map(fn ($qty): int => (int) $qty)
            ->filter(fn (int $qty): bool => $qty > 0);

        if ($cart->isEmpty()) {
            return collect();
        }

        return BarProduct::query()
            ->withStock()
            ->whereIn('id', $cart->keys())
            ->orderBy('name')
            ->get()
            ->map(fn (BarProduct $product): array => [
                'product' => $product,
                'quantity' => (int) $cart[$product->id],
                'total_price' => (int) $cart[$product->id] * (int) $product->sale_price,
            ]);
    }

    public function remove(int $productId, BarCartService $cartService): void
    {
        $this->authorizeOrders();

        $cartService->removeProductFromSessionCart($productId);

        unset($this->items, $this->totalPrice, $this->cartCount);
    }

    public function render(): View
    {
        return $this->view();
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
        return (int) $this->items->sum('total_price');
    }

    /**
     * Clore le panier : l'enregistrer sur l'ardoise, ou l'encaisser tout de suite.
     */
    public function validateOrder(string $action, BarCartService $cartService): void
    {
        $this->authorizeOrders();

        try {
            $order = $cartService->checkoutFromSessionCart($action);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return;
        } catch (\Throwable $e) {
            report($e);
            $this->error('Une erreur est survenue lors de la validation de la commande.');

            return;
        }

        if ($action === 'pay_now') {
            $this->redirectRoute('bar.payment.show', ['order' => $order->id], navigate: true);

            return;
        }

        // Retour au comptoir, et non sur la file : la boucle du bar est « servir →
        // encaisser → servir le suivant ». L'ardoise étant close en session, le
        // comptoir redemande pour qui on sert.
        session()->flash('success', sprintf(
            'Ardoise « %s » enregistrée — %s à encaisser.',
            (string) $order->name,
            euros((int) $order->total_price)
        ));

        $this->redirectRoute('bar.index', navigate: true);
    }

    protected function authorizeOrders(): void
    {
        abort_unless(auth()->user()?->can(Permission::BarOrdersManage->value), 403);
    }

    public function with(): array
    {
        return ['breadcrumbs' => $this->getBreadcrumbs()];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()->home()->bar()->current($this->tabName ?? __('Walk-in'));
    }
};
