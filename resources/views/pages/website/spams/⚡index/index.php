<?php

declare(strict_types=1);

namespace Resources\views\Pages\Website\Spams\Index;

use App\Domains\ClubAdmin\Contact\Models\Spam;
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

    #[Url]
    public string $search = '';

    #[Url]
    public string $period = '';

    #[Url]
    public string $userAgentType = '';

    /** @var array{column: string, direction: string} */
    public array $sortBy = ['column' => 'created_at', 'direction' => 'desc'];

    public bool $detailModal = false;

    public ?int $detailSpamId = null;

    public bool $deleteModal = false;

    public ?int $deletingId = null;

    public bool $bulkDeleteModal = false;

    // ── HasBulkActions ────────────────────────────────────────────────────────

    /** @return array<int, string> */
    protected function getPageIds(): array
    {
        return $this->spams
            ->pluck('id')
            ->map(fn (int $id) => (string) $id)
            ->toArray();
    }

    public function getTotalMatchingCount(): int
    {
        return $this->spams->total();
    }

    // ── HasFilterDrawer ───────────────────────────────────────────────────────

    /** @return array<int, array{key: string, label: string}> */
    public function getFilterChips(): array
    {
        $chips = [];

        if (filled($this->period)) {
            $chips[] = [
                'key'   => 'period',
                'label' => match ($this->period) {
                    'today' => __('Period') . ': ' . __('Today'),
                    'week'  => __('Period') . ': ' . __('This week'),
                    'month' => __('Period') . ': ' . __('This month'),
                    default => __('Period') . ': ' . $this->period,
                },
            ];
        }

        if (filled($this->userAgentType)) {
            $chips[] = [
                'key'   => 'userAgentType',
                'label' => __('Type') . ': ' . match ($this->userAgentType) {
                    'bot'     => 'Bot',
                    'curl'    => 'cURL',
                    'browser' => __('Browsers'),
                    default   => $this->userAgentType,
                },
            ];
        }

        return $chips;
    }

    public function clearFilters(): void
    {
        $this->period        = '';
        $this->userAgentType = '';
        $this->resetPage();
    }

    // ── Bulk actions ──────────────────────────────────────────────────────────

    public function confirmBulkDelete(): void
    {
        $this->bulkDeleteModal = true;
    }

    public function bulkDelete(): void
    {
        if (empty($this->selected)) {
            return;
        }

        $count = Spam::whereIn('id', $this->selected)->delete();
        $this->bulkDeleteModal = false;
        $this->clearSelection();
        $this->error(trans_choice('selectedCount', $count, ['count' => $count]) . ' ' . __('deleted.'));
    }

    // ── Filter hooks ──────────────────────────────────────────────────────────

    public function updatedSearch(): void        { $this->resetPage(); }

    public function updatedPeriod(): void        { $this->resetPage(); }

    public function updatedUserAgentType(): void { $this->resetPage(); }

    // ── Single-record actions ─────────────────────────────────────────────────

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
        $this->deleteModal = false;
        $this->deletingId  = null;
        $this->error(__('Spam deleted.'));
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    #[Computed]
    public function spams(): LengthAwarePaginator
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

        return $query->paginate(25);
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
            ->current(__('Spams'));
    }

    public function render(): View
    {
        return $this->view();
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $stats = [
            'total'     => Spam::count(),
            'today'     => Spam::whereDate('created_at', today())->count(),
            'uniqueIps' => Spam::distinct('ip')->count('ip'),
        ];

        $periodOptions = [
            ['id' => 'today', 'name' => __('Today')],
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
            ['key' => 'created_at', 'label' => __('Date'),       'class' => 'hidden sm:table-cell'],
            ['key' => 'ip',         'label' => 'IP',             'sortable' => false],
            ['key' => 'user_agent', 'label' => 'User Agent',     'class' => 'hidden md:table-cell', 'sortable' => false],
            ['key' => 'data',       'label' => __('Data'),        'class' => 'hidden lg:table-cell', 'sortable' => false],
        ];

        return [
            'breadcrumbs'      => $this->getBreadcrumbs(),
            'spams'            => $this->spams,
            'stats'            => $stats,
            'periodOptions'    => $periodOptions,
            'userAgentOptions' => $userAgentOptions,
            'detailSpam'       => $detailSpam,
            'headers'          => $headers,
            'filterChips'      => $this->filterChips,
        ];
    }
};
