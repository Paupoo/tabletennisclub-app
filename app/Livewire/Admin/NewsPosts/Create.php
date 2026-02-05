<?php

declare(strict_types=1);

namespace App\Livewire\Admin\NewsPosts;

use App\Models\ClubPosts\NewsPost;
use Livewire\Component;

use const App\Livewire\Admin\Articles\admin;
use const App\Livewire\Admin\Articles\articles;

class Create extends Component
{
    public NewsPost $article;

    public function mount()
    {
        $this->article = new NewsPost;
    }

    public function render()
    {
        return view('livewire.admin.articles.create', [
            'article' => $this->article,
        ]);
    }
}
