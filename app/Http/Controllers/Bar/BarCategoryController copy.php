<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bar;

use App\Http\Controllers\Controller;
use App\Models\Bar\BarCategory;
use Illuminate\Http\Request;

class BarCategoryController extends Controller
{
    public function index()
    {
        $categories = BarCategory::withCount('products')
            ->orderBy('name')
            ->get();

        return view('bar.categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_name' => 'required|string|max:150|unique:bar_categories,name',
        ]);

        $name = trim(preg_replace('/\s+/', ' ', $validated['category_name']));

        $category = BarCategory::create(['name' => $name]);

        return redirect()
            ->route('bar.products.index') // go back to product page
            ->with('success', 'Category created')
            ->with('selected_category_id', $category->id) // pass info to select the new category in the dropdown
            ->with('open_product_panel', true)
            ->withInput(); // pass info to open the product creation panel
    }

    public function update(Request $request, BarCategory $category)
    {
        $validated = $request->validate([
            'category_name' => 'required|string|max:150|unique:bar_categories,name,' . $category->id,
        ]);

        $name = trim(preg_replace('/\s+/', ' ', $validated['category_name']));

        $category->update([
            'name' => $name,
            'modified_by' => auth()->id(),
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