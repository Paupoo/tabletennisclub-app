<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ArticlesCategoryEnum;
use App\Enums\ArticlesStatusEnum;
use App\Models\Article;
use App\Support\Breadcrumb;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ArticleController extends Controller
{
    /**
     * Archive un article
     */
    public function archive(Article $article)
    {
        $article->update([
            'status' => ArticlesStatusEnum::ARCHIVED,
        ]);

        return redirect()->back()
            ->with('success', 'Article archivé avec succès !');
    }

    /**
     * API endpoint pour l'auto-sauvegarde (AJAX)
     */
    public function autoSave(Request $request, Article $article)
    {
        // Validation basique pour l'auto-sauvegarde
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'slug' => 'sometimes|string|max:255',
        ]);

        // Vérifier que l'utilisateur peut modifier cet article
        if ($article->user_id !== auth()->id() && ! auth()->user()->hasRole('admin')) {
            return response()->json(['error' => 'Non autorisé'], 403);
        }

        // Mettre à jour uniquement les champs fournis
        $article->update(array_filter($validated));

        return response()->json([
            'success' => true,
            'message' => 'Sauvegarde automatique effectuée',
            'updated_at' => $article->fresh()->updated_at->format('d/m/Y H:i:s'),
        ]);
    }

    /**
     * Affiche le formulaire de création d'un nouvel article
     */
    public function create()
    {
        $breadcrumbs = $breadcrumbs = Breadcrumb::make()
            ->home()
            ->articles()
            ->current(__('Create'))
            ->toArray();

        return view('admin.articles.create', compact('breadcrumbs'));
    }

    /**
     * Supprime un article (soft delete)
     */
    public function destroy(Article $article)
    {
        // Supprimer l'image associée si elle existe
        if ($article->image) {
            Storage::disk('public')->delete($article->image);
        }

        $article->delete();

        return redirect()->route('admin.articles.index')
            ->with('success', 'Article supprimé avec succès !');
    }

    /**
     * Duplique un article existant
     */
    public function duplicate(Article $article)
    {
        $newArticle = $article->replicate();

        // Modifier les champs pour éviter les conflits
        $newArticle->title = $article->title . ' - Copie';
        $newArticle->slug = $article->slug . '-copie-' . time();
        $newArticle->status = ArticlesStatusEnum::DRAFT;
        $newArticle->user_id = auth()->id();

        // Dupliquer l'image si elle existe
        if ($article->image) {
            $extension = pathinfo($article->image, PATHINFO_EXTENSION);
            $filename = pathinfo($article->image, PATHINFO_FILENAME);
            $newImagePath = 'articles/' . $filename . '-copie-' . time() . '.' . $extension;

            if (Storage::disk('public')->exists($article->image)) {
                Storage::disk('public')->copy($article->image, $newImagePath);
                $newArticle->image = $newImagePath;
            }
        }

        $newArticle->save();

        return redirect()->route('admin.articles.edit', $newArticle)
            ->with('success', 'Article dupliqué avec succès ! Vous pouvez maintenant le modifier.');
    }

    /**
     * Affiche le formulaire d'édition d'un article
     */
    public function edit(Article $article)
    {
        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->articles()
            ->add($article->title, route('admin.articles.edit', $article))
            ->current(__('Edit'))
            ->toArray();

        return view('admin.articles.edit', compact('article', 'breadcrumbs'));
    }

    /**
     * Export des articles en CSV
     */
    public function export(Request $request)
    {
        $query = Article::with('user');

        // Appliquer les mêmes filtres que sur la page index
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('visibility')) {
            $query->where('is_public', $request->visibility);
        }

        $articles = $query->latest()->get();

        $filename = 'articles_' . now()->format('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($articles): void {
            $file = fopen('php://output', 'w');

            // En-têtes CSV
            fputcsv($file, [
                'ID',
                'Titre',
                'Slug',
                'Catégorie',
                'Statut',
                'Visibilité',
                'Auteur',
                'Date de création',
                'Date de modification',
                'Nombre de caractères',
                'Nombre de mots',
            ]);

            // Données
            foreach ($articles as $article) {
                fputcsv($file, [
                    $article->id,
                    $article->title,
                    $article->slug,
                    $article->category->name,
                    $article->status->value,
                    $article->is_public ? 'Public' : 'Privé',
                    $article->user->first_name . ' ' . $article->user->last_name,
                    $article->created_at->format('d/m/Y H:i'),
                    $article->updated_at->format('d/m/Y H:i'),
                    strlen($article->content),
                    str_word_count(strip_tags($article->content)),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Supprime définitivement un article
     */
    public function forceDestroy($id)
    {
        $article = Article::withTrashed()->findOrFail($id);

        // Supprimer l'image associée si elle existe
        if ($article->image) {
            Storage::disk('public')->delete($article->image);
        }

        $article->forceDelete();

        return redirect()->route('admin.articles.index')
            ->with('success', 'Article supprimé définitivement !');
    }

    /**
     * Affiche la liste des articles avec filtres et pagination
     */
    public function index(Request $request)
    {
        $query = Article::with('user');

        // Filtres de recherche
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('visibility')) {
            $query->where('is_public', $request->visibility);
        }

        // Tri par défaut : les plus récents en premier
        $articles = $query->latest()->paginate($request->get('perPage', 25));

        // Statistiques pour le dashboard
        $stats = collect([
            'totalPublished' => Article::where('status', ArticlesStatusEnum::PUBLISHED)->count(),
            'totalDraft' => Article::where('status', ArticlesStatusEnum::DRAFT)->count(),
            'totalPublic' => Article::where('is_public', true)->count(),
            'totalPrivate' => Article::where('is_public', false)->count(),
        ]);

        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->articles()
            ->toArray();

        return view('admin.articles.index', compact('articles', 'stats', 'breadcrumbs'));
    }

    /**
     * Génère un aperçu de l'article sans le sauvegarder
     */
    public function preview(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string',
            'content' => 'required|string',
            'category' => 'required|string',
            'is_public' => 'boolean',
        ]);

        // Créer un article temporaire pour l'aperçu (non sauvegardé)
        $previewArticle = new Article($data);
        $previewArticle->user = auth()->user();
        $previewArticle->created_at = now();

        return view('admin.articles.preview', compact('previewArticle'));
    }

    /**
     * Publie un article (change le statut en publié)
     */
    public function publish(Article $article)
    {
        $article->update([
            'status' => ArticlesStatusEnum::PUBLISHED,
        ]);

        return redirect()->back()
            ->with('success', 'Article publié avec succès !');
    }

    /**
     * Restaure un article supprimé (soft deleted)
     */
    public function restore($id)
    {
        $article = Article::withTrashed()->findOrFail($id);
        $article->restore();

        return redirect()->route('admin.articles.index')
            ->with('success', 'Article restauré avec succès !');
    }

    /**
     * Affiche les détails d'un article
     */
    public function show(Article $article)
    {
        $article->load('user');

        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->articles()
            ->add($article->title)
            ->toArray();

        return view('admin.articles.show', compact('article', 'breadcrumbs'));
    }

    /**
     * Statistiques détaillées pour le dashboard
     */
    public function statistics()
    {
        $stats = [
            'total' => Article::count(),
            'published' => Article::where('status', ArticlesStatusEnum::PUBLISHED)->count(),            'draft' => Article::where('status', ArticlesStatusEnum::DRAFT)->count(),
            'archived' => Article::where('status', ArticlesStatusEnum::ARCHIVED)->count(),
            'public' => Article::where('is_public', true)->count(),
            'private' => Article::where('is_public', false)->count(),
            'by_category' => Article::selectRaw('category, COUNT(*) as count')
                ->groupBy('category')
                ->pluck('count', 'category')
                ->toArray(),
            'by_author' => Article::with('user')
                ->selectRaw('user_id, COUNT(*) as count')
                ->groupBy('user_id')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->user->full_name => $item->count];
                })->toArray(),
            'recent_activity' => Article::with('user')
                ->latest()
                ->limit(5)
                ->get(),
        ];

        return response()->json($stats);
    }

    /**
     * Enregistre un nouvel article
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:articles,slug',
            'content' => 'required|string',
            'category' => ['required', Rule::enum(ArticlesCategoryEnum::class)],
            'status' => ['required', Rule::enum(ArticlesStatusEnum::class)],
            'is_public' => 'required|boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Génération automatique du slug si nécessaire
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']);
        }

        // Vérification de l'unicité du slug
        $originalSlug = $validated['slug'];
        $counter = 1;
        while (Article::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $originalSlug . '-' . $counter;
            $counter++;
        }

        // Gestion de l'upload d'image
        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('articles', 'public');
        }

        // Ajout de l'utilisateur connecté
        $validated['user_id'] = auth()->id();

        $article = Article::create($validated);

        // Redirection selon l'action demandée
        if ($request->get('action') === 'save_and_continue') {
            return redirect()->route('admin.articles.edit', $article)
                ->with('success', 'Article créé avec succès ! Vous pouvez continuer à le modifier.');
        }

        return redirect()->route('admin.articles.index')
            ->with('success', 'Article créé avec succès !');
    }

    /**
     * Affiche les articles supprimés (corbeille)
     */
    public function trash(Request $request)
    {
        $query = Article::onlyTrashed()->with('user');

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $trashedArticles = $query->latest('deleted_at')->paginate($request->get('perPage', 25));

        $breadcrumbs = [
            ['name' => 'Admin', 'url' => route('admin.dashboard')],
            ['name' => 'Articles', 'url' => route('admin.articles.index')],
            ['name' => 'Corbeille', 'url' => null],
        ];

        return view('admin.articles.trash', compact('trashedArticles', 'breadcrumbs'));
    }

    /**
     * Met à jour un article existant
     */
    public function update(Request $request, Article $article)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:255', Rule::unique('articles', 'slug')->ignore($article->id)],
            'content' => 'required|string',
            'category' => ['required', Rule::enum(ArticlesCategoryEnum::class)],
            'status' => ['required', Rule::enum(ArticlesStatusEnum::class)],
            'is_public' => 'required|boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'remove_image' => 'nullable|boolean',
        ]);

        // Gestion de la suppression de l'image existante
        if ($request->boolean('remove_image') && $article->image) {
            Storage::disk('public')->delete($article->image);
            $validated['image'] = null;
        }

        // Gestion de l'upload d'une nouvelle image
        if ($request->hasFile('image')) {
            // Supprimer l'ancienne image si elle existe
            if ($article->image) {
                Storage::disk('public')->delete($article->image);
            }
            $validated['image'] = $request->file('image')->store('articles', 'public');
        }

        // Gestion des actions rapides
        if ($request->has('quick_action')) {
            switch ($request->get('quick_action')) {
                case 'publish':
                    $validated['status'] = ArticlesStatusEnum::PUBLISHED;
                    break;
                case 'archive':
                    $validated['status'] = ArticlesStatusEnum::ARCHIVED;
                    break;
            }
        }

        $article->update($validated);

        // Redirection selon l'action demandée
        if ($request->get('action') === 'save_and_view') {
            return redirect()->route('admin.articles.show', $article)
                ->with('success', 'Article mis à jour avec succès !');
        }

        return redirect()->route('admin.articles.index')
            ->with('success', 'Article mis à jour avec succès !');
    }
}
