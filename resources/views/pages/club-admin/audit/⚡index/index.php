<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
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
        return Activity::query()
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
            ->when($this->search, fn ($q) => $q
                ->where('description', 'like', "%{$this->search}%")
                ->orWhere('subject_type', 'like', "%{$this->search}%")
            )
            ->when($this->modelFilter, fn ($q) => $q->where('subject_type', $this->modelFilter))
            ->when($this->causerFilter, fn ($q) => $q->where('causer_id', $this->causerFilter))
            ->when($this->eventFilter, fn ($q) => $q->where('event', $this->eventFilter))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->orderBy($col, $dir)
            ->paginate(50);
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
     * Distinct subject types present in the log, as select options.
     *
     * @return array<int, array{id: string, name: string}>
     */
    protected function modelOptions(): array
    {
        return Activity::query()
            ->whereNotNull('subject_type')
            ->distinct()
            ->pluck('subject_type')
            ->map(fn (string $type): array => ['id' => $type, 'name' => $this->subjectLabel($type)])
            ->sortBy('name')
            ->values()
            ->all();
    }
};
