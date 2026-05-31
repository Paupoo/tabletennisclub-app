<?php

declare(strict_types=1);

use App\Domains\Shared\Enums\EventPostStatusEnum;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Models\ClubAdmin\Users\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast, WithPagination, HasBreadcrumbs;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $matchType = '';

    #[Url]
    public string $isFull = '';

    #[Url]
    public string $hasEvent = '';

    public bool $showFilters = false;

    public array $sortBy = ['column' => 'start_date', 'direction' => 'desc'];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedMatchType(): void
    {
        $this->resetPage();
    }

    public function updatedIsFull(): void
    {
        $this->resetPage();
    }

    public function updatedHasEvent(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'status', 'matchType', 'isFull', 'hasEvent']);
        $this->resetPage();
    }

    #[Computed]
    public function activeFiltersCount(): int
    {
        return collect([$this->status, $this->matchType, $this->isFull, $this->hasEvent])
            ->filter(fn ($v) => filled($v))
            ->count();
    }

    #[Computed]
    public function canManage(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ($user->is_admin || $user->is_committee_member);
    }


    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__("Tournaments"));
    }

        public function render(): View
    {
        return $this->view();
    }

    public function with(): array
    {
        $tournaments = Tournament::withCount([
            'users AS active_registrations_count' => fn ($q) => $q->whereIn('tournament_user.registration_status', ['registered', 'confirmed', 'spot_offered']),
            'users AS waiting_count'              => fn ($q) => $q->where('tournament_user.registration_status', 'waiting'),
        ])
            ->with('eventPost')
            ->when(! $this->canManage, fn ($q) => $q->whereNotIn('status', [TournamentStatusEnum::DRAFT->value]))
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->matchType, fn ($q) => $q->where('match_type', $this->matchType))
            ->when($this->isFull === 'full', fn ($q) => $q->whereRaw(
                '(SELECT COUNT(*) FROM tournament_user WHERE tournament_id = tournaments.id AND registration_status IN (?, ?, ?)) >= tournaments.max_users AND tournaments.max_users > 0',
                ['registered', 'confirmed', 'spot_offered']
            ))
            ->when($this->isFull === 'not_full', fn ($q) => $q->whereRaw(
                '((SELECT COUNT(*) FROM tournament_user WHERE tournament_id = tournaments.id AND registration_status IN (?, ?, ?)) < tournaments.max_users OR tournaments.max_users = 0)',
                ['registered', 'confirmed', 'spot_offered']
            ))
            ->when($this->hasEvent === 'yes', fn ($q) => $q->whereHas('eventPost', fn ($eq) => $eq->where('status', EventPostStatusEnum::PUBLISHED)))
            ->when($this->hasEvent === 'no', fn ($q) => $q->whereDoesntHave('eventPost', fn ($eq) => $eq->where('status', EventPostStatusEnum::PUBLISHED)))
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate(20);

        $statsBase = $this->canManage
            ? Tournament::query()
            : Tournament::whereNotIn('status', [TournamentStatusEnum::DRAFT->value]);

        $stats = [
            'total'    => (clone $statsBase)->count(),
            'live'     => (clone $statsBase)->where('status', TournamentStatusEnum::PENDING->value)->count(),
            'upcoming' => (clone $statsBase)->whereIn('status', ['published', 'locked', 'setup'])->count(),
            'closed'   => (clone $statsBase)->whereIn('status', ['closed', 'cancelled'])->count(),
        ];

        $statusOptions = [
            ['id' => TournamentStatusEnum::PENDING->value,   'name' => __('Live')],
            ['id' => TournamentStatusEnum::PUBLISHED->value, 'name' => __('Published')],
            ['id' => TournamentStatusEnum::SETUP->value,     'name' => __('Setup')],
            ['id' => TournamentStatusEnum::LOCKED->value,    'name' => __('Locked')],
            ['id' => TournamentStatusEnum::CLOSED->value,    'name' => __('Closed')],
            ['id' => TournamentStatusEnum::CANCELLED->value, 'name' => __('Cancelled')],
        ];

        if ($this->canManage) {
            $statusOptions[] = ['id' => TournamentStatusEnum::DRAFT->value, 'name' => __('Draft')];
        }

        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'tournaments'     => $tournaments,
            'stats'           => $stats,
            'statusOptions'   => $statusOptions,
            'matchTypeOptions' => [
                ['id' => 'single', 'name' => __('Singles')],
                ['id' => 'double', 'name' => __('Doubles')],
            ],
            'isFullOptions' => [
                ['id' => 'full',     'name' => __('Full')],
                ['id' => 'not_full', 'name' => __('Available spots')],
            ],
            'hasEventOptions' => [
                ['id' => 'yes', 'name' => __('Published on site')],
                ['id' => 'no',  'name' => __('Not published')],
            ],
            'headers' => [
                ['key' => 'name',       'label' => __('Name')],
                ['key' => 'start_date', 'label' => __('Date')],
                ['key' => 'match_type', 'label' => __('Type'),         'class' => 'hidden sm:table-cell', 'sortable' => false],
                ['key' => 'spots',      'label' => __('Participants'), 'class' => 'hidden md:table-cell', 'sortable' => false],
                ['key' => 'status',     'label' => __('Status'),       'sortable' => false],
                ['key' => 'event',      'label' => __('Website'),      'class' => 'hidden lg:table-cell', 'sortable' => false],
            ],
        ];
    }
};
