<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Data\Tournament\NextAction;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Services\TournamentNextActionService;
use App\Domains\Shared\Enums\EventPostStatusEnum;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Domains\Shared\States\Tournament\TournamentStateMachine;
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

    #[Url]
    public string $hasEvent = '';

    #[Url]
    public string $isFull = '';

    #[Url]
    public string $matchType = '';

    /**
     * Le filtre grossier de l'en-tête : en cours, à venir, terminés.
     *
     * Distinct de `status`, qui reste dans le tiroir pour viser un statut
     * précis. Les deux se composent -- « à venir » ET « inscriptions
     * ouvertes » est une question qu'on pose.
     */
    #[Url]
    public string $phase = '';

    #[Url]
    public string $search = '';

    /** @var array{column: string, direction: string} */
    public array $sortBy = ['column' => 'start_date', 'direction' => 'desc'];

    #[Url]
    public string $status = '';

    /**
     * Les statuts couverts par chaque phase de l'en-tête.
     *
     * @return array<string, array<int, string>>
     */
    public static function phaseStatuses(): array
    {
        return [
            'live' => [TournamentStatusEnum::PENDING->value],
            'upcoming' => [
                TournamentStatusEnum::PUBLISHED->value,
                TournamentStatusEnum::LOCKED->value,
                TournamentStatusEnum::SETUP->value,
            ],
            'done' => [
                TournamentStatusEnum::CLOSED->value,
                TournamentStatusEnum::CANCELLED->value,
            ],
        ];
    }

    public function bulkCancel(): void
    {
        $cancelled = 0;
        $refused = 0;

        foreach (Tournament::whereIn('id', $this->selected)->get() as $tournament) {
            try {
                (new TournamentStateMachine($tournament))->cancel();
                $cancelled++;
            } catch (InvalidArgumentException|LogicException) {
                // Played, closed or already cancelled: cancel what can be
                // cancelled and account for the rest rather than reporting a
                // clean sweep that did not happen.
                $refused++;
            }
        }

        $this->confirmBulkCancelModal = false;
        $this->clearSelection();

        if ($cancelled > 0) {
            $this->warning(trans_choice('{1} Tournament cancelled.|[2,*] :count tournaments cancelled.', $cancelled, ['count' => $cancelled]));
        }

        if ($refused > 0) {
            $this->error(trans_choice(
                '{1} One tournament could not be cancelled: it has already been played or closed.|[2,*] :count tournaments could not be cancelled: they have already been played or closed.',
                $refused,
                ['count' => $refused],
            ));
        }
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    #[Computed]
    public function canManage(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can(Permission::TournamentsManage->value);
    }

    public function clearFilters(): void
    {
        $this->phase = '';
        $this->status = '';
        $this->matchType = '';
        $this->isFull = '';
        $this->hasEvent = '';
        $this->resetPage();
    }

    // ── Bulk actions ──────────────────────────────────────────────────────────

    public function confirmBulkCancel(): void
    {
        $this->confirmBulkCancelModal = true;
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

        if (filled($this->phase)) {
            $chips[] = [
                'key' => 'phase',
                'label' => match ($this->phase) {
                    'live' => __('Live'),
                    'upcoming' => __('Upcoming'),
                    'done' => __('Closed'),
                    default => $this->phase,
                },
            ];
        }

        if (filled($this->status)) {
            $label = TournamentStatusEnum::tryFrom($this->status)?->getLabel() ?? $this->status;
            $chips[] = ['key' => 'status', 'label' => __('Status') . ': ' . $label];
        }

        if (filled($this->matchType)) {
            $chips[] = [
                'key' => 'matchType',
                'label' => __('Type') . ': ' . ($this->matchType === 'single' ? __('Singles') : __('Doubles')),
            ];
        }

        if (filled($this->isFull)) {
            $chips[] = [
                'key' => 'isFull',
                'label' => __('Spots') . ': ' . ($this->isFull === 'full' ? __('Full') : __('Available spots')),
            ];
        }

        if (filled($this->hasEvent)) {
            $chips[] = [
                'key' => 'hasEvent',
                'label' => __('Website') . ': ' . ($this->hasEvent === 'yes' ? __('Article published') : __('No article')),
            ];
        }

        return $chips;
    }

    public function getTotalMatchingCount(): int
    {
        return $this->tournaments->total();
    }

    /**
     * Ce que ce tournoi attend du comité, ou null s'il n'attend rien.
     *
     * La règle vit dans le service : la vue ne fait que l'afficher.
     */
    public function nextActionFor(Tournament $tournament): ?NextAction
    {
        return app(TournamentNextActionService::class)->for($tournament);
    }

    public function refreshTournaments(): void
    {
        unset($this->tournaments);
    }

    public function render(): View
    {
        return $this->view();
    }

    #[Computed]
    public function tournaments(): LengthAwarePaginator
    {
        return Tournament::withCount([
            'users AS active_registrations_count' => fn ($q) => $q->whereIn('tournament_user.registration_status', ['registered', 'confirmed', 'spot_offered']),
            'users AS waiting_count' => fn ($q) => $q->where('tournament_user.registration_status', 'waiting'),
        ])
            ->with('eventPost')
            ->when(! $this->canManage, fn ($q) => $q->whereNotIn('status', [TournamentStatusEnum::DRAFT->value]))
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when(
                self::phaseStatuses()[$this->phase] ?? null,
                fn ($q, array $statuses) => $q->whereIn('status', $statuses),
            )
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

    public function updatedHasEvent(): void
    {
        $this->resetPage();
    }

    public function updatedIsFull(): void
    {
        $this->resetPage();
    }

    public function updatedMatchType(): void
    {
        $this->resetPage();
    }

    // ── Filter hooks ──────────────────────────────────────────────────────────

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPhase(): void
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
        $statsBase = $this->canManage
            ? Tournament::query()
            : Tournament::whereNotIn('status', [TournamentStatusEnum::DRAFT->value]);

        $stats = [
            'total' => (clone $statsBase)->count(),
            'live' => (clone $statsBase)->where('status', TournamentStatusEnum::PENDING->value)->count(),
            'upcoming' => (clone $statsBase)->whereIn('status', ['published', 'locked', 'setup'])->count(),
            'closed' => (clone $statsBase)->whereIn('status', ['closed', 'cancelled'])->count(),
        ];

        $statusOptions = TournamentStatusEnum::toOptions(withDraft: $this->canManage);

        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'tournaments' => $this->tournaments,
            'stats' => $stats,
            'statusOptions' => $statusOptions,
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
                ['key' => 'name',        'label' => __('Tournament')],
                ['key' => 'start_date',  'label' => __('Date')],
                ['key' => 'match_type',  'label' => __('Type'),          'class' => 'hidden xl:table-cell', 'sortable' => false],
                ['key' => 'spots',       'label' => __('Registrations'), 'class' => 'hidden md:table-cell', 'sortable' => false],
                ['key' => 'status',      'label' => __('Status'),        'sortable' => false],
                ['key' => 'next_action', 'label' => __('Waiting on you'), 'sortable' => false],
            ],
            'filterChips' => $this->filterChips,
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Tournaments'));
    }

    // ── HasBulkActions ────────────────────────────────────────────────────────

    /** @return array<int, string> */
    protected function getPageIds(): array
    {
        return $this->tournaments
            ->pluck('id')
            ->map(fn (int $id): string => (string) $id)
            ->toArray();
    }
};
