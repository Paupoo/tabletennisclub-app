<?php

declare(strict_types=1);

namespace Resources\views\Pages\Website\Articles\Index;

use App\Enums\NewsPostCategoryEnum;
use App\Enums\NewsPostStatusEnum;
use App\Models\ClubPosts\NewsPost;
use App\Support\Breadcrumb;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
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

    public bool $showFilters = false;

    public array $sortBy = ['column' => 'created_at', 'direction' => 'desc'];

    public bool $deleteModal = false;

    public ?int $deletingId = null;

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

    public function resetFilters(): void
    {
        $this->reset(['search', 'status', 'category']);
        $this->resetPage();
    }

    #[Computed]
    public function activeFiltersCount(): int
    {
        return collect([$this->status, $this->category])
            ->filter(fn ($v) => filled($v))
            ->count();
    }

    public function publish(int $id): void
    {
        NewsPost::findOrFail($id)->update(['status' => NewsPostStatusEnum::PUBLISHED]);
        $this->success(__('Article published.'));
    }

    public function archive(int $id): void
    {
        NewsPost::findOrFail($id)->update(['status' => NewsPostStatusEnum::ARCHIVED]);
        $this->warning(__('Article archived.'));
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
        $this->error(__('Article deleted.'));
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
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
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

        $headers = [
            ['key' => 'title', 'label' => __('Title'), 'sortable' => false],
            ['key' => 'category_label', 'label' => __('Category'), 'class' => 'hidden md:table-cell', 'sortable' => false],
            ['key' => 'author_name', 'label' => __('Author'), 'class' => 'hidden lg:table-cell', 'sortable' => false],
            ['key' => 'status', 'label' => __('Status'), 'sortable' => false],
            ['key' => 'created_at', 'label' => __('Date'), 'class' => 'hidden sm:table-cell'],
        ];

        return [
            'breadcrumbs'    => Breadcrumb::make()->home()->add('Website', '#')->current('Articles')->toArray(),
            'articles'       => $articles,
            'stats'          => $stats,
            'statusOptions'  => $statusOptions,
            'categoryOptions' => $categoryOptions,
            'headers'        => $headers,
        ];
    }
};
