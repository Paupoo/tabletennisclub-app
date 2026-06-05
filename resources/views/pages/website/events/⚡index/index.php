<?php

declare(strict_types=1);

use App\Domains\Shared\Enums\ClubEventTypeEnum;
use App\Domains\Shared\Enums\EventPostStatusEnum;
use App\Domains\ClubPosts\Models\EventPost;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasBulkActions;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast, WithPagination, HasBreadcrumbs;
    use HasBulkActions, HasFilterDrawer;

    // ── Filters ──────────────────────────────────────────────────────────────

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $type = '';

    /** @var array{column: string, direction: string} */
    public array $sortBy = ['column' => 'event_date', 'direction' => 'desc'];

    // ── Edit drawer ───────────────────────────────────────────────────────────

    public bool $editDrawer = false;

    public ?int $selectedEventId = null;

    public string $editStatus = '';

    public bool $editFeatured = false;

    public string $editTitle = '';

    public string $editDescription = '';

    public string $editLocation = '';

    public string $editEventDate = '';

    public string $editStartTime = '';

    public string $editEndTime = '';

    public string $editPrice = '';

    public string $editIcon = '';

    public string $editNotes = '';

    public string $editFeaturedUntil = '';

    // ── Deletion ──────────────────────────────────────────────────────────────

    public bool $deleteModal = false;

    public ?int $deletingId = null;

    public bool $confirmBulkArchiveModal = false;

    // ── HasBulkActions ────────────────────────────────────────────────────────

    /** @return array<int, string> */
    protected function getPageIds(): array
    {
        return $this->events
            ->pluck('id')
            ->map(fn (int $id) => (string) $id)
            ->toArray();
    }

    public function getTotalMatchingCount(): int
    {
        return $this->events->total();
    }

    // ── HasFilterDrawer ───────────────────────────────────────────────────────

    /** @return array<int, array{key: string, label: string}> */
    public function getFilterChips(): array
    {
        $chips = [];

        if (filled($this->status)) {
            $label = collect(EventPostStatusEnum::cases())
                ->first(fn ($s) => $s->value === $this->status)?->getLabel() ?? $this->status;
            $chips[] = ['key' => 'status', 'label' => __('Status') . ': ' . $label];
        }

        if (filled($this->type)) {
            $enum  = collect(ClubEventTypeEnum::cases())->first(fn ($e) => $e->value === $this->type);
            $label = $enum ? $enum->getIcon() . ' ' . $enum->getLabel() : $this->type;
            $chips[] = ['key' => 'type', 'label' => __('Type') . ': ' . $label];
        }

        return $chips;
    }

    public function clearFilters(): void
    {
        $this->status = '';
        $this->type   = '';
        $this->resetPage();
    }

    // ── Bulk actions ──────────────────────────────────────────────────────────

    public function bulkPublish(): void
    {
        $count = count($this->selected);
        EventPost::whereIn('id', $this->selected)->update(['status' => EventPostStatusEnum::PUBLISHED]);
        $this->clearSelection();
        $this->success(trans_choice('{1} Event published.|[2,*] :count events published.', $count, ['count' => $count]));
    }

    public function confirmBulkArchive(): void
    {
        $this->confirmBulkArchiveModal = true;
    }

    public function bulkArchive(): void
    {
        $count = count($this->selected);
        EventPost::whereIn('id', $this->selected)->update(['status' => EventPostStatusEnum::ARCHIVED]);
        $this->confirmBulkArchiveModal = false;
        $this->clearSelection();
        $this->warning(trans_choice('{1} Event archived.|[2,*] :count events archived.', $count, ['count' => $count]));
    }

    // ── Filter hooks ──────────────────────────────────────────────────────────

    public function updatedSearch(): void { $this->resetPage(); }

    public function updatedStatus(): void { $this->resetPage(); }

    public function updatedType(): void   { $this->resetPage(); }

    // ── Single-record actions ─────────────────────────────────────────────────

    public function openEdit(int $id): void
    {
        $event = EventPost::findOrFail($id);

        $this->selectedEventId   = $event->id;
        $this->editStatus        = $event->status->value;
        $this->editFeatured      = $event->featured;
        $this->editTitle         = $event->title;
        $this->editDescription   = $event->description ?? '';
        $this->editLocation      = $event->location ?? '';
        $this->editEventDate     = $event->event_date?->format('Y-m-d') ?? '';
        $this->editStartTime     = $event->start_time?->format('H:i') ?? '';
        $this->editEndTime       = $event->end_time?->format('H:i') ?? '';
        $this->editPrice         = $event->price ?? '';
        $this->editIcon          = $event->icon ?? '';
        $this->editNotes         = $event->notes ?? '';
        $this->editFeaturedUntil = $event->featured_until?->format('Y-m-d') ?? '';

        $this->editDrawer = true;
    }

    public function saveEdit(): void
    {
        $this->validate([
            'editTitle'         => ['required', 'string', 'max:255'],
            'editDescription'   => ['required', 'string'],
            'editLocation'      => ['required', 'string', 'max:255'],
            'editStatus'        => ['required', 'in:DRAFT,PUBLISHED,ARCHIVED'],
            'editEventDate'     => ['required', 'date'],
            'editStartTime'     => ['required', 'date_format:H:i'],
            'editEndTime'       => ['nullable', 'date_format:H:i'],
            'editPrice'         => ['nullable', 'string', 'max:255'],
            'editIcon'          => ['nullable', 'string', 'max:10'],
            'editNotes'         => ['nullable', 'string'],
            'editFeaturedUntil' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        EventPost::findOrFail($this->selectedEventId)->update([
            'title'          => $this->editTitle,
            'description'    => $this->editDescription,
            'location'       => $this->editLocation,
            'status'         => $this->editStatus,
            'featured'       => $this->editFeatured,
            'event_date'     => $this->editEventDate,
            'start_time'     => $this->editStartTime,
            'end_time'       => $this->editEndTime ?: null,
            'price'          => $this->editPrice ?: null,
            'icon'           => $this->editIcon ?: null,
            'notes'          => $this->editNotes ?: null,
            'featured_until' => $this->editFeaturedUntil ?: null,
        ]);

        $this->editDrawer      = false;
        $this->selectedEventId = null;

        $this->success(__('Event updated.'));
    }

    public function publish(int $id): void
    {
        EventPost::findOrFail($id)->update(['status' => EventPostStatusEnum::PUBLISHED]);
        $this->success(__('Event published.'));
    }

    public function archive(int $id): void
    {
        EventPost::findOrFail($id)->update(['status' => EventPostStatusEnum::ARCHIVED]);
        $this->warning(__('Event archived.'));
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId  = $id;
        $this->deleteModal = true;
    }

    public function delete(): void
    {
        $event = EventPost::findOrFail($this->deletingId);

        if (! $event->canBeDeleted()) {
            $this->error(__('Only draft or archived events can be deleted.'));
            $this->deleteModal = false;

            return;
        }

        $event->delete();

        $this->deleteModal = false;
        $this->deletingId  = null;

        $this->error(__('Event deleted.'));
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    #[Computed]
    public function events(): LengthAwarePaginator
    {
        return EventPost::with('eventable')
            ->when($this->search, fn ($q) => $q->where('title', 'like', "%{$this->search}%"))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->type, fn ($q) => $q->where('type', $this->type))
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate(20);
    }

    /** @return array<int, array{key: string, label: string}> */
    #[Computed]
    public function filterChips(): array
    {
        return $this->getFilterChips();
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Events'));
    }

    public function render(): View
    {
        return $this->view();
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $statsBase = EventPost::query();

        $stats = [
            'total'     => (clone $statsBase)->count(),
            'published' => (clone $statsBase)->where('status', EventPostStatusEnum::PUBLISHED->value)->count(),
            'draft'     => (clone $statsBase)->where('status', EventPostStatusEnum::DRAFT->value)->count(),
            'archived'  => (clone $statsBase)->where('status', EventPostStatusEnum::ARCHIVED->value)->count(),
        ];

        $statusOptions = collect(EventPostStatusEnum::cases())
            ->map(fn ($e) => ['id' => $e->value, 'name' => $e->getLabel()])
            ->all();

        $typeOptions = collect(ClubEventTypeEnum::cases())
            ->map(fn ($e) => ['id' => $e->value, 'name' => $e->getIcon() . ' ' . $e->getLabel()])
            ->all();

        $selectedEvent = $this->selectedEventId
            ? EventPost::with('eventable')->find($this->selectedEventId)
            : null;

        $headers = [
            ['key' => 'type',       'label' => __('Type'),      'sortable' => false],
            ['key' => 'title',      'label' => __('Title')],
            ['key' => 'event_date', 'label' => __('Date')],
            ['key' => 'eventable',  'label' => __('Linked to'), 'sortable' => false],
            ['key' => 'status',     'label' => __('Status'),    'sortable' => false],
            ['key' => 'featured',   'label' => __('Featured'),  'sortable' => false, 'class' => 'hidden lg:table-cell'],
        ];

        return [
            'breadcrumbs'   => $this->getBreadcrumbs(),
            'events'        => $this->events,
            'stats'         => $stats,
            'statusOptions' => $statusOptions,
            'typeOptions'   => $typeOptions,
            'selectedEvent' => $selectedEvent,
            'headers'       => $headers,
            'filterChips'   => $this->filterChips,
        ];
    }
};
