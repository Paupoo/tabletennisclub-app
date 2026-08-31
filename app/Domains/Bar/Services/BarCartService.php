<?php

declare(strict_types=1);

namespace App\Domains\Bar\Services;

use App\Domains\Bar\Models\BarOrder;
use App\Domains\Bar\Models\BarOrderItem;
use App\Domains\Bar\Models\BarProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BarCartService
{
    private const string ACTION_PAY_NOW = 'pay_now';

    private const string ACTION_VALIDATE = 'validate';

    public function __construct(
        private readonly StockService $stockService
    ) {}

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

        $order = DB::transaction(function () use ($cart, $userId): BarOrder {
            $orderId = session()->get('editing_order_id');
            $order = $this->loadOrCreateDraftOrder($orderId, $userId);

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
    }

    public function getCartViewData(): array
    {
        $cart = $this->getSanitizedCart();

        $products = BarProduct::query()
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
        ];
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

    private function loadOrCreateDraftOrder($orderId, int $userId): BarOrder
    {
        if (! $orderId) {
            return BarOrder::query()->create([
                'total_price' => 0,
                'created_by' => $userId,
                'is_paid' => 0,
            ]);
        }

        $order = BarOrder::query()
            ->with('items')
            ->lockForUpdate()
            ->find($orderId);

        if (! $order instanceof BarOrder) {
            throw new \RuntimeException('La commande à modifier est introuvable.');
        }

        if ((int) $order->created_by !== $userId) {
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
}
