<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bar;

use App\Http\Controllers\Controller;
use App\Models\Bar\BarCategory;
use App\Models\Bar\BarProduct;

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

        return view('bar.index', compact('categories', 'cart', 'cartCount', 'totalPrice'));
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