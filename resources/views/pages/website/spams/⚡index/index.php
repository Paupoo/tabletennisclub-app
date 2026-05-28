<?php

declare(strict_types=1);

namespace Resources\views\Pages\Website\Spams\Index;

use App\Models\ClubAdmin\Contact\Spam;
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
    public string $period = '';

    #[Url]
    public string $userAgentType = '';

    public bool $showFilters = false;

    public array $sortBy = ['column' => 'created_at', 'direction' => 'desc'];

    public array $selected = [];

    public bool $detailModal = false;

    public ?int $detailSpamId = null;

    public bool $deleteModal = false;

    public ?int $deletingId = null;

    public bool $bulkDeleteModal = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->selected = [];
    }

    public function updatedPeriod(): void
    {
        $this->resetPage();
        $this->selected = [];
    }

    public function updatedUserAgentType(): void
    {
        $this->resetPage();
        $this->selected = [];
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'period', 'userAgentType']);
        $this->selected = [];
        $this->resetPage();
    }

    #[Computed]
    public function activeFiltersCount(): int
    {
        return collect([$this->period, $this->userAgentType])
            ->filter(fn ($v) => filled($v))
            ->count();
    }

    public function openDetail(int $id): void
    {
        $this->detailSpamId = $id;
        $this->detailModal  = true;
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId  = $id;
        $this->deleteModal = true;
    }

    public function delete(): void
    {
        Spam::findOrFail($this->deletingId)->delete();
        $this->selected    = array_values(array_filter($this->selected, fn ($id) => $id !== $this->deletingId));
        $this->deleteModal = false;
        $this->deletingId  = null;
        $this->error(__('Spam deleted.'));
    }

    public function bulkDelete(): void
    {
        if (empty($this->selected)) {
            return;
        }
        $count = Spam::whereIn('id', $this->selected)->delete();
        $this->selected        = [];
        $this->bulkDeleteModal = false;
        $this->error(trans_choice('selectedCount', $count, ['count' => $count]) . ' ' . __('deleted.'));
    }

    public function render(): View
    {
        return $this->view();
    }

    public function with(): array
    {
        $spams = $this->buildQuery()->paginate(25);

        $stats = [
            'total'     => Spam::count(),
            'today'     => Spam::whereDate('created_at', today())->count(),
            'uniqueIps' => Spam::distinct('ip')->count('ip'),
        ];

        $periodOptions = [
            ['id' => 'today', 'name' => __("Today")],
            ['id' => 'week',  'name' => __('This week')],
            ['id' => 'month', 'name' => __('This month')],
        ];

        $userAgentOptions = [
            ['id' => 'bot',     'name' => 'Bots'],
            ['id' => 'curl',    'name' => 'cURL'],
            ['id' => 'browser', 'name' => __('Browsers')],
        ];

        $detailSpam = $this->detailSpamId ? Spam::find($this->detailSpamId) : null;

        $headers = [
            ['key' => 'created_at', 'label' => __('Date'), 'class' => 'hidden sm:table-cell'],
            ['key' => 'ip', 'label' => 'IP', 'sortable' => false],
            ['key' => 'user_agent', 'label' => 'User Agent', 'class' => 'hidden md:table-cell', 'sortable' => false],
            ['key' => 'data', 'label' => __('Data'), 'class' => 'hidden lg:table-cell', 'sortable' => false],
        ];

        return [
            'breadcrumbs'      => Breadcrumb::make()->home()->add('Website', '#')->current('Spam')->toArray(),
            'spams'            => $spams,
            'stats'            => $stats,
            'periodOptions'    => $periodOptions,
            'userAgentOptions' => $userAgentOptions,
            'detailSpam'       => $detailSpam,
            'headers'          => $headers,
        ];
    }

    private function buildQuery()
    {
        $query = Spam::query()->orderBy($this->sortBy['column'], $this->sortBy['direction']);

        if ($this->search) {
            $term = '%' . $this->search . '%';
            $query->where(fn ($q) => $q
                ->where('ip', 'like', $term)
                ->orWhere('user_agent', 'like', $term)
            );
        }

        if ($this->period) {
            match ($this->period) {
                'today' => $query->whereDate('created_at', today()),
                'week'  => $query->where('created_at', '>=', now()->subWeek()),
                'month' => $query->where('created_at', '>=', now()->subMonth()),
                default => null,
            };
        }

        if ($this->userAgentType) {
            $query->where('user_agent', 'like', match ($this->userAgentType) {
                'bot'     => '%bot%',
                'curl'    => '%curl%',
                'browser' => '%Mozilla%',
                default   => '%',
            });
        }

        return $query;
    }
};
