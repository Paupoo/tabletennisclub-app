<?php

declare(strict_types=1);

namespace Resources\views\Pages\Website\Spams\Index;

use App\Models\ClubAdmin\Contact\Spam;
use App\Support\Breadcrumb;
use Illuminate\View\View;
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

    public array $selectedItems   = [];
    public bool $selectAll        = false;

    public bool $detailModal  = false;
    public ?int $detailSpamId = null;

    public bool $deleteModal  = false;
    public ?int $deletingId   = null;

    public bool $bulkDeleteModal = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->resetSelection();
    }

    public function updatedPeriod(): void
    {
        $this->resetPage();
        $this->resetSelection();
    }

    public function updatedUserAgentType(): void
    {
        $this->resetPage();
        $this->resetSelection();
    }

    public function updatedSelectAll(): void
    {
        if ($this->selectAll) {
            $this->selectedItems = $this->buildQuery()->paginate(25)->pluck('id')->toArray();
        } else {
            $this->selectedItems = [];
        }
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
        $this->selectedItems = array_values(array_filter($this->selectedItems, fn ($id) => $id !== $this->deletingId));
        $this->deleteModal = false;
        $this->deletingId  = null;
        $this->error('Spam supprimé.');
    }

    public function bulkDelete(): void
    {
        if (empty($this->selectedItems)) {
            return;
        }
        $count = Spam::whereIn('id', $this->selectedItems)->delete();
        $this->resetSelection();
        $this->bulkDeleteModal = false;
        $this->error("{$count} spam(s) supprimé(s).");
    }

    public function render(): View
    {
        return $this->view();
    }

    public function with(): array
    {
        $spams = $this->buildQuery()->paginate(25);

        $stats = [
            'total'    => Spam::count(),
            'today'    => Spam::whereDate('created_at', today())->count(),
            'uniqueIps' => Spam::distinct('ip')->count('ip'),
        ];

        $periodOptions = [
            ['id' => 'today', 'name' => "Aujourd'hui"],
            ['id' => 'week', 'name' => 'Cette semaine'],
            ['id' => 'month', 'name' => 'Ce mois'],
        ];

        $userAgentOptions = [
            ['id' => 'bot', 'name' => 'Bots'],
            ['id' => 'curl', 'name' => 'cURL'],
            ['id' => 'browser', 'name' => 'Navigateurs'],
        ];

        $detailSpam = $this->detailSpamId ? Spam::find($this->detailSpamId) : null;

        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->add('Website', '#')
                ->current('Spam')
                ->toArray(),
            'spams'            => $spams,
            'stats'            => $stats,
            'periodOptions'    => $periodOptions,
            'userAgentOptions' => $userAgentOptions,
            'detailSpam'       => $detailSpam,
        ];
    }

    private function buildQuery()
    {
        $query = Spam::query()->orderByDesc('created_at');

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

    private function resetSelection(): void
    {
        $this->selectedItems = [];
        $this->selectAll     = false;
    }
};
