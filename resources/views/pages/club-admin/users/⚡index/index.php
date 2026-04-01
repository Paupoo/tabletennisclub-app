<?php

use App\Models\ClubAdmin\Users\User;
use App\Support\Breadcrumb;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;
    use WithPagination;

    // ── Recherche & tri ──────────────────────────────────────────────────────
    public string $search = '';

    public ?string $subscription_id = null;

    public array $sortBy = ['column' => 'last_name', 'direction' => 'asc'];

    // ── Filtres ──────────────────────────────────────────────────────────────
    public bool $showFilters = false;

    public array $licenceTypes = [];   // ex: ['competitive', 'recreational']

    public array $categories = [];     // ex: ['men', 'women', 'youth']

    public bool $onlyActive = false;

    // ── Sélection bulk ───────────────────────────────────────────────────────
    public array $selected = [];

    public ?int $team_id = null;

    public array $team_ids = [];

    public ?int $event_id = null;

    public ?int $training_id = null;

    // ── Modales ──────────────────────────────────────────────────────────────
    public bool $deleteModal = false;

    public bool $deleteSelectedModal = false;

    public ?int $userToDelete = null;

    // ────────────────────────────────────────────────────────────────────────
    // Computed
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Nombre de filtres actifs — utilisé pour le badge sur le bouton.
     */
    #[Computed]
    public function activeFiltersCount(): int
    {
        return count($this->licenceTypes)
            + count($this->categories)
            + ($this->onlyActive ? 1 : 0);
    }

    /**
     * En-têtes de la table.
     */
    #[Computed]
    public function headers(): array
    {
        return [
            ['key' => 'photo',      'label' => '',              'sortable' => false],
            ['key' => 'name',  'label' => __('Name'),      'sortable' => true],
            ['key' => 'email',      'label' => __('Email'),     'sortable' => true],
            ['key' => 'is_competitive', 'label' => __('Licence'),  'sortable' => true],
            ['key' => 'ranking',    'label' => __('Ranking'),   'sortable' => true],
        ];
    }

    /**
     * Données de la table avec filtres appliqués.
     */
    #[Computed]
    public function users()
    {
        return User::query()
            ->when($this->search, fn ($q) => $q->where(
                fn ($q) => $q->where('first_name', 'like', "%{$this->search}%")
                    ->orWhere('last_name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
            ))
            ->when(
                $this->licenceTypes,
                fn ($q) => $q->whereIn('licence_type', $this->licenceTypes)
            )
            ->when(
                $this->categories,
                fn ($q) => $q->whereIn('category', $this->categories)
            )
            ->when(
                $this->onlyActive,
                fn ($q) => $q->where('is_active', true)
            )
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate(15);
    }

    /**
     * Liste des équipes pour le select bulk.
     */
    #[Computed]
    public function teams(): Collection
    {
        return collect([
            ['id' => 1, 'name' => 'Équipe A'],
            ['id' => 2, 'name' => 'Équipe B'],
            ['id' => 3, 'name' => 'Équipe C'],
        ]);
    }

    #[Computed]
    public function subscriptions(): Collection
    {
        return collect([
            ['id' => 'event-1',    'name' => 'Tournoi printemps',   'group' => __('Events')],
            ['id' => 'event-2',    'name' => 'Coupe régionale',     'group' => __('Events')],
            ['id' => 'event-3',    'name' => 'Championnat été',     'group' => __('Events')],
            ['id' => 'training-1', 'name' => 'Entraînement lundi',  'group' => __('Trainings')],
            ['id' => 'training-2', 'name' => 'Entraînement mercredi', 'group' => __('Trainings')],
        ]);
    }

    public function bulkSubscribe(): void
    {
        if (! $this->subscription_id) {
            return;
        }

        // TODO: logique réelle quand les modèles Event/Training existeront
        $this->subscription_id = null;
        $this->success(__('Users subscribed.'));
    }

    // ────────────────────────────────────────────────────────────────────────
    // Filtres
    // ────────────────────────────────────────────────────────────────────────

    public function resetFilters(): void
    {
        $this->licenceTypes = [];
        $this->categories = [];
        $this->onlyActive = false;
        $this->resetPage();
    }

    // ────────────────────────────────────────────────────────────────────────
    // Suppression simple
    // ────────────────────────────────────────────────────────────────────────

    public function confirmDelete(int $userId): void
    {
        $this->userToDelete = $userId;
        $this->deleteModal = true;
    }

    public function delete(): void
    {
        User::findOrFail($this->userToDelete)->delete();

        $this->userToDelete = null;
        $this->deleteModal = false;
        $this->success(__('User deleted.'));
    }

    // ────────────────────────────────────────────────────────────────────────
    // Bulk — suppression
    // ────────────────────────────────────────────────────────────────────────

    public function confirmBulkDelete(): void
    {
        $this->deleteSelectedModal = true;
    }

    public function deleteSelected(): void
    {
        User::whereIn('id', $this->selected)->delete();

        $this->selected = [];
        $this->deleteSelectedModal = false;
        $this->success(__('Selected users deleted.'));
    }

    // ────────────────────────────────────────────────────────────────────────
    // Bulk — équipe
    // ────────────────────────────────────────────────────────────────────────

    public function bulkAddToTeam(): void
    {
        if (! $this->team_id) {
            return;
        }

        // TODO: logique réelle quand le modèle Team existera
        $this->team_id = null;
        $this->success(__('Users added to the team.'));
    }

    // ────────────────────────────────────────────────────────────────────────
    // Bulk — activation
    // ────────────────────────────────────────────────────────────────────────

    public function bulkActivate(): void
    {
        User::whereIn('id', $this->selected)->update(['is_active' => true]);
        $this->success(__('Users activated.'));
    }

    public function bulkDeactivate(): void
    {
        User::whereIn('id', $this->selected)->update(['is_active' => false]);
        $this->success(__('Users deactivated.'));
    }

    // ────────────────────────────────────────────────────────────────────────
    // Render
    // ────────────────────────────────────────────────────────────────────────

    public function render()
    {
        return $this->view([
            'users' => $this->users,
            'headers' => $this->headers,
            'teams' => $this->teams,
            'subscriptions' => $this->subscriptions,
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->users()
                ->current(__('List'))
                ->toArray(),
            'activeFiltersCount' => $this->activeFiltersCount,
        ]);
    }
};