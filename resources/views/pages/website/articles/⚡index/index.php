<?php

declare(strict_types=1);

namespace Resources\views\Pages\Website\Articles\Index;

use App\Enums\NewsPostCategoryEnum;
use App\Enums\NewsPostStatusEnum;
use App\Models\ClubPosts\NewsPost;
use App\Support\Breadcrumb;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast, WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $category = '';

    public bool $deleteModal = false;
    public ?int $deletingId  = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    public function publish(int $id): void
    {
        NewsPost::findOrFail($id)->update(['status' => NewsPostStatusEnum::PUBLISHED]);
        $this->success('Article publié.');
    }

    public function archive(int $id): void
    {
        NewsPost::findOrFail($id)->update(['status' => NewsPostStatusEnum::ARCHIVED]);
        $this->warning('Article archivé.');
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId  = $id;
        $this->deleteModal = true;
    }

    public function delete(): void
    {
        NewsPost::findOrFail($this->deletingId)->delete();
        $this->deleteModal = false;
        $this->deletingId  = null;
        $this->error('Article supprimé.');
    }

    public function render(): View
    {
        return $this->view();
    }

    public function with(): array
    {
        $articles = NewsPost::with('user')
            ->when($this->search, fn ($q) => $q->search($this->search))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->category, fn ($q) => $q->where('category', $this->category))
            ->orderByDesc('created_at')
            ->paginate(15);

        $stats = NewsPost::selectRaw("
            COUNT(*) as total,
            SUM(status = 'published') as published,
            SUM(status = 'draft') as draft,
            SUM(status = 'archived') as archived
        ")->first();

        $statusOptions = collect(NewsPostStatusEnum::cases())
            ->map(fn ($s) => ['id' => $s->value, 'name' => $s->getLabel()]);

        $categoryOptions = collect(NewsPostCategoryEnum::cases())
            ->map(fn ($c) => ['id' => $c->value, 'name' => $c->getLabel()]);

        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->add('Website', '#')
                ->current('Articles')
                ->toArray(),
            'articles'         => $articles,
            'stats'            => $stats,
            'statusOptions'    => $statusOptions,
            'categoryOptions'  => $categoryOptions,
        ];
    }
};
