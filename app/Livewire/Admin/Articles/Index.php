<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Articles;

use App\Models\Article;
use Livewire\Component;

class Index extends Component
{
    public string $search = '';

    public function mount(): void {}

    public function render()
    {
        $articles = Article::search($this->search)->get();

        return view('livewire.admin.articles.index', [
            'articles' => $articles,
        ]);
    }
}
