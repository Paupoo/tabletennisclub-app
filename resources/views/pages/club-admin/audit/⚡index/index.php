<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use Spatie\Activitylog\Models\Activity;

new class extends Component
{
    use HasBreadcrumbs, HasFilterDrawer, Toast, WithPagination;

    public string $causerFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $eventFilter = '';

    public string $modelFilter = '';

    public string $search = '';

    public array $sortBy = ['column' => 'created_at', 'direction' => 'desc'];

    /**
     * Member ids matching a search term, memoised for the request.
     *
     * @var array<string, array<int, int>>
     */
    private array $searchMemberIdCache = [];

    /**
     * Subject types present in the log, memoised for the request.
     *
     * @var array<string, string>|null
     */
    private ?array $subjectLabelCache = null;

    public function clearFilters(): void
    {
        $this->reset(['modelFilter', 'causerFilter', 'eventFilter', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    /**
     * Human-readable label for an activity event/description.
     */
    public function eventLabel(string $event): string
    {
        return match ($event) {
            'created' => __('Created'),
            'updated' => __('Modified'),
            'deleted' => __('Deleted'),
            'training_pack_reconciled' => __('Training pack adjusted'),
            default => $event,
        };
    }

    public function getFilterChips(): array
    {
        $chips = [];

        if ($this->modelFilter) {
            $chips[] = ['key' => 'modelFilter', 'label' => $this->subjectLabel($this->modelFilter)];
        }

        if ($this->causerFilter) {
            $causer = User::find($this->causerFilter);
            $chips[] = ['key' => 'causerFilter', 'label' => $causer ? "{$causer->first_name} {$causer->last_name}" : __('Unknown')];
        }

        if ($this->eventFilter) {
            $chips[] = ['key' => 'eventFilter', 'label' => $this->eventLabel($this->eventFilter)];
        }

        if ($this->dateFrom) {
            $chips[] = ['key' => 'dateFrom', 'label' => __('From: :date', ['date' => $this->dateFrom])];
        }

        if ($this->dateTo) {
            $chips[] = ['key' => 'dateTo', 'label' => __('To: :date', ['date' => $this->dateTo])];
        }

        return $chips;
    }

    public function getTotalMatchingCount(): int
    {
        return $this->activities()->total();
    }

    public function headers(): array
    {
        return [
            ['key' => 'created_at', 'label' => __('Date & time'), 'sortable' => true],
            ['key' => 'causer',     'label' => __('Author'),      'sortable' => false],
            ['key' => 'event',      'label' => __('Action'),      'sortable' => false],
            ['key' => 'subject',    'label' => __('Item'),        'sortable' => false],
            ['key' => 'changes',    'label' => __('Details'),     'sortable' => false],
        ];
    }

    public function render(): View
    {
        return $this->view([
            'headers' => $this->headers(),
            'activities' => $this->activities(),
            'filterChips' => $this->getFilterChips(),
            'modelOptions' => $this->modelOptions(),
            'subjectLabels' => $this->subjectLabels(),
            'causerOptions' => $this->causerOptions(),
            'eventOptions' => $this->eventOptions(),
            'breadcrumbs' => $this->getBreadcrumbs(),
        ]);
    }

    /**
     * Human-readable label for an audited subject type.
     */
    public function subjectLabel(string $type): string
    {
        $base = class_basename($type);

        return match ($base) {
            'User' => __('Member'),
            'Guardian' => __('Guardian'),
            'Subscription' => __('Subscription'),
            'Registration' => __('Registration'),
            'Payment' => __('Payment'),
            'Transaction' => __('Transaction'),
            'CashRegister' => __('Cash register'),
            'CashRegisterEntry' => __('Cash register entry'),
            'BankImport' => __('Bank import'),
            'Contact' => __('Contact'),
            'EmailTemplate' => __('Email template'),
            'Spam' => __('Spam'),
            'Room' => __('Room'),
            'Table' => __('Table'),
            'Season' => __('Season'),
            'League' => __('League'),
            'Club' => __('Club'),
            'Team' => __('Team'),
            'Interclub' => __('Interclub'),
            'InterclubResult' => __('Interclub result'),
            'Tournament' => __('Tournament'),
            'TournamentMatch' => __('Tournament match'),
            'TournamentPair' => __('Tournament pair'),
            'TournamentRegistration' => __('Tournament registration'),
            'Pool' => __('Pool'),
            'TableTournament' => __('Tournament table'),
            'MatchSet' => __('Match set'),
            'Training' => __('Training'),
            'TrainingPack' => __('Training pack'),
            'Meeting' => __('Meeting'),
            'MeetingUser' => __('Meeting attendance'),
            'MeetingMinutes' => __('Meeting minutes'),
            'MeetingAgendaItem' => __('Agenda item'),
            'MeetingActionItem' => __('Action item'),
            'MeetingDateVote' => __('Date vote'),
            'NewsPost' => __('News post'),
            'EventPost' => __('Event post'),
            'BarProduct' => __('Bar product'),
            'BarCategory' => __('Bar category'),
            'BarOrder' => __('Bar order'),
            'BarPayment' => __('Bar payment'),
            'BarStockMovement' => __('Stock movement'),
            'AppSetting' => __('Setting'),
            default => $base,
        };
    }

    /**
     * Label map (subject_type => human label) for the types present in the log.
     *
     * @return array<string, string>
     */
    public function subjectLabels(): array
    {
        return $this->subjectLabelCache ??= Activity::query()
            ->whereNotNull('subject_type')
            ->distinct()
            ->pluck('subject_type')
            ->mapWithKeys(fn (string $type): array => [$type => $this->subjectLabel($type)])
            ->all();
    }

    public function updatedCauserFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedEventFilter(): void
    {
        $this->resetPage();
    }

    public function updatedModelFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSortBy(): void
    {
        $this->resetPage();
    }

    protected function activities(): LengthAwarePaginator
    {
        $col = $this->sortBy['column'];
        $dir = $this->sortBy['direction'];

        return Activity::with(['causer', 'subject'])
            ->when($this->search, fn (Builder $q) => $q->where(
                fn (Builder $group) => $this->applySearch($group, $this->search)
            ))
            ->when($this->modelFilter, fn ($q) => $q->where('subject_type', $this->modelFilter))
            ->when($this->causerFilter, fn ($q) => $q->where('causer_id', $this->causerFilter))
            ->when($this->eventFilter, fn ($q) => $q->where('event', $this->eventFilter))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->orderBy($col, $dir)
            ->paginate(50);
    }

    /**
     * Free-text search, applied inside its own `where` group.
     *
     * The log only stores machine values — `created`, an FQCN, foreign keys —
     * so a raw LIKE can never match what the page actually displays. The term
     * is resolved first (into member ids and into the subject types whose human
     * label matches), then matched against the indexed morph columns.
     */
    protected function applySearch(Builder $query, string $term): void
    {
        $memberIds = $this->searchMemberIds($term);

        $query
            ->whereIn('subject_type', $this->matchingSubjectTypes($term))
            ->orWhere(fn (Builder $authored) => $authored
                ->where('causer_type', User::class)
                ->whereIn('causer_id', $memberIds)
            )
            ->orWhere(fn (Builder $targeted) => $targeted
                ->where('subject_type', User::class)
                ->whereIn('subject_id', $memberIds)
            );
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Audit'));
    }

    /**
     * Members who have authored at least one logged action.
     *
     * @return array<int, array{id: int, name: string}>
     */
    protected function causerOptions(): array
    {
        $ids = Activity::query()
            ->whereNotNull('causer_id')
            ->where('causer_type', User::class)
            ->distinct()
            ->pluck('causer_id');

        /** @var EloquentCollection<int, User> $users */
        $users = User::whereIn('id', $ids)->orderBy('last_name')->get();

        return $users
            ->map(fn (User $user): array => ['id' => $user->id, 'name' => "{$user->first_name} {$user->last_name}"])
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    protected function eventOptions(): array
    {
        return [
            ['id' => 'created', 'name' => __('Created')],
            ['id' => 'updated', 'name' => __('Modified')],
            ['id' => 'deleted', 'name' => __('Deleted')],
        ];
    }

    /**
     * Subject types whose human label — or class name — contains the term.
     *
     * Derived from subjectLabel() so the label map is never duplicated.
     *
     * @return array<int, string>
     */
    protected function matchingSubjectTypes(string $term): array
    {
        return collect($this->subjectLabels())
            ->filter(fn (string $label, string $type): bool => mb_stripos($label, $term) !== false
                || mb_stripos(class_basename($type), $term) !== false
            )
            ->keys()
            ->all();
    }

    /**
     * Distinct subject types present in the log, as select options.
     *
     * @return array<int, array{id: string, name: string}>
     */
    protected function modelOptions(): array
    {
        return collect($this->subjectLabels())
            ->map(fn (string $label, string $type): array => ['id' => $type, 'name' => $label])
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * Ids of the members whose name (or email) matches the term.
     *
     * @return array<int, int>
     */
    protected function searchMemberIds(string $term): array
    {
        return $this->searchMemberIdCache[$term] ??= User::query()
            ->searchName($term)
            ->pluck('id')
            ->all();
    }
};
