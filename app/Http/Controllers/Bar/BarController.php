<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bar;

use App\Domains\Bar\Models\BarCategory;
use App\Domains\Bar\Models\BarOrderItem;
use App\Domains\Bar\Models\BarProduct;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BarController extends Controller
{
    /**
     * Must stay in sync with the front-end limit used in bar/index.blade.php.
     */
    // private const MAX_QTY_PER_PRODUCT = 20;

    public function add(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:bar_products,id',
        ]);

        $id = $validated['product_id'];

        $product = BarProduct::findOrFail($id);

        if(! $product->is_available) {
            return back()->with('error', 'Produit indisponible.');
        }

        $cart = $this->sanitizedCart();
        $currentQty = $cart[$id] ?? 0;
        $stock = (int) $product->stock;

        // Server-side enforcement of the same limits the UI disables the
        // "+" button for. The UI state can be bypassed, so this must not
        // rely on the button being disabled.
        if($currentQty >= $stock) {
            return back()->with('error', 'Stock maximum atteint pour ce produit.');
        }

        // if($currentQty >= self::MAX_QTY_PER_PRODUCT) {
        //     return back()->with('error', 'Quantité maximale atteinte pour ce produit.');
        // }

        $cart[$id] = $currentQty + 1;

        session()->put('cart', $cart);

        return back()->with('success', 'Produit ajouté au panier.');
    }

    public function index(): View
    {
        // lighter query (no stockMovements)
        $categories = BarCategory::with(['products' => function ($q) {
            $q->orderBy('name');
        }])
            ->orderBy('name')
            ->get();

        $cart = $this->sanitizedCart();
        $cartCount = array_sum($cart);

        // safe product loading
        $products = BarProduct::whereIn('id', array_keys($cart))
            ->get()
            ->keyBy('id');

        // safe total computation
        $totalPrice = collect($cart)->sum(function ($qty, $productId) use ($products): int {
            $product = $products->get($productId);
            return $product ? (int) $product->sale_price * $qty : 0;
        });

        // favorites based on paid orders
        $favoriteProductIds = BarOrderItem::query()
            ->select('bar_order_items.product_id', DB::raw('SUM(bar_order_items.quantity) as total_quantity'))
            ->join('bar_orders', 'bar_orders.id', '=', 'bar_order_items.order_id')
            ->where('bar_orders.is_paid', 1)
            ->groupBy('bar_order_items.product_id')
            ->orderByDesc('total_quantity')
            ->limit(10)
            ->pluck('bar_order_items.product_id')
            ->toArray();

        // O(1) rank lookup instead of calling array_search() per item in sortBy().
        $favoriteRank = array_flip($favoriteProductIds);

        $favorites = BarProduct::whereIn('id', $favoriteProductIds)
            ->get()
            ->filter(function (BarProduct $product): bool {
                return (bool) $product->is_available && (int) $product->stock > 0;
            })
            ->sortBy(function (BarProduct $product) use ($favoriteRank): int {
                return $favoriteRank[$product->id] ?? PHP_INT_MAX;
            })
            ->values();

        return view('bar.index', compact(
            'categories',
            'cart',
            'cartCount',
            'totalPrice',
            'favorites'
        ));
    }

    public function remove(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:bar_products,id',
        ]);

        $id = $validated['product_id'];

        $cart = $this->sanitizedCart();

        if(isset($cart[$id])) {
            $cart[$id]--;

            if($cart[$id] <= 0) {
                unset($cart[$id]);
            }

            session()->put('cart', $cart);

            return back()->with('success', 'Produit retiré du panier.');
        }

        return back()->with('info', 'Produit non présent dans le panier.');
    }

    public function show(): View
    {
        return view('bar.cart');
    }

    /**
     * Cart as stored in session, cleaned of non-positive / non-integer
     * quantities. Centralized here so add()/remove()/index() can't drift.
     *
     * @return array<int, int>
     */
    private function sanitizedCart(): array
    {
        return array_filter(
            array_map('intval', session()->get('cart', [])),
            fn($qty) => $qty > 0
        );
    }
}
