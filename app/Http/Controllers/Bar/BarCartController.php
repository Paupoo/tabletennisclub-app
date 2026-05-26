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
    public function add(Request $request)
    {
        $productId = (int) $request->input('product_id');
        $product = BarProduct::findOrFail($productId);

        // Don't allow adding unavailable products
        if (! (bool) $product->is_available) {
            return back();
        }

        $cart = session()->get('cart', []);
        $currentQty = (int) ($cart[$productId] ?? 0);

        // Stock is computed from movements (FIFO-ready)
        $stock = (int) $product->stock;

        if ($currentQty < $stock) {
            $cart[$productId] = $currentQty + 1;
            session()->put('cart', $cart);
        }

        return back();
    }

    public function remove(Request $request)
    {
        $productId = (int) $request->input('product_id');
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

        $products = BarProduct::whereIn('id', array_keys($cart))
            ->orderBy('name')
            ->get();

        $items = $products->map(function (BarProduct $product) use ($cart) {
            $qty = (int) ($cart[$product->id] ?? 0);

            return [
                'product' => $product,
                'quantity' => $qty,
                'total_price' => $qty * (int) $product->sale_price,
            ];
        });

        $cartCount = array_sum($cart);
        $totalPrice = (int) $items->sum('total_price');

        return view('bar.carts.index', [
            'items' => $items,
            'totalPrice' => $totalPrice,
            'cartCount' => $cartCount,
        ]);
    }

    public function clear()
    {
        session()->forget('cart');
        
        return back()->with('success', 'Panier vidé avec succès.');
    }

    public function validateOrder(Request $request)
{
    $cart = session()->get('cart', []);

    if (empty($cart)) {
        return redirect()->route('bar.carts.show')
            ->with('error', 'Le panier est vide.');
    }

    $action = $request->input('action', 'validate');
    $userId = auth()->id();

    $products = BarProduct::whereIn('id', array_keys($cart))->get();

    $totalPrice = $products->sum(function ($product) use ($cart) {
        return $product->sale_price * ($cart[$product->id] ?? 0);
    });

    $order = null;

    try {
        DB::transaction(function () use ($cart, $userId, $totalPrice, &$order) {
            $orderId = session()->get('editing_order_id');

            if ($orderId) {
                // Editing an existing order
                $order = BarOrder::with('items')->lockForUpdate()->find($orderId);

                if (! $order) {
                    throw new \RuntimeException('La commande à modifier est introuvable.');
                }

                if ($order->is_paid) {
                    throw new \RuntimeException('Impossible de modifier une commande déjà payée.');
                }

                // Revert previous stock impact
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

                // Delete previous lines
                $order->items()->delete();

                // Update order header
                $order->update([
                    'total_price' => $totalPrice,
                    'modified_by' => $userId,
                ]);

            } else {
                // Create new order
                $order = BarOrder::create([
                    'total_price' => $totalPrice,
                    'created_by'  => $userId,
                    'is_paid'     => 0,
                ]);
            }

            // Lock current products for stock verification
            $products = BarProduct::whereIn('id', array_keys($cart))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($cart as $productId => $qty) {
                $qty = (int) $qty;
                $product = $products->get((int) $productId);

                if (! $product) {
                    throw new \RuntimeException("Produit introuvable (ID {$productId}).");
                }

                $availableStock = (int) $product->stock;

                if ($qty > $availableStock) {
                    throw new \RuntimeException("Stock insuffisant pour {$product->name}.");
                }

                // Recreate order lines
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

    // Clean session state after success
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