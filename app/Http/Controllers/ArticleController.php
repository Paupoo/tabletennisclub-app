<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreArticleRequest;
use App\Models\Article;
use App\Support\Breadcrumb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ArticleController extends Controller
{
    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $breadcrubs = Breadcrumb::make()
            ->home()
            ->articles()
            ->add('Create')
            ->toArray();

        $article = new Article;

        return view('admin.articles.create', compact('articles', 'breadcrumbs'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Article $article)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Article $article)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $articles = Article::orderByDesc('created_at')->paginate(20);

        $breadcrumbs = Breadcrumb::make()
            ->make()
            ->articles()
            ->toArray();

        $actions = '';

        return View('admin.articles.index', compact('articles', 'breadcrumbs', 'actions'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Article $article)
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreArticleRequest $request): RedirectResponse
    {

        Article::create($request->validated());

        return redirect()->route('admin.articles.show');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Article $article)
    {
        //
    }
}
