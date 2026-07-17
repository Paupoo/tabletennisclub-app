<?php

declare(strict_types=1);

use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use App\Support\Help\HelpArticle;
use App\Support\Help\HelpAudience;
use App\Support\Help\HelpLibrary;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    use HasBreadcrumbs;

    /** Only the slug is state: the article itself is read from disk on render. */
    public string $slug = '';

    #[Computed]
    public function article(): HelpArticle
    {
        $article = HelpLibrary::find($this->slug);

        abort_if($article === null, 404);
        abort_unless($article->isVisibleTo(HelpAudience::for(Auth::user())), 404);

        return $article;
    }

    public function mount(string $slug): void
    {
        $this->slug = $slug;

        // Resolve eagerly so an unknown or out-of-audience slug 404s on mount
        // rather than halfway through rendering the layout.
        $this->article();
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->add(__('Help'), route('admin.help.index'))
            ->current($this->article()->title);
    }
};
