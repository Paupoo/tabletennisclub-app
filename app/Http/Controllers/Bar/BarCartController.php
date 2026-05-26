<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bar;

use App\Http\Controllers\Controller;
use App\Models\Bar\BarProduct;
use App\Models\Bar\BarStockMovement;
use App\Models\Bar\BarOrder;
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

        return view('bar.cart.index', [
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
            return redirect()->route('bar.cart.show')
                ->with('error', 'Le panier est vide.');
        }

        $action = $request->input('action', 'validate');
        $userId = auth()->id();

        $products = BarProduct::whereIn('id', array_keys($cart))->get();

        $totalPrice = $products->sum(function ($product) use ($cart) {
            return $product->sale_price * ($cart[$product->id] ?? 0);
        });

        $order = null;

        DB::transaction(function () use ($cart, $userId, $totalPrice, &$order) {

            $order = \App\Models\Bar\BarOrder::create([
                'created_by'  => $userId,
                'total_price' => $totalPrice,
                'is_paid'     => 0,
            ]);

            $products = BarProduct::whereIn('id', array_keys($cart))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($cart as $productId => $qty) {
                $qty = (int) $qty;
                $product = $products->get((int) $productId);

                if (! $product) {
                    throw new \RuntimeException("Produit introuvable");
                }

                $availableStock = (int) $product->stock;

                if ($qty > $availableStock) {
                    throw new \RuntimeException("Stock insuffisant pour {$product->name}");
                }

                \App\Models\Bar\BarStockMovement::create([
                    'product_id'    => $product->id,
                    'quantity'      => $qty,
                    'movement_type' => 'OUT',
                    'reason'        => "Order #{$order->id} - validation",
                    'created_by'    => $userId,
                ]);

                \App\Models\Bar\BarOrderItem::create([
                    'order_id'    => $order->id,
                    'product_id'  => $product->id,
                    'quantity'    => $qty,
                    'unit_price'  => $product->sale_price,
                    'total_price' => $product->sale_price * $qty,
                ]);
            }
        });

        session()->forget('cart');

        if ($action === 'pay_now') {
            return redirect()->route('bar.payment.show', $order)
                ->with('success', 'Commande créée. Procédez au paiement.');
        }

        return redirect()->route('bar.orders.index')
            ->with('success', 'Commande validée');
    }
}