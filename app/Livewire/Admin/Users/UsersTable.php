<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Users;

use App\Models\ClubAdmin\Users\User;
use App\Services\ForceList;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class UsersTable extends Component
{
    use WithPagination;

    // --- Propriétés de sélection (Manquantes précédemment) ---
    public array $selectedItems = [];
    public bool $selectAll = false;

    // --- Filtres et État de l'URL ---
    #[Url(as: 'competitor')]
    public string $competitor = '';

    #[Url(as: 'per_page')]
    public int $perPage = 25;

    #[Url(as: 'search')]
    public string $search = '';

    public ?int $selectedUserId = null;

    #[Url(as: 'gender')]
    public string $sex = '';

    #[Url(as: 'sort_field')]
    public string $sortByField = '';

    #[Url(as: 'sort_dir')]
    public string $sortDirection = 'desc';

    #[Url(as: 'status')]
    public string $status = '';

    protected ForceList $forceList;
    protected string $paginationTheme = 'tailwind';

    // Injection de dépendances via boot
    public function boot(ForceList $forceList)
    {
        $this->forceList = $forceList;
    }

    // --- Actions Groupées (Bulk Actions) ---

    public function bulkActivate()
    {
        $this->authorize('update', Auth::user());

        User::whereIn('id', $this->selectedItems)->update(['is_active' => true]);

        session()->flash('success', count($this->selectedItems) . ' membre(s) activé(s).');
        $this->resetSelection();
    }

    public function bulkDeactivate()
    {
        $this->authorize('update', Auth::user());

        User::whereIn('id', $this->selectedItems)->update(['is_active' => false]);

        session()->flash('warning', count($this->selectedItems) . ' membre(s) désactivé(s).');
        $this->resetSelection();
    }

    public function bulkDelete()
    {
        $this->authorize('delete', Auth::user());

        User::whereIn('id', $this->selectedItems)->delete();

        session()->flash('success', count($this->selectedItems) . ' membre(s) supprimé(s).');
        $this->resetSelection();
    }

    public function bulkPaid()
    {
        $this->authorize('update', Auth::user());

        User::whereIn('id', $this->selectedItems)->update(['has_paid' => true]);

        session()->flash('success', count($this->selectedItems) . ' membre(s) mis à jour (payé).');
        $this->resetSelection();
    }

    public function bulkUnpaid()
    {
        $this->authorize('update', Auth::user());

        User::whereIn('id', $this->selectedItems)->update(['has_paid' => false]);

        session()->flash('warning', count($this->selectedItems) . ' membre(s) mis à jour (non payé).');
        $this->resetSelection();
    }

    // --- Gestion de la sélection ---

    public function updatedSelectAll($value): void
    {
        if ($value) {
            // Sélectionne uniquement les IDs de la page actuelle (convertis en string pour les checkboxes)
            $this->selectedItems = $this->getUsers()
                ->paginate($this->perPage)
                ->pluck('id')
                ->map(fn($id) => (string)$id)
                ->toArray();
        } else {
            $this->selectedItems = [];
        }
    }

    public function updatedSelectedItems(): void
    {
        // Si on décoche un item manuellement, on décoche le "Tout sélectionner"
        $this->selectAll = false;
    }

    private function resetSelection(): void
    {
        $this->selectedItems = [];
        $this->selectAll = false;
    }

    // --- Cycle de vie et Filtres ---

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->resetSelection();
    }
    public function updatedCompetitor(): void
    {
        $this->resetPage();
        $this->resetSelection();
    }
    public function updatedSex(): void
    {
        $this->resetPage();
        $this->resetSelection();
    }
    public function updatedStatus(): void
    {
        $this->resetPage();
        $this->resetSelection();
    }
    public function updatedPerPage(): void
    {
        $this->resetPage();
        $this->resetSelection();
    }

    public function sortBy($field)
    {
        if ($this->sortByField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortDirection = 'asc';
        }

        $this->sortByField = $field;
        $this->resetPage();
    }

    // --- Logique métier (Query) ---

    private function getUsers(): Builder
    {
        $query = User::query();

        if (!empty($this->search)) {
            $query->searchTerms($this->search);
        }

        if ($this->competitor !== '' && $this->competitor !== 'all') {
            $query->where('is_competitor', $this->competitor === '1');
        }

        if (!empty($this->sex) && $this->sex !== 'all') {
            $query->where('gender', $this->sex);
        }

        switch ($this->status) {
            case 'active':
                $query->where('is_active', true);
                break;
            case 'inactive':
                $query->where('is_active', false);
                break;
            case 'paid':
                $query->where('has_paid', true);
                break;
            case 'unpaid':
                $query->where('has_paid', false);
                break;
        }

        if (empty($this->sortByField)) {
            $query->orderBy('is_competitor', 'desc')
                ->orderBy('force_list')
                ->orderBy('ranking')
                ->orderBy('last_name')
                ->orderBy('first_name');
        } else {
            // Note: attention au tri sur les relations (ex: team_id->name),
            // cela nécessite une jointure SQL pour fonctionner.
            $query->orderBy($this->sortByField, $this->sortDirection);
        }

        return $query->with('teams');
    }

    public function render()
    {
        return view('livewire.admin.users.users-table', [
            'users' => $this->getUsers()->paginate($this->perPage),
            'user_model' => User::class,
        ]);
    }
}
