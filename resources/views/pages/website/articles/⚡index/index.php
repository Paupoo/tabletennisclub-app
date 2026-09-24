<?php

declare(strict_types=1);

namespace Resources\views\Pages\Website\Articles\Index;

use App\Domains\ClubPosts\Models\NewsPost;
use App\Domains\Shared\Enums\NewsPostCategoryEnum;
use App\Domains\Shared\Enums\NewsPostStatusEnum;
use App\Domains\Shared\Enums\Permission;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasBulkActions;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Markdown;
use App\Support\Breadcrumb;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs, Toast, WithPagination;
    use HasBulkActions, HasFilterDrawer;

    #[Url]
    public string $category = '';

    public bool $confirmBulkArchiveModal = false;

    public bool $deleteModal = false;

    public ?int $deletingId = null;

    public ?int $previewingId = null;

    public bool $previewModal = false;

    #[Url]
    public string $search = '';

    /** @var array{column: string, direction: string} */
    public array $sortBy = ['column' => 'created_at', 'direction' => 'desc'];

    #[Url]
    public string $status = '';

    public function archive(int $id): void
    {
        Gate::authorize(Permission::NewsPostsManage->value);

        NewsPost::findOrFail($id)->update(['status' => NewsPostStatusEnum::ARCHIVED]);
        $this->warning(__('Article archived.'));
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    #[Computed]
    public function articles(): LengthAwarePaginator
    {
        return NewsPost::with('user')
            ->when($this->search, fn ($q) => $q->search($this->search))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->category, fn ($q) => $q->where('category', $this->category))
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate(15);
    }

    public function bulkArchive(): void
    {
        Gate::authorize(Permission::NewsPostsManage->value);

        $count = count($this->selected);
        NewsPost::whereIn('id', $this->selected)->update(['status' => NewsPostStatusEnum::ARCHIVED]);
        $this->confirmBulkArchiveModal = false;
        $this->clearSelection();
        $this->warning(trans_choice('{1} Article archived.|[2,*] :count articles archived.', $count, ['count' => $count]));
    }

    // ── Bulk actions ──────────────────────────────────────────────────────────

    public function bulkPublish(): void
    {
        Gate::authorize(Permission::NewsPostsManage->value);

        $count = count($this->selected);
        NewsPost::whereIn('id', $this->selected)->update(['status' => NewsPostStatusEnum::PUBLISHED]);
        $this->clearSelection();
        $this->success(trans_choice('{1} Article published.|[2,*] :count articles published.', $count, ['count' => $count]));
    }

    public function clearFilters(): void
    {
        $this->status = '';
        $this->category = '';
        $this->resetPage();
    }

    public function confirmBulkArchive(): void
    {
        Gate::authorize(Permission::NewsPostsManage->value);

        $this->confirmBulkArchiveModal = true;
    }

    public function confirmDelete(int $id): void
    {
        Gate::authorize(Permission::NewsPostsManage->value);

        $this->deletingId = $id;
        $this->deleteModal = true;
    }

    /**
     * Whether the visitor edits the articles, or only reads them: the committee
     * reads every article at the baseline, drafts included.
     */
    #[Computed]
    public function mayManage(): bool
    {
        return Gate::allows(Permission::NewsPostsManage->value);
    }

    /**
     * A draft has no public page yet, and the editor is the website délégation's:
     * a reader reads it here.
     */
    public function openPreview(int $id): void
    {
        Gate::authorize(Permission::NewsPostsView->value);

        $this->previewingId = NewsPost::findOrFail($id)->id;
        $this->previewModal = true;
    }

    /**
     * The article open in the preview, rendered the way the public page does.
     *
     * @return array{title: string, html: string}|null
     */
    #[Computed]
    public function preview(): ?array
    {
        $article = $this->previewingId ? NewsPost::find($this->previewingId) : null;

        return $article === null ? null : [
            'title' => $article->title,
            'html' => Markdown::safe($article->content ?? ''),
        ];
    }

    public function delete(): void
    {
        Gate::authorize(Permission::NewsPostsManage->value);

        NewsPost::findOrFail($this->deletingId)->delete();
        $this->deleteModal = false;
        $this->deletingId = null;
        $this->error(__('Article deleted.'));
    }

    /** @return array<int, array{key: string, label: string}> */
    #[Computed]
    public function filterChips(): array
    {
        return $this->getFilterChips();
    }

    // ── HasFilterDrawer ───────────────────────────────────────────────────────

    /** @return array<int, array{key: string, label: string}> */
    public function getFilterChips(): array
    {
        $chips = [];

        if (filled($this->status)) {
            $label = collect(NewsPostStatusEnum::cases())
                ->first(fn ($s): bool => $s->value === $this->status)?->getLabel() ?? $this->status;
            $chips[] = ['key' => 'status', 'label' => __('Status') . ': ' . $label];
        }

        if (filled($this->category)) {
            $label = collect(NewsPostCategoryEnum::cases())
                ->first(fn ($c): bool => $c->value === $this->category)?->getLabel() ?? $this->category;
            $chips[] = ['key' => 'category', 'label' => __('Category') . ': ' . $label];
        }

        return $chips;
    }

    public function getTotalMatchingCount(): int
    {
        return $this->articles->total();
    }

    // ── Single-record actions ─────────────────────────────────────────────────

    public function publish(int $id): void
    {
        Gate::authorize(Permission::NewsPostsManage->value);

        NewsPost::findOrFail($id)->update(['status' => NewsPostStatusEnum::PUBLISHED]);
        $this->success(__('Article published.'));
    }

    public function render(): View
    {
        return $this->view();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    // ── Filter hooks ──────────────────────────────────────────────────────────

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $stats = NewsPost::selectRaw("
            COUNT(*) as total,
            SUM(status = 'published') as published,
            SUM(status = 'draft') as draft,
            SUM(status = 'archived') as archived
        ")->first();

        $statusOptions = collect(NewsPostStatusEnum::cases())
            ->map(fn ($s): array => ['id' => $s->value, 'name' => $s->getLabel()]);

        $categoryOptions = collect(NewsPostCategoryEnum::cases())
            ->map(fn ($c): array => ['id' => $c->value, 'name' => $c->getLabel()]);

        $headers = [
            ['key' => 'title',        'label' => __('Title'),    'sortable' => false],
            ['key' => 'category_label', 'label' => __('Category'), 'class' => 'hidden md:table-cell', 'sortable' => false],
            ['key' => 'author_name',  'label' => __('Author'),   'class' => 'hidden lg:table-cell', 'sortable' => false],
            ['key' => 'status',       'label' => __('Status'),   'sortable' => false],
            ['key' => 'created_at',   'label' => __('Date'),     'class' => 'hidden sm:table-cell'],
        ];

        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'articles' => $this->articles,
            'stats' => $stats,
            'statusOptions' => $statusOptions,
            'categoryOptions' => $categoryOptions,
            'headers' => $headers,
            'filterChips' => $this->filterChips,
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Articles'));
    }

    // ── HasBulkActions ────────────────────────────────────────────────────────

    /** @return array<int, string> */
    protected function getPageIds(): array
    {
        return $this->articles
            ->pluck('id')
            ->map(fn (int $id): string => (string) $id)
            ->toArray();
    }
};
