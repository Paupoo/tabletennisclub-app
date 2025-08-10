<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Articles;

use App\Models\Article;
use App\Support\Breadcrumb;
use Livewire\Component;

class Index extends Component
{
    public string $search = '';

    public function mount(): void {}

    public function render()
    {
        $articles = Article::search($this->search)->get();

        $breadcrumbs = Breadcrumb::make()
            ->make()
            ->articles()
            ->toArray();

        $stats = collect([
            'totalPublished' => 0,
            'totalDraft' => 0,
            'totalPublic' => 0,
            'totalPrivate' => 0,
        ]);

        return view('livewire.admin.articles.index', compact('articles', 'breadcrumbs'));
    }
}
