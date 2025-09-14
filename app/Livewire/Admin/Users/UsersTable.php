<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Users;

use App\Models\User;
use App\Services\ForceList;
use Exception;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Url; // Ajout pour les URLs

class UsersTable extends Component
{
    use WithPagination;

    // Utilisation d'Url attributes pour maintenir l'état dans l'URL
    #[Url(as: 'competitor')]
    public string $competitor = '';
    
    #[Url(as: 'per_page')]
    public int $perPage = 25;
    
    #[Url(as: 'search')]
    public string $search = '';
    
    public ?int $selectedUserId = null;
    
    #[Url(as: 'gender')]
    public string $gender = '';
    
    #[Url(as: 'sort_field')]
    public string $sortByField = '';
    
    #[Url(as: 'sort_dir')]
    public string $sortDirection = 'desc';
    
    #[Url(as: 'status')]
    public string $status = '';
    
    protected ForceList $forceList;
    public array $selectedItems = [];
    public bool $selectAll = false;

    // Définir le nom de la page pour éviter les conflits
    protected string $paginationView = 'custom-paginate';
    protected string $paginationTheme = 'tailwind';

    public function boot(ForceList $forceList)
    {
        $this->forceList = $forceList;
    }

    // Méthode pour s'assurer que la pagination utilise la bonne route
    public function updatingPage($page)
    {
        $this->selectedItems = [];
        $this->selectAll = false;
    }

    public function bulkActivate()
    {
        $this->authorize('update', Auth()->user());

        User::whereIn('id', $this->selectedItems)->update([
            'is_active' => true,
        ]);
        session()->flash('success', __(count($this->selectedItems) . ' member(s) have been activated.'));
        $this->resetSelection();
        // Utiliser redirect au lieu de redirectRoute pour éviter les problèmes de méthode
        return redirect()->route('users.index');
    }

    public function bulkDeactivate()
    {
        $this->authorize('update', Auth()->user());

        User::whereIn('id', $this->selectedItems)->update([
            'is_active' => false,
        ]);
        session()->flash('warning', __(count($this->selectedItems) . ' member(s) have been deactivated.'));
        $this->resetSelection();
        return redirect()->route('users.index');
    }

    public function bulkPaid()
    {
        $this->authorize('update', Auth()->user());

        User::whereIn('id', $this->selectedItems)->update([
            'has_paid' => true,
        ]);
        session()->flash('success', __(count($this->selectedItems) . ' member(s) have been marked paid.'));
        $this->resetSelection();
        return redirect()->route('users.index');
    }

    public function bulkUnpaid()
    {
        $this->authorize('update', Auth()->user());

        User::whereIn('id', $this->selectedItems)->update([
            'has_paid' => false,
        ]);
        session()->flash('warning', __(count($this->selectedItems) . ' member(s) have marked unpaid.'));
        $this->resetSelection();
        return redirect()->route('users.index');
    }

    public function bulkDelete()
    {
        $this->authorize('delete', Auth()->user());

        User::whereIn('id', $this->selectedItems)->delete();
        session()->flash('success', __(count($this->selectedItems) . ' member(s) have been deleted.'));
        $this->resetSelection();
        return redirect()->route('users.index');
    }

    // Méthode helper pour réinitialiser la sélection
    private function resetSelection(): void
    {
        $this->selectedItems = [];
        $this->selectAll = false;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy()
    {
        $user = User::findOrFail($this->selectedUserId);
        $this->authorize('delete', [Auth()->user(), $user]);
        
        try {
            // Vérifier les contraintes métier
            if ($user->tournaments()->whereIn('status', ['draft', 'open', 'pending'])->count() > 0) {
                session()->flash('error', __('Cannot delete ' . $user->first_name . ' ' . $user->last_name . ' because he subscribed to one or more tournaments'));
                return redirect()->route('users.index');
            }
            
            $user->delete();
            $this->forceList->setOrUpdateAll();
            session()->flash('warning', __('User ' . $user->first_name . ' ' . $user->last_name . ' has been deleted'));
            return redirect()->route('users.index');
            
        } catch (QueryException $e) {
            session()->flash('error', __('The user could not be deleted'));
            return redirect()->route('users.index');
        }
    }

    public function render()
    {
        $users = $this->getUsers()
            ->paginate($this->perPage);

        return view('livewire.admin.users.users-table', [
            'users' => $users,
            'user_model' => User::class,
        ]);
    }

    private function getUsers(): Builder
    {
        $query = User::query();

        // Application des filtres de recherche
        if (!empty($this->search)) {
            $query->searchTerms($this->search);
        }

        // Filtre competitor - vérification explicite des valeurs
        if ($this->competitor !== '' && $this->competitor !== 'all') {
            $query->where('is_competitor', $this->competitor === '1');
        }

        // Filtre gender - vérification que la valeur correspond aux enum
        if (!empty($this->gender) && $this->gender !== 'all') {
            $query->where('gender', $this->gender);
        }

        // Filtres de statut - logique améliorée
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

        // Gestion du tri
        if (empty($this->sortByField)) {
            // Tri par défaut
            $query->orderBy('is_competitor', 'desc')
                  ->orderBy('force_list')
                  ->orderBy('ranking')
                  ->orderBy('last_name')
                  ->orderBy('first_name')
                  ->with('teams');
        } else {
            // Tri personnalisé
            $query->orderBy($this->sortByField, $this->sortDirection);
        }

        return $query;
    }

    public function sortBy($field)
    {
        if ($this->sortByField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortDirection = 'asc';
        }

        $this->sortByField = $field;
        $this->resetPage(); // Retourner à la page 1 lors du tri
    }

    // Méthode supprimée car elle duplique la logique de searchTerms
    // private function applySearch($query, string $search): void { ... }

    public function updatedSelectAll(): void
    {
        if ($this->selectAll) {
            $this->selectedItems = $this->getUsers()
                ->paginate($this->perPage)
                ->pluck('id')
                ->toArray();
        } else {
            $this->selectedItems = [];
        }
    }

    public function updatedSelectedItems(): void
    {
        $this->selectAll = false;
    }

    // Réinitialiser la pagination quand on change le nombre par page
    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    // Méthodes pour réinitialiser la pagination lors des changements de filtres
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCompetitor(): void
    {
        $this->resetPage();
    }

    public function updatedGender(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }
}