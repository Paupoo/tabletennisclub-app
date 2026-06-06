<?php

declare(strict_types=1);

use App\Domains\Shared\Enums\EventPostStatusEnum;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
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
    use Toast, WithPagination, HasBreadcrumbs;
    use HasBulkActions, HasFilterDrawer;

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

    /** @var array{column: string, direction: string} */
    public array $sortBy = ['column' => 'start_date', 'direction' => 'desc'];

    public bool $confirmBulkCancelModal = false;

    // ── HasBulkActions ────────────────────────────────────────────────────────

    /** @return array<int, string> */
    protected function getPageIds(): array
    {
        return $this->tournaments
            ->pluck('id')
            ->map(fn (int $id) => (string) $id)
            ->toArray();
    }

    public function getTotalMatchingCount(): int
    {
        return $this->tournaments->total();
    }

    // ── HasFilterDrawer ───────────────────────────────────────────────────────

    /** @return array<int, array{key: string, label: string}> */
    public function getFilterChips(): array
    {
        $chips = [];

        if (filled($this->status)) {
            $label = match ($this->status) {
                'pending'   => __('Live'),
                'published' => __('Published'),
                'setup'     => __('Setup'),
                'locked'    => __('Locked'),
                'closed'    => __('Closed'),
                'cancelled' => __('Cancelled'),
                'draft'     => __('Draft'),
                default     => $this->status,
            };
            $chips[] = ['key' => 'status', 'label' => __('Status') . ': ' . $label];
        }

        if (filled($this->matchType)) {
            $chips[] = [
                'key'   => 'matchType',
                'label' => __('Type') . ': ' . ($this->matchType === 'single' ? __('Singles') : __('Doubles')),
            ];
        }

        if (filled($this->isFull)) {
            $chips[] = [
                'key'   => 'isFull',
                'label' => __('Spots') . ': ' . ($this->isFull === 'full' ? __('Full') : __('Available spots')),
            ];
        }

        if (filled($this->hasEvent)) {
            $chips[] = [
                'key'   => 'hasEvent',
                'label' => __('Website') . ': ' . ($this->hasEvent === 'yes' ? __('Published') : __('Not published')),
            ];
        }

        return $chips;
    }

    public function clearFilters(): void
    {
        $this->status    = '';
        $this->matchType = '';
        $this->isFull    = '';
        $this->hasEvent  = '';
        $this->resetPage();
    }

    // ── Bulk actions ──────────────────────────────────────────────────────────

    public function confirmBulkCancel(): void
    {
        $this->confirmBulkCancelModal = true;
    }

    public function bulkCancel(): void
    {
        $count = count($this->selected);
        Tournament::whereIn('id', $this->selected)->update(['status' => TournamentStatusEnum::CANCELLED]);
        $this->confirmBulkCancelModal = false;
        $this->clearSelection();
        $this->warning(trans_choice('{1} Tournament cancelled.|[2,*] :count tournaments cancelled.', $count, ['count' => $count]));
    }

    public function refreshTournaments(): void
    {
        unset($this->tournaments);
    }

    // ── Filter hooks ──────────────────────────────────────────────────────────

    public function updatedSearch(): void    { $this->resetPage(); }

    public function updatedStatus(): void    { $this->resetPage(); }

    public function updatedMatchType(): void { $this->resetPage(); }

    public function updatedIsFull(): void    { $this->resetPage(); }

    public function updatedHasEvent(): void  { $this->resetPage(); }

    // ── Computed ──────────────────────────────────────────────────────────────

    #[Computed]
    public function canManage(): bool
    {
        $user = Auth::user();

        return $user instanceof User && ($user->is_admin || $user->is_committee_member);
    }

    #[Computed]
    public function tournaments(): LengthAwarePaginator
    {
        return Tournament::withCount([
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
            ->current(__('Tournaments'));
    }

    public function render(): View
    {
        return $this->view();
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
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
            'breadcrumbs'      => $this->getBreadcrumbs(),
            'tournaments'      => $this->tournaments,
            'stats'            => $stats,
            'statusOptions'    => $statusOptions,
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
            'filterChips' => $this->filterChips,
        ];
    }
};
