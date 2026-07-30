<?php

declare(strict_types=1);

use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use App\Support\Help\HelpArticle;
use App\Support\Help\HelpAudience;
use App\Support\Help\HelpLibrary;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Help hub — one task per page, each written once and tagged with the roles it
 * concerns. The role is an entry filter, never a folder: a task shared by the
 * secretary and the treasurer lives in a single file.
 */
new #[Title('Help')] class extends Component
{
    use HasBreadcrumbs;

    #[Url]
    public string $search = '';

    public function with(): array
    {
        $articles = HelpLibrary::visibleTo(HelpAudience::for(Auth::user()));

        if ($this->search !== '') {
            $needle = Str::lower($this->search);

            $articles = array_values(array_filter(
                $articles,
                fn (HelpArticle $article): bool => Str::contains(
                    Str::lower($article->title . ' ' . $article->summary . ' ' . $article->markdown),
                    $needle,
                ),
            ));
        }

        return [
            'articles' => $articles,
            'breadcrumbs' => $this->getBreadcrumbs(),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Help'));
    }
};
