<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bar;

use App\Domains\Bar\Models\BarCategory;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Services\StockService;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BarProductController extends Controller
{
    private StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->middleware('auth');
        $this->stockService = $stockService;
    }

    public function destroy(BarProduct $product): RedirectResponse
    {
        if ((int) $product->stock > 0) {
            return back()->with('error', 'Impossible de supprimer : stock non nul.');
        }

        $product->delete();

        return back()->with('success', 'Produit supprimé avec succès.');
    }

    public function index(): View
    {
        $categories = BarCategory::with(['products' => function (HasMany $q): void {
            $q->orderBy('name');
        }])
            ->orderBy('name')
            ->get();

        // Récupère les données sauvegardées par storeState() et les supprime de la session.
        $savedForm = session()->pull('product_form', []);

        return view('bar.products.index', compact('categories', 'savedForm'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:bar_categories,id',
            'product_name' => 'required|string|max:150|unique:bar_products,name',
            'sale_price' => ['required', 'string', 'regex:/^\d+(?:[\.,]\d{1,2})?$/'],
            'stock' => 'required|integer|min:0',
            'is_available' => 'required|boolean',
        ]);

        $validated['sale_price'] = cents($validated['sale_price']);

        $initialStock = (int) $validated['stock'];
        unset($validated['stock']);

        // ← Renommer product_name → name avant le create()
        $validated['name'] = $validated['product_name'];
        unset($validated['product_name']);

        DB::transaction(function () use ($validated, $initialStock): void {
            $product = BarProduct::create($validated);

            if ($initialStock > 0) {
                $this->stockService->addIncomingStock(
                    (int) $product->id,
                    $initialStock,
                    'Initial stock',
                    auth()->id(),
                    auth()->id()
                );
            }
        });

        session()->forget('product_form');

        return back()->with('success', 'Product created');
    }

    public function storeState(Request $request): Response
    {
        $validated = $request->validate([
            'product_name' => 'nullable|string|max:150',
            'sale_price' => ['nullable', 'string', 'regex:/^\d+(?:[\.,]\d{1,2})?$/'],
            'stock' => 'nullable|integer|min:0',
            'is_available' => 'nullable|boolean',
            'category_id' => 'nullable|exists:bar_categories,id',
        ]);

        session([
            'product_form' => $validated,
        ]);

        return response()->noContent();
    }

    public function update(Request $request, BarProduct $product): RedirectResponse
    {
        $validated = $request->validate([
            'product_name' => ['sometimes', 'string', 'max:150', 'unique:bar_products,name,' . $product->id],
            'category_id' => ['sometimes', 'exists:bar_categories,id'],
            'sale_price' => ['sometimes', 'string', 'regex:/^\d+(?:[\.,]\d{1,2})?$/'],
            'stock' => ['sometimes', 'integer', 'min:0'],
            'is_available' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($validated, $product): void {
            if (array_key_exists('stock', $validated)) {
                $newStock = (int) $validated['stock'];
                unset($validated['stock']);

                $currentStock = (int) $product->stock;
                $delta = $newStock - $currentStock;

                if ($delta > 0) {
                    $this->stockService->addIncomingStock(
                        (int) $product->id,
                        $delta,
                        'Stock adjustment',
                        auth()->id(),
                        auth()->id()
                    );
                } elseif ($delta < 0) {
                    $this->stockService->consumeFIFO(
                        (int) $product->id,
                        abs($delta),
                        'Stock adjustment',
                        auth()->id(),
                        auth()->id()
                    );
                }
            }

            if (array_key_exists('sale_price', $validated)) {
                $validated['sale_price'] = cents($validated['sale_price']);
            }

            if (array_key_exists('product_name', $validated)) {
                $validated['name'] = $validated['product_name'];
                unset($validated['product_name']);
            }

            if (! empty($validated)) {
                $product->update($validated);
            }
        });

        return back()->with('success', 'Product updated');
    }
}
