<?php

declare(strict_types=1);

namespace App\Domains\Bar\Services;

use App\Domains\Bar\Models\BarOrder;
use App\Domains\Bar\Models\BarOrderItem;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\Shared\Enums\Permission;
use Illuminate\Support\Facades\DB;

class BarCartService
{
    private const string ACTION_PAY_NOW = 'pay_now';

    private const string ACTION_VALIDATE = 'validate';

    public function __construct(private readonly StockService $stockService) {}

    public function addProductToSessionCart(int $productId): array
    {
        $product = BarProduct::query()->findOrFail($productId);

        if (! $product->is_available) {
            return [
                'status' => 'error',
                'message' => 'Ce produit n\'est plus disponible.',
            ];
        }

        $cart = $this->getSanitizedCart();
        $currentQty = (int) ($cart[$productId] ?? 0);

        if ($currentQty >= (int) $product->stock) {
            return [
                'status' => 'error',
                'message' => sprintf('Stock insuffisant pour %s.', $product->name),
            ];
        }

        $cart[$productId] = $currentQty + 1;
        session()->put('cart', $cart);

        return [
            'status' => 'success',
            'message' => sprintf('%s ajouté au panier.', $product->name),
        ];
    }

    public function checkoutFromSessionCart(string $action): BarOrder
    {
        if (! in_array($action, [self::ACTION_VALIDATE, self::ACTION_PAY_NOW], true)) {
            throw new \RuntimeException('Action de validation invalide.');
        }

        $cart = $this->getSanitizedCart();

        if ($cart === []) {
            throw new \RuntimeException('Le panier est vide.');
        }

        $userId = auth()->id();

        if (! is_int($userId)) {
            throw new \RuntimeException('Utilisateur non authentifié.');
        }

        $tabName = $this->currentTabName();

        // Une commande qu'on laisse ouverte doit pouvoir être retrouvée : c'est tout
        // l'objet du nom. Celle qu'on règle sur-le-champ n'entre jamais dans la file,
        // donc elle n'a rien à nommer.
        if ($action === self::ACTION_VALIDATE && $tabName === null) {
            throw new \RuntimeException('Donnez un nom à cette ardoise avant de la laisser ouverte.');
        }

        $order = DB::transaction(function () use ($cart, $userId, $tabName): BarOrder {
            $orderId = session()->get('editing_order_id');
            $order = $this->loadOrCreateDraftOrder($orderId, $userId, $tabName);

            $products = BarProduct::query()
                ->whereIn('id', array_keys($cart))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $totalPrice = 0;

            foreach ($cart as $productId => $qty) {
                $product = $products->get($productId);

                if (! $product instanceof BarProduct) {
                    throw new \RuntimeException(sprintf('Produit introuvable (ID %d).', $productId));
                }

                $this->validateProductStock($product, $qty);

                $unitPrice = (int) $product->sale_price;
                $lineTotal = $unitPrice * $qty;
                $totalPrice += $lineTotal;

                $orderItem = BarOrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'total_price' => $lineTotal,
                ]);

                $this->stockService->consumeFIFO(
                    (int) $product->id,
                    $qty,
                    sprintf('Order #%d', (int) $order->id),
                    $userId,
                    $userId,
                    (int) $order->id,
                    (int) $orderItem->id,
                );
            }

            $order->update([
                'total_price' => $totalPrice,
                'modified_by' => $userId,
            ]);

            return $order->fresh(['items.product']);
        });

        $this->clearSessionCart();

        return $order;
    }

    public function clearSessionCart(): void
    {
        session()->forget('cart');
        session()->forget('editing_order_id');
        session()->forget('bar_tab_name');
        session()->forget('bar_walk_in');
    }

    public function currentTabName(): ?string
    {
        $name = session()->get('bar_tab_name');

        return is_string($name) && $name !== '' ? $name : null;
    }

    public function getCartViewData(): array
    {
        $cart = $this->getSanitizedCart();

        $products = BarProduct::query()
            ->withStock()
            ->whereIn('id', array_keys($cart))
            ->orderBy('name')
            ->get();

        $items = $products->map(function (BarProduct $product) use ($cart): array {
            $qty = (int) ($cart[$product->id] ?? 0);

            return [
                'product' => $product,
                'quantity' => $qty,
                'total_price' => $qty * (int) $product->sale_price,
            ];
        });

        return [
            'items' => $items,
            'totalPrice' => (int) $items->sum('total_price'),
            'cartCount' => array_sum($cart),
            'tabName' => $this->currentTabName(),
        ];
    }

    /**
     * Choisir l'ardoise sur laquelle on va servir.
     *
     * Un nom qui correspond à une ardoise ouverte la **rejoint** : le panier est
     * préchargé avec ce qu'elle porte déjà, et la validation la réécrira. C'est le
     * geste que le barman a en tête — « remets-leur la même » — et c'est aussi ce qui
     * rend deux ardoises homonymes impossibles sans avoir à refuser quoi que ce soit.
     *
     * Précharger, et non ajouter par-dessus : `loadOrCreateDraftOrder` restitue le
     * stock des lignes existantes puis les supprime avant de reconstruire depuis le
     * panier. Un panier qui ne contiendrait que les nouvelles consommations effacerait
     * donc tout le reste de l'ardoise. Le bénéfice, au passage, c'est qu'un `−` corrige
     * enfin une consommation servie par erreur.
     *
     * @return array{status: string, message: string, joined: bool}
     */
    public function openTab(string $name): array
    {
        $key = BarOrder::normaliseName($name);

        if ($key === '') {
            return ['status' => 'error', 'message' => 'Donnez un nom à cette ardoise.', 'joined' => false];
        }

        $existing = BarOrder::openTabNamed($name);

        if (! $existing instanceof BarOrder) {
            session()->forget('editing_order_id');
            session()->forget('bar_walk_in');
            session()->put('bar_tab_name', trim($name));
            session()->put('cart', []);

            return ['status' => 'success', 'message' => sprintf('Ardoise « %s » ouverte.', trim($name)), 'joined' => false];
        }

        $existing->load('items');

        session()->forget('bar_walk_in');
        session()->put('bar_tab_name', $existing->name);
        session()->put('editing_order_id', $existing->id);
        session()->put('cart', $existing->items
            ->mapWithKeys(fn (BarOrderItem $item): array => [$item->product_id => (int) $item->quantity])
            ->toArray());

        return [
            'status' => 'success',
            'message' => sprintf('Ardoise « %s » rejointe — %s déjà.', $existing->name, euros((int) $existing->total_price)),
            'joined' => true,
        ];
    }

    /**
     * Le client de passage : il commande, il paie, il part.
     *
     * Aucun nom, parce qu'il n'y a rien à retrouver — une commande réglée sur-le-champ
     * n'entre jamais dans la file. Exiger un nom ici mettrait une saisie clavier sur
     * le geste le plus fréquent du bar, et le barman taperait « x ».
     */
    public function openWalkIn(): void
    {
        session()->forget('editing_order_id');
        session()->forget('bar_tab_name');
        session()->put('bar_walk_in', true);
        session()->put('cart', []);
    }

    public function removeProductFromSessionCart(int $productId): void
    {
        $cart = $this->getSanitizedCart();

        if (! isset($cart[$productId])) {
            return;
        }

        $cart[$productId]--;

        if ($cart[$productId] <= 0) {
            unset($cart[$productId]);
        }

        session()->put('cart', $cart);
    }

    private function getSanitizedCart(): array
    {
        return collect(session()->get('cart', []))
            ->mapWithKeys(fn ($qty, $id): array => [(int) $id => (int) $qty])
            ->filter(fn (int $qty): bool => $qty > 0)
            ->toArray();
    }

    private function loadOrCreateDraftOrder($orderId, int $userId, ?string $tabName = null): BarOrder
    {
        if (! $orderId) {
            return BarOrder::query()->create([
                'total_price' => 0,
                'created_by' => $userId,
                'is_paid' => 0,
                'name' => $tabName,
                // La clé ne vit que tant que l'ardoise est ouverte. Elle porte
                // l'unicité, et le paiement la libère pour que le même nom serve de
                // nouveau le même soir.
                'open_name_key' => $tabName === null ? null : BarOrder::normaliseName($tabName),
            ]);
        }

        $order = BarOrder::query()
            ->with('items')
            ->lockForUpdate()
            ->find($orderId);

        if (! $order instanceof BarOrder) {
            throw new \RuntimeException('La commande à modifier est introuvable.');
        }

        // La propriété ne décrit pas un bar : on s'y relaie derrière le comptoir, et
        // celui qui encaisse n'est presque jamais celui qui a servi. `bar.orders.takeover`
        // existait déjà dans l'enum, était accordée au rôle BARMAN, et n'était vérifiée
        // nulle part — ce verrou-ci est le quatrième, et le plus discret : il empêchait
        // même d'ajouter une consommation à l'ardoise d'un collègue.
        if ((int) $order->created_by !== $userId
            && auth()->user()?->can(Permission::BarOrdersTakeover->value) !== true) {
            throw new \RuntimeException("Vous n'êtes pas autorisé à modifier cette commande.");
        }

        if ((bool) $order->is_paid) {
            throw new \RuntimeException('Impossible de modifier une commande déjà payée.');
        }

        foreach ($order->items as $item) {
            $this->stockService->restoreFromOrderItem((int) $item->id, $userId);
        }

        $order->items()->delete();

        return $order;
    }

    private function validateProductStock(BarProduct $product, int $qty): void
    {
        if (! $product->is_available) {
            throw new \RuntimeException(sprintf('Le produit %s n\'est plus disponible.', $product->name));
        }

        $availableStock = max(0, (int) $product->stock);

        if ($qty > $availableStock) {
            throw new \RuntimeException(sprintf('Stock insuffisant pour %s.', $product->name));
        }
    }
}
