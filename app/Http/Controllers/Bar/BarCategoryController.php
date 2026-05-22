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
            'name' => 'required|string|max:150|unique:bar_categories,name',
        ]);

        $name = trim(preg_replace('/\s+/', ' ', $validated['name']));

        BarCategory::create(['name' => $name]);

        return back()->with('success', 'Category created');
    }

    public function update(Request $request, BarCategory $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150|unique:bar_categories,name,' . $category->id,
        ]);

        $name = trim(preg_replace('/\s+/', ' ', $validated['name']));

        $category->update(['name' => $name]);

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