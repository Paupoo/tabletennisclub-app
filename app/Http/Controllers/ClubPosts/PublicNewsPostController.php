<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubPosts;

use App\Domains\ClubPosts\Models\NewsPost;
use App\Http\Controllers\Controller;
use App\Support\Markdown;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PublicNewsPostController extends Controller
{
    public function index(Request $request): View
    {
        return view('public.articles.index');
    }

    public function show(string $slug): View
    {
        $article = NewsPost::published()
            ->whereSlug($slug)
            ->with('user')
            ->firstOrFail();

        // Articles similaires (même catégorie)
        $relatedArticles = NewsPost::published()
            ->where('category', $article->category)
            ->where('slug', '!=', $slug)
            ->take(3)
            ->get();

        return view('public.articles.show', [
            'article' => $article,
            'renderedContent' => Markdown::safe($article->content ?? ''),
            'relatedArticles' => $relatedArticles,
        ]);
    }
}
