<?php

declare(strict_types=1);

use App\Enums\MeetingFormatEnum;
use App\Enums\MeetingStatusEnum;
use App\Enums\MeetingTypeEnum;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Meeting\Meeting;
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
    public string $type = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $format = '';

    public bool $showFilters = false;

    public array $sortBy = ['column' => 'scheduled_at', 'direction' => 'desc'];

    public function updatedSearch(): void  { $this->resetPage(); }
    public function updatedType(): void    { $this->resetPage(); }
    public function updatedStatus(): void  { $this->resetPage(); }
    public function updatedFormat(): void  { $this->resetPage(); }

    public function resetFilters(): void
    {
        $this->reset(['search', 'type', 'status', 'format']);
        $this->resetPage();
    }

    #[Computed]
    public function canManage(): bool
    {
        $user = auth()->user();
        return $user instanceof User && ($user->is_admin || $user->is_committee_member);
    }

    #[Computed]
    public function activeFiltersCount(): int
    {
        return collect([$this->type, $this->status, $this->format])
            ->filter(fn ($v) => filled($v))
            ->count();
    }

    public function render(): View
    {
        return $this->view();
    }

    public function with(): array
    {
        $meetings = Meeting::withCount([
            'users AS confirmed_count' => fn ($q) => $q->whereIn('meeting_user.status', ['confirmed', 'attended']),
        ])
            ->when($this->search, fn ($q) => $q->where('title', 'like', "%{$this->search}%"))
            ->when($this->type,   fn ($q) => $q->where('type', $this->type))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->format, fn ($q) => $q->where('format', $this->format))
            ->orderBy($this->sortBy['column'] ?? 'scheduled_at', $this->sortBy['direction'] ?? 'desc')
            ->paginate(20);

        $stats = [
            'total'     => Meeting::count(),
            'upcoming'  => Meeting::where('status', MeetingStatusEnum::CONFIRMED->value)
                ->where('scheduled_at', '>', now())->count(),
            'planning'  => Meeting::where('status', MeetingStatusEnum::PLANNING->value)->count(),
            'completed' => Meeting::where('status', MeetingStatusEnum::COMPLETED->value)->count(),
        ];

        return [
            'breadcrumbs'   => Breadcrumb::make()->home()->meetings()->toArray(),
            'meetings'      => $meetings,
            'stats'         => $stats,
            'typeOptions'   => MeetingTypeEnum::getOptions(),
            'statusOptions' => MeetingStatusEnum::getOptions(),
            'formatOptions' => MeetingFormatEnum::getOptions(),
            'headers'       => [
                ['key' => 'title',        'label' => __('Title')],
                ['key' => 'type',         'label' => __('Type'),   'sortable' => false],
                ['key' => 'scheduled_at', 'label' => __('Date')],
                ['key' => 'format',       'label' => __('Format'), 'class' => 'hidden md:table-cell', 'sortable' => false],
                ['key' => 'participants', 'label' => __('RSVPs'),  'class' => 'hidden lg:table-cell', 'sortable' => false],
                ['key' => 'status',       'label' => __('Status'), 'sortable' => false],
            ],
        ];
    }
};
