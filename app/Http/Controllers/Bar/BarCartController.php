<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bar;

use App\Http\Controllers\Controller;
use App\Models\Bar\BarProduct;
use App\Models\Bar\BarStockMovement;
use App\Models\Bar\BarOrder;
use App\Models\Bar\BarOrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BarCartController extends Controller
{
    /**
     * Middleware d'authentification appliqué à toutes les actions du panier.
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Quantité maximale autorisée par produit dans le panier.
     * Valeur fixée arbitrairement - à discuter
     */
    private const MAX_QTY_PER_PRODUCT = 20;

    public function add(Request $request)
    {
        // Validation des données entrantes.
        $validated = $request->validate([
            'product_id' => 'required|integer|min:1',
        ]);

        $productId = $validated['product_id'];
        $product = BarProduct::findOrFail($productId);

        if (! (bool) $product->is_available) {
            return back();
        }

        $cart = session()->get('cart', []);
        $currentQty = (int) ($cart[$productId] ?? 0);

        $stock = (int) $product->stock;

        // On plafonne aussi à MAX_QTY_PER_PRODUCT pour éviter le monopole de stock.
        if ($currentQty < $stock && $currentQty < self::MAX_QTY_PER_PRODUCT) {
            $cart[$productId] = $currentQty + 1;
            session()->put('cart', $cart);
        }

        return back();
    }

    public function remove(Request $request)
    {
        // Validation des données entrantes.
        $validated = $request->validate([
            'product_id' => 'required|integer|min:1',
        ]);

        $productId = $validated['product_id'];
        $cart = session()->get('cart', []);

        if (isset($cart[$productId])) {
            $cart[$productId] = (int) $cart[$productId] - 1;

            if ($cart[$productId] <= 0) {
                unset($cart[$productId]);
            }

            session()->put('cart', $cart);
        }

        return back();
    }

    public function show()
    {
        $cart = session()->get('cart', []);

        // On assainit le contenu de la session pour ne garder que des entiers positifs.
        $cart = array_filter(
            array_map('intval', $cart),
            fn(int $qty) => $qty > 0
        );

        $products = BarProduct::whereIn('id', array_keys($cart))
            ->orderBy('name')
            ->get();

        $items = $products->map(function (BarProduct $product) use ($cart) {
            $qty = (int) ($cart[$product->id] ?? 0);

            return [
                'product'     => $product,
                'quantity'    => $qty,
                'total_price' => $qty * (int) $product->sale_price,
            ];
        });

        $cartCount  = array_sum($cart);
        $totalPrice = (int) $items->sum('total_price');

        return view('bar.carts.index', [
            'items'      => $items,
            'totalPrice' => $totalPrice,
            'cartCount'  => $cartCount,
        ]);
    }

    public function clear()
    {
        session()->forget('cart');

        return back()->with('success', 'Panier vidé avec succès.');
    }

    public function validateOrder(Request $request)
    {
        // On assainit le contenu de la session avant tout traitement.
        $cart = array_filter(
            array_map('intval', session()->get('cart', [])),
            fn(int $qty) => $qty > 0
        );

        if (empty($cart)) {
            return redirect()->route('bar.carts.show')
                ->with('error', 'Le panier est vide.');
        }

        $action = $request->input('action', 'validate');
        $userId = auth()->id(); // Garanti non-null grâce au middleware auth.

        $products = BarProduct::whereIn('id', array_keys($cart))->get();

        $totalPrice = $products->sum(function ($product) use ($cart) {
            return $product->sale_price * ($cart[$product->id] ?? 0);
        });

        $order = null;

        try {
            DB::transaction(function () use ($cart, $userId, $totalPrice, &$order) {
                $orderId = session()->get('editing_order_id');

                if ($orderId) {
                    $order = BarOrder::with('items')->lockForUpdate()->find($orderId);

                    if (! $order) {
                        throw new \RuntimeException('La commande à modifier est introuvable.');
                    }

                    // On vérifie que la commande appartient bien à l'utilisateur connecté.
                    // Sans ce contrôle, un utilisateur pourrait modifier la commande d'un autre
                    // si l'ID de commande en session a été altéré ou partagé.
                    if ((int) $order->created_by !== (int) $userId) {
                        throw new \RuntimeException("Vous n'êtes pas autorisé à modifier cette commande.");
                    }

                    if ($order->is_paid) {
                        throw new \RuntimeException('Impossible de modifier une commande déjà payée.');
                    }

                    foreach ($order->items as $item) {
                        BarStockMovement::create([
                            'product_id'    => $item->product_id,
                            'quantity'      => $item->quantity,
                            'movement_type' => 'IN',
                            'reason'        => "Order #{$order->id} - modification (revert)",
                            'created_by'    => null,
                            'modified_by'   => $userId,
                        ]);
                    }

                    $order->items()->delete();

                    $order->update([
                        'total_price' => $totalPrice,
                        'modified_by' => $userId,
                    ]);

                } else {
                    $order = BarOrder::create([
                        'total_price' => $totalPrice,
                        'created_by'  => $userId,
                        'is_paid'     => 0,
                    ]);
                }

                // Le stock est vérifié sous lockForUpdate() à l'intérieur de la transaction.
                // C'est ici que la race condition est résolue : entre le moment où add() lit le stock
                // et le moment où validateOrder() le décrémente, personne d'autre ne peut écrire.
                $products = BarProduct::whereIn('id', array_keys($cart))
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($cart as $productId => $qty) {
                    $qty     = (int) $qty;
                    $product = $products->get((int) $productId);

                    if (! $product) {
                        throw new \RuntimeException("Produit introuvable (ID {$productId}).");
                    }

                    $availableStock = (int) $product->stock;

                    if ($qty > $availableStock) {
                        throw new \RuntimeException("Stock insuffisant pour {$product->name}.");
                    }

                    BarOrderItem::create([
                        'order_id'    => $order->id,
                        'product_id'  => $product->id,
                        'quantity'    => $qty,
                        'unit_price'  => $product->sale_price,
                        'total_price' => $product->sale_price * $qty,
                    ]);
                }
            });
        } catch (\RuntimeException $e) {
            return redirect()->route('bar.index')
                ->with('error', $e->getMessage());
        }

        session()->forget('cart');
        session()->forget('editing_order_id');

        if ($action === 'pay_now') {
            return redirect()->route('bar.payment.show', $order)
                ->with('success', 'Commande créée. Procédez au paiement.');
        }

        return redirect()->route('bar.orders.index')
            ->with('success', 'Commande validée');
    }
}
