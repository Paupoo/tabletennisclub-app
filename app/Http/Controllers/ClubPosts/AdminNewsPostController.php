<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubPosts;

use App\Enums\NewsPostCategoryEnum;
use App\Enums\NewsPostStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\ClubPosts\NewsPost;
use App\Support\Breadcrumb;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminNewsPostController extends Controller
{
    /**
     * Archive a newsPost
     */
    public function archive(NewsPost $article)
    {
        $article->update([
            'status' => NewsPostStatusEnum::ARCHIVED,
        ]);

        return redirect()->back()
            ->with('success', __('NewsPost put in archives'));
    }

    /**
     * API endpoint for autosave (AJAX).
     */
    public function autoSave(Request $request, NewsPost $article)
    {
        // Basic validation for autosave.
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'slug' => 'sometimes|string|max:255',
        ]);

        // Ensure the user can modify this article
        if ($article->user_id !== auth()->id() && ! auth()->user()->hasRole('clubAdmin')) {
            return response()->json(['error' => 'Non autorisé'], 403);
        }

        // Update only the provided fields
        $article->update(array_filter($validated));

        return response()->json([
            'success' => true,
            'message' => __('Auto-save executed'),
            'updated_at' => $article->fresh()->updated_at->format('d/m/Y H:i:s'),
        ]);
    }

    /**
     * Show the form to create a new article
     */
    public function create()
    {
        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->articles()
            ->current(__('Create'))
            ->toArray();

        return view('clubPosts.newsPosts.create', compact('breadcrumbs'));
    }

    /**
     * Delete an article (soft delete)
     */
    public function destroy(NewsPost $article)
    {
        // Delete the associated image if it exists
        if ($article->image) {
            Storage::disk('public')->delete($article->image);
        }

        $article->delete();

        return redirect()->route('clubPosts.newsPosts.index')
            ->with('success', __('NewsPost deleted successfully'));
    }

    /**
     * Duplicate an existing article
     */
    public function duplicate(NewsPost $article)
    {
        $newArticle = $article->replicate();

        // Modify fields to avoid conflicts
        $newArticle->title = $article->title . ' - Copie';
        $newArticle->slug = $article->slug . '-copie-' . time();
        $newArticle->status = NewsPostStatusEnum::DRAFT;
        $newArticle->user_id = auth()->id();

        // Duplicate the image if it exists
        if ($article->image) {
            $extension = pathinfo($article->image, PATHINFO_EXTENSION);
            $filename = pathinfo($article->image, PATHINFO_FILENAME);
            $newImagePath = 'clubPosts/' . $filename . '-copie-' . time() . '.' . $extension;

            if (Storage::disk('public')->exists($article->image)) {
                Storage::disk('public')->copy($article->image, $newImagePath);
                $newArticle->image = $newImagePath;
            }
        }

        $newArticle->save();

        return redirect()->route('clubPosts.newsPosts.edit', $newArticle)
            ->with('success', __('NewsPost duplicated successfully, you may proceed to edit it'));
    }

    /**
     * Show the form to edit an article
     */
    public function edit(NewsPost $article)
    {
        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->articles()
            ->add($article->title, route('clubPosts.newsPosts.edit', $article))
            ->current(__('Edit'))
            ->toArray();

        return view('clubPosts.newsPosts.edit', compact('article', 'breadcrumbs'));
    }

    /**
     * Display the list of clubPosts with filters and pagination
     */
    public function index(Request $request)
    {
        $query = NewsPost::with('user');

        // Search filters
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

        // Default sorting: newest first
        $articles = $query->latest()->paginate($request->get('perPage', 25));

        // Statistics for the dashboard
        $stats = collect([
            'totalPublished' => NewsPost::where('status', NewsPostStatusEnum::PUBLISHED)->count(),
            'totalDraft' => NewsPost::where('status', NewsPostStatusEnum::DRAFT)->count(),
            'totalPublic' => NewsPost::where('is_public', true)->count(),
            'totalPrivate' => NewsPost::where('is_public', false)->count(),
        ]);

        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->articles()
            ->toArray();

        return view('clubPosts.newsPosts.index', compact('articles', 'stats', 'breadcrumbs'));
    }

    /**
     * Publish an article (set status to published)
     */
    public function publish(NewsPost $article)
    {
        $article->update([
            'status' => NewsPostStatusEnum::PUBLISHED,
        ]);

        return redirect()->back()
            ->with('success', __('NewsPost published successfully'));
    }

    /**
     * Restore a deleted article (soft deleted)
     */
    public function restore($id)
    {
        $article = NewsPost::withTrashed()->findOrFail($id);
        $article->restore();

        return redirect()->route('clubPosts.newsPosts.index')
            ->with('success', __('NewsPost restored successfully'));
    }

    /**
     * Display the details of an article
     */
    public function show(NewsPost $article)
    {
        $article->load('user');

        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->articles()
            ->add($article->title)
            ->toArray();

        return view('clubPosts.newsPosts.show', compact('article', 'breadcrumbs'));
    }

    /**
     * Detailed statistics for the dashboard
     */
    public function statistics()
    {
        $stats = [
            'total' => NewsPost::count(),
            'published' => NewsPost::where('status', NewsPostStatusEnum::PUBLISHED)->count(),
            'draft' => NewsPost::where('status', NewsPostStatusEnum::DRAFT)->count(),
            'archived' => NewsPost::where('status', NewsPostStatusEnum::ARCHIVED)->count(),
            'public' => NewsPost::where('is_public', true)->count(),
            'private' => NewsPost::where('is_public', false)->count(),
            'by_category' => NewsPost::selectRaw('category, COUNT(*) as count')
                ->groupBy('category')
                ->pluck('count', 'category')
                ->toArray(),
            'by_author' => NewsPost::with('user')
                ->selectRaw('user_id, COUNT(*) as count')
                ->groupBy('user_id')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->user->full_name => $item->count];
                })->toArray(),
            'recent_activity' => NewsPost::with('user')
                ->latest()
                ->limit(5)
                ->get(),
        ];

        return response()->json($stats);
    }

    /**
     * Store a new article
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:clubPosts,slug',
            'content' => 'required|string',
            'category' => ['required', Rule::enum(NewsPostCategoryEnum::class)],
            'status' => ['required', Rule::enum(NewsPostStatusEnum::class)],
            'is_public' => 'required|boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Generate slug automatically if needed
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']);
        }

        // Ensure slug uniqueness
        $originalSlug = $validated['slug'];
        $counter = 1;
        while (NewsPost::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $originalSlug . '-' . $counter;
            $counter++;
        }

        // Manage image upload
        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('clubPosts', 'public');
        }

        // Attach the authenticated user
        $validated['user_id'] = auth()->id();

        $article = NewsPost::create($validated);

        // Redirect depending on the requested action
        if ($request->get('action') === 'save_and_continue') {
            return redirect()->route('clubPosts.newsPosts.edit', $article)
                ->with('success', __('NewsPost created successfully, you may proceed to edit it'));
        }

        return redirect()->route('clubPosts.newsPosts.index')
            ->with('success', __('NewsPost created successfully'));
    }

    /**
     * Update an existing article
     */
    public function update(Request $request, NewsPost $article)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:255', Rule::unique('clubPosts', 'slug')->ignore($article->id)],
            'content' => 'required|string',
            'category' => ['required', Rule::enum(NewsPostCategoryEnum::class)],
            'status' => ['required', Rule::enum(NewsPostStatusEnum::class)],
            'is_public' => 'required|boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'remove_image' => 'nullable|boolean',
        ]);

        // Handle removal of the existing image
        if ($request->boolean('remove_image') && $article->image) {
            Storage::disk('public')->delete($article->image);
            $validated['image'] = null;
        }

        // Handle upload of a new image
        if ($request->hasFile('image')) {
            // Supprimer l'ancienne image si elle existe
            if ($article->image) {
                Storage::disk('public')->delete($article->image);
            }
            $validated['image'] = $request->file('image')->store('clubPosts', 'public');
        }

        // Handle quick actions
        if ($request->has('quick_action')) {
            switch ($request->get('quick_action')) {
                case 'publish':
                    $validated['status'] = NewsPostStatusEnum::PUBLISHED;
                    break;
                case 'archive':
                    $validated['status'] = NewsPostStatusEnum::ARCHIVED;
                    break;
            }
        }

        $article->update($validated);

        // Redirection selon l'action demandée
        if ($request->get('action') === 'save_and_view') {
            return redirect()->route('clubPosts.newsPosts.show', $article)
                ->with('success', __('NewsPost updated successfully'));
        }

        return redirect()->route('clubPosts.newsPosts.index')
            ->with('success', __('NewsPost updated successfully'));
    }
}
