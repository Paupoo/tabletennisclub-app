<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bar;

use App\Http\Controllers\Controller;
use App\Models\Bar\BarProduct;
use App\Models\Bar\BarCategory;
use Illuminate\Http\Request;

class BarProductController extends Controller
{
    // BarProductController.php
    public function index()
    {
        $categories = BarCategory::with(['products' => function ($q) {
            $q->orderBy('name');
            }])
            ->orderBy('name')
            ->get();
        // Optional if you still need a flat list elsewhere:
        // $products = BarProduct::with('category')->orderBy('name')->get();

        return view('bar.products.index', compact('categories'));
}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:bar_categories,id',
            'name'        => 'required|string|max:150|unique:bar_products,name',
            'sale_price'  => 'required|string|regex:/^\d+(\.\d{1,2})?$/',
            'is_available'=> 'required|boolean',
        ]);
        $validated['sale_price'] = cents($validated['sale_price']);

        BarProduct::create($validated);

        return redirect()->back()->with('success', 'Product created');
    }

    public function update(Request $request, BarProduct $product)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:bar_categories,id',
            'name'        => 'required|string|max:150|unique:bar_products,name,' . $product->id,
            'sale_price'  => 'required|string|regex:/^\d+(\.\d{1,2})?$/',
            'is_available'=> 'required|boolean',
        ]);
        $validated['sale_price'] = cents($validated['sale_price']);

        $product->update($validated);

        return redirect()->back()->with('success', 'Product updated');
    }

    public function destroy(BarProduct $product)
    {
        $product->delete();

        return redirect()->back()->with('success', 'Product deleted');
    }
}
