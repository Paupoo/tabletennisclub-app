<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bar;

use App\Http\Controllers\Controller;
use App\Models\Bar\BarCategory;
use App\Models\Bar\BarProduct;
use App\Models\Bar\BarStockMovement;
use Illuminate\Http\Request;

class BarProductController extends Controller
{
    public function index()
    {
        $categories = BarCategory::with(['products' => function ($q) {
                $q->orderBy('name');
            }])
            ->orderBy('name')
            ->get();

        // Récupère les données sauvegardées par storeState() et les supprime de la session.
        $savedForm = session()->pull('product_form', []);

        return view('bar.products.index', compact('categories', 'savedForm'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id'  => 'required|exists:bar_categories,id',
            'product_name' => 'required|string|max:150|unique:bar_products,name',
            'sale_price'   => ['required', 'string', 'regex:/^\d+(?:[\.,]\d{1,2})?$/'],
            'stock'        => 'required|integer|min:0',
            'is_available' => 'required|boolean',
        ]);

        $validated['sale_price'] = cents($validated['sale_price']);

        $initialStock = (int) $validated['stock'];
        unset($validated['stock']);

        // ← Renommer product_name → name avant le create()
        $validated['name'] = $validated['product_name'];
        unset($validated['product_name']);

        $product = BarProduct::create($validated);

        if ($initialStock > 0) {
            BarStockMovement::create([
                'product_id'    => $product->id,
                'quantity'      => $initialStock,
                'movement_type' => 'IN',
                'reason'        => 'Initial stock',
                'created_by'    => auth()->id(),
                'modified_by'   => null,
            ]);
        }

        session()->forget('product_form');

        return back()->with('success', 'Product created');
    }

    public function update(Request $request, BarProduct $product)
    {
        $validated = $request->validate([
            'product_name' => ['sometimes', 'string', 'max:150', 'unique:bar_products,name,' . $product->id],
            'category_id'  => ['sometimes', 'exists:bar_categories,id'],
            'sale_price'   => ['sometimes', 'string', 'regex:/^\d+(?:[\.,]\d{1,2})?$/'],
            'stock'        => ['sometimes', 'integer', 'min:0'],
            'is_available' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('sale_price', $validated)) {
            $validated['sale_price'] = cents($validated['sale_price']);
        }

        if (array_key_exists('stock', $validated)) {
            $newStock = (int) $validated['stock'];
            unset($validated['stock']);

            $currentStock = (int) $product->stock;
            $delta = $newStock - $currentStock;

            if ($delta > 0) {
                BarStockMovement::create([
                    'product_id'    => $product->id,
                    'quantity'      => $delta,
                    'movement_type' => 'IN',
                    'reason'        => 'Stock adjustment',
                    'created_by'    => null,
                    'modified_by'   => auth()->id(),
                ]);
            } elseif ($delta < 0) {
                BarStockMovement::create([
                    'product_id'    => $product->id,
                    'quantity'      => abs($delta),
                    'movement_type' => 'OUT',
                    'reason'        => 'Stock adjustment',
                    'created_by'    => null,
                    'modified_by'   => auth()->id(),
                ]);
            }
        }

        // ← Renommer product_name → name avant le update()
        if (array_key_exists('product_name', $validated)) {
            $validated['name'] = $validated['product_name'];
            unset($validated['product_name']);
        }

        if (!empty($validated)) {
            $product->update($validated);
        }

        return back()->with('success', 'Product updated');
    }

    public function destroy(BarProduct $product)
    {
        if ((int) $product->stock > 0) {
            return back()->with('error', 'Impossible de supprimer : stock non nul.');
        }

        $product->delete();

        return back()->with('success', 'Produit supprimé avec succès.');
    }

    public function storeState(Request $request)
    {
        session([
            'product_form' => $request->only([
                'product_name',
                'sale_price',
                'stock',
                'is_available',
                'category_id',
            ])
        ]);

        return response()->noContent();
    }
}
