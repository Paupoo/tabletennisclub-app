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

        BarCategory::create([
            'name' => $name,
        ]);

        // Retour sur l'écran des catégories, et pas sur celui des produits : on a
        // demandé « crée une catégorie », pas « montre-moi les produits ». La
        // redirection servait un détour — venir de l'écran produits pour créer la
        // catégorie manquante — que la modale du tableau a rendu inutile, et qui
        // désorientait quiconque arrivait par le menu : la catégorie créée ne
        // s'affichait jamais dans sa propre liste, sans un mot de confirmation.
        return back()->with('success', __('Category created.'));
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
