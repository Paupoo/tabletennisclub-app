<?php

namespace App\Livewire\Admin\Articles;

use App\Models\Article;
use Illuminate\Support\Collection;
use Livewire\Component;

class Index extends Component
{
    public string $search = '';

    public function mount(): void
    {
    }

    public function render()
    {
        $articles = Article::search($this->search)->get();
        
        return view('livewire.admin.articles.index', [
            'articles' => $articles,
        ]);
    }
}
