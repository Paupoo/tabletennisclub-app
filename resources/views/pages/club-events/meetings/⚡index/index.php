<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\MeetingFormatEnum;
use App\Domains\Shared\Enums\MeetingStatusEnum;
use App\Domains\Shared\Enums\MeetingTypeEnum;
use App\Domains\Shared\Enums\Permission;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasBulkActions;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
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

    public bool $confirmBulkCancelModal = false;

    public bool $confirmBulkDeleteModal = false;

    #[Url]
    public string $format = '';

    #[Url]
    public string $search = '';

    #[Url]
    public bool $showArchived = false;

    /** @var array{column: string, direction: string} */
    public array $sortBy = ['column' => 'scheduled_at', 'direction' => 'desc'];

    #[Url]
    public string $status = '';

    #[Url]
    public string $type = '';

    public function bulkArchive(): void
    {
        abort_unless($this->canManage, 403);

        $meetings = Meeting::whereIn('id', $this->selected)->get()
            ->filter(fn (Meeting $meeting): bool => $meeting->canBeArchived());

        $meetings->each(fn (Meeting $meeting) => $meeting->archive());

        $count = $meetings->count();
        $this->clearSelection();
        $this->success(trans_choice('{1} Meeting archived.|[2,*] :count meetings archived.', $count, ['count' => $count]));
    }

    public function bulkCancel(): void
    {
        $count = count($this->selected);
        Meeting::whereIn('id', $this->selected)->update(['status' => MeetingStatusEnum::CANCELLED]);
        $this->confirmBulkCancelModal = false;
        $this->clearSelection();
        $this->warning(trans_choice('{1} Meeting cancelled.|[2,*] :count meetings cancelled.', $count, ['count' => $count]));
    }

    public function bulkDelete(): void
    {
        abort_unless($this->canManage, 403);

        $meetings = Meeting::whereIn('id', $this->selected)->get()
            ->filter(fn (Meeting $meeting): bool => $meeting->canBeDeleted());

        $count = $meetings->count();
        $meetings->each(fn (Meeting $meeting) => $meeting->delete());

        $this->confirmBulkDeleteModal = false;
        $this->clearSelection();
        $this->warning(trans_choice('{1} Meeting deleted.|[2,*] :count meetings deleted.', $count, ['count' => $count]));
    }

    public function bulkUnarchive(): void
    {
        abort_unless($this->canManage, 403);

        $meetings = Meeting::whereIn('id', $this->selected)->get()
            ->filter(fn (Meeting $meeting): bool => $meeting->isArchived());

        $meetings->each(fn (Meeting $meeting) => $meeting->unarchive());

        $count = $meetings->count();
        $this->clearSelection();
        $this->success(trans_choice('{1} Meeting restored.|[2,*] :count meetings restored.', $count, ['count' => $count]));
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    #[Computed]
    public function canManage(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can(Permission::MeetingsManage->value);
    }

    public function clearFilters(): void
    {
        $this->type = '';
        $this->status = '';
        $this->format = '';
        $this->resetPage();
    }

    // ── Bulk actions ──────────────────────────────────────────────────────────

    public function confirmBulkCancel(): void
    {
        $this->confirmBulkCancelModal = true;
    }

    public function confirmBulkDelete(): void
    {
        $this->confirmBulkDeleteModal = true;
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

        if (filled($this->type)) {
            $label = collect(MeetingTypeEnum::cases())
                ->first(fn ($e): bool => $e->value === $this->type)?->getLabel() ?? $this->type;
            $chips[] = ['key' => 'type', 'label' => __('Type') . ': ' . $label];
        }

        if (filled($this->status)) {
            $label = collect(MeetingStatusEnum::cases())
                ->first(fn ($e): bool => $e->value === $this->status)?->getLabel() ?? $this->status;
            $chips[] = ['key' => 'status', 'label' => __('Status') . ': ' . $label];
        }

        if (filled($this->format)) {
            $label = collect(MeetingFormatEnum::cases())
                ->first(fn ($e): bool => $e->value === $this->format)?->getLabel() ?? $this->format;
            $chips[] = ['key' => 'format', 'label' => __('Format') . ': ' . $label];
        }

        return $chips;
    }

    public function getTotalMatchingCount(): int
    {
        return $this->meetings->total();
    }

    #[Computed]
    public function meetings(): LengthAwarePaginator
    {
        return Meeting::withCount([
            'users AS confirmed_count' => fn ($q) => $q->whereIn('meeting_user.status', ['confirmed', 'attended']),
        ])
            ->when($this->search, fn ($q) => $q->where('title', 'like', "%{$this->search}%"))
            ->when($this->type, fn ($q) => $q->where('type', $this->type))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->format, fn ($q) => $q->where('format', $this->format))
            ->when(
                $this->showArchived,
                fn ($q) => $q->whereNotNull('archived_at'),
                fn ($q) => $q->whereNull('archived_at'),
            )
            ->orderBy($this->sortBy['column'] ?? 'scheduled_at', $this->sortBy['direction'] ?? 'desc')
            ->paginate(20);
    }

    public function render(): View
    {
        return $this->view();
    }

    public function updatedFormat(): void
    {
        $this->resetPage();
    }

    // ── Filter hooks ──────────────────────────────────────────────────────────

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedShowArchived(): void
    {
        $this->clearSelection();
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'meetings' => $this->meetings,
            'typeOptions' => MeetingTypeEnum::getOptions(),
            'statusOptions' => MeetingStatusEnum::getOptions(),
            'formatOptions' => MeetingFormatEnum::getOptions(),
            'headers' => [
                ['key' => 'title',        'label' => __('Title')],
                ['key' => 'type',         'label' => __('Type'),    'sortable' => false],
                ['key' => 'scheduled_at', 'label' => __('Date')],
                ['key' => 'format',       'label' => __('Format'),  'class' => 'hidden md:table-cell', 'sortable' => false],
                ['key' => 'participants', 'label' => __('RSVPs'),   'class' => 'hidden lg:table-cell', 'sortable' => false],
                ['key' => 'status',       'label' => __('Status'),  'sortable' => false],
            ],
            'filterChips' => $this->filterChips,
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Meetings'));
    }

    // ── HasBulkActions ────────────────────────────────────────────────────────

    /** @return array<int, string> */
    protected function getPageIds(): array
    {
        return $this->meetings
            ->pluck('id')
            ->map(fn (int $id): string => (string) $id)
            ->toArray();
    }
};
