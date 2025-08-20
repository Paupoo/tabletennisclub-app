<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Articles;

use App\Models\Article;
use App\Support\Breadcrumb;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;
    public string $search = '';

    public int $perPage = 25;
    public string $status = '';
    public string $category = '';
    public string $visibility = '';
    public string $sortByField = '';
    public string $sortDirection = 'desc';
    public ?int $selectedArticleId = null;


    public function mount(): void {}

    public function render()
    {
        $articles = Article::search($this->search)
            ->when($this->visibility !== '', function(Builder $query): void {
                $this->visibility ? $query->isPublic() : $query->isPrivate();
            })
            ->when($this->status !== '', function (Builder $query): void {
                $query->where('status', $this->status); 
            })
            ->when($this->category !== '', function (Builder $query): void {
                $query->where('category', $this->category);
            })
            ->when($this->sortByField !== '', function (Builder $query): void {
                $query->orderBy($this->sortByField, $this->sortDirection);
            })
            ->paginate($this->perPage);

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

        return view('livewire.admin.articles.index', compact('articles', 'breadcrumbs', 'stats'));
    }

    public function sortBy(string $field): void {
        if($this->sortByField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        }

        $this->sortByField = $field;
    }

    public function destroy(Article $article) {
        
        $this->authorize('delete', Auth()->user());

        $article->delete();

        session()->flash('success', __('The article ' . $article->title . ' has been deleted.'));
        return $this->redirectRoute('admin.articles.index');
    }
}
