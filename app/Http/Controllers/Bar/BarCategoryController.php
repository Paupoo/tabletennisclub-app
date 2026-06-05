<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bar;

use App\Domains\Bar\Models\BarCategory;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BarCategoryController extends Controller
{
    public function destroy(BarCategory $category): RedirectResponse
    {
        if ($category->products()->exists()) {
            return back()->with('error', 'Cannot delete: category is used by products');
        }

        $category->delete();

        return back()->with('success', 'Category deleted');
    }

    public function index(Request $request): View
    {
        $categories = BarCategory::withCount('products')  // ← nécessaire pour $category->products_count dans le Blade
            ->orderBy('name')
            ->get();

        return view('bar.categories.index', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category_name' => 'required|string|max:150|unique:bar_categories,name',
        ]);

        $name = trim(preg_replace('/\s+/', ' ', $validated['category_name']));

        $category = BarCategory::create([
            'name' => $name,
        ]);

        return redirect()
            ->route('bar.products.index')
            ->with('selected_category_id', $category->id)
            ->with('open_product_panel', true);
    }

    public function update(Request $request, BarCategory $category): RedirectResponse
    {
        $validated = $request->validate([
            'category_name' => 'required|string|max:150|unique:bar_categories,name,' . $category->id,
        ]);

        $name = trim(preg_replace('/\s+/', ' ', $validated['category_name']));

        $category->update([
            'name' => $name,
        ]);

        return back()->with('success', 'Category updated');
    }
}
