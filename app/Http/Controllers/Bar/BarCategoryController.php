<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bar;

use App\Http\Controllers\Controller;
use App\Domains\Bar\Models\BarCategory;
use Illuminate\Http\Request;

class BarCategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = BarCategory::withCount('products')  // ← nécessaire pour $category->products_count dans le Blade
            ->orderBy('name')
            ->get();

        return view('bar.categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_name' => 'required|string|max:150|unique:bar_categories,name'
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

    public function update(Request $request, BarCategory $category)
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

    public function destroy(BarCategory $category)
    {
        if ($category->products()->exists()) {
            return back()->with('error', 'Cannot delete: category is used by products');
        }

        $category->delete();

        return back()->with('success', 'Category deleted');
    }
}
