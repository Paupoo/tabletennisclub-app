<?php

namespace App\Livewire\Admin\Articles;

use App\Models\Article;
use Livewire\Component;

class Create extends Component
{
    public Article $article;

    public function mount()
    {
        $this->article = new Article();
    }

    public function render()
    {
        return view('livewire.admin.articles.create',[
            'article' => $this->article,
        ]);
    }
}
