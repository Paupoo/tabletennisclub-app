<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bar;

use App\Http\Controllers\Controller;
use App\Models\Bar\BarCategory;
use App\Models\Bar\BarProduct;
use App\Models\Bar\BarOrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BarController extends Controller
{
    public function index()
    {
        $categories = BarCategory::with([
            'products.stockMovements'
        ])->orderBy('name')->get();

        $cart = session()->get('cart', []);
        // Compute totals
        $cartCount = array_sum($cart);

        $products = BarProduct::whereIn('id', array_keys($cart))->get();
        $totalPrice = $products->sum(function ($product) use ($cart) {
            return $product->sale_price * ($cart[$product->id] ?? 0);
        });

        //Favorites based on paid orders
        $favoriteProductIds = BarOrderItem::query()
            ->select('bar_order_items.product_id', DB::raw('SUM(bar_order_items.quantity) as total_quantity'))
            ->join('bar_orders', 'bar_orders.id', '=', 'bar_order_items.order_id')
            ->where('bar_orders.is_paid', 1)
            ->groupBy('bar_order_items.product_id')
            ->orderByDesc('total_quantity')
            ->limit(10)
            ->pluck('bar_order_items.product_id')
            
            ->toArray();

            $favorites = BarProduct::with('stockMovements')
            ->whereIn('id', $favoriteProductIds)
            ->get()
            ->filter(function ($product) {
                return (bool) $product->is_available && (int) $product->stock > 0;
                })
            ->sortBy(function ($product) use ($favoriteProductIds) {
                return array_search($product->id, $favoriteProductIds);
            })
            ->values();

        return view('bar.index', compact('categories', 'cart', 'cartCount', 'totalPrice', 'favorites'));
    }
    
    public function add(Request $request)
    {
        $cart = session()->get('cart', []);

        $id = $request->product_id;

        $cart[$id] = ($cart[$id] ?? 0) + 1;

        session()->put('cart', $cart);

        return back();
    }

    public function remove(Request $request)
    {
        $cart = session()->get('cart', []);

        $id = $request->product_id;

        if (isset($cart[$id])) {
            $cart[$id]--;

            if ($cart[$id] <= 0) {
                unset($cart[$id]);
            }
        }

        session()->put('cart', $cart);

        return back();
    }

    public function show()
    {
        return view('bar.cart');
    }
}