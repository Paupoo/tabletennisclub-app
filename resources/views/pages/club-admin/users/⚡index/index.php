<?php

declare(strict_types=1);

use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Team;
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

    public array $categories = [];     // ex: ['men', 'women', 'youth']

    // ── Modales ──────────────────────────────────────────────────────────────
    public bool $deleteModal = false;

    public bool $deleteSelectedModal = false;

    public ?int $event_id = null;

    public array $licenceTypes = [];   // ex: ['competitive', 'recreational']

    public bool $showInactiveUsers = false;

    // ── Recherche & tri ──────────────────────────────────────────────────────
    public string $search = '';

    // ── Sélection bulk ───────────────────────────────────────────────────────
    public array $selected = [];

    public string $selectedLicenceType = 'both';

    // ── Filtres ──────────────────────────────────────────────────────────────
    public bool $showFilters = false;

    public array $sortBy = ['column' => 'last_name', 'direction' => 'asc'];

    public ?string $subscription_id = null;

    public ?int $team_id = null;

    public array $team_ids = [];

    public ?int $training_id = null;

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
        return ($this->selectedLicenceType !== 'both' ? 1 : 0)
            + count($this->categories)
            + ($this->showInactiveUsers ? 1 : 0);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Bulk — activation
    // ────────────────────────────────────────────────────────────────────────

    public function bulkActivate(): void
    {
        User::whereIn('id', $this->selected)->update(['is_active' => true]);
        $this->success(__('Users activated.'));
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

    public function bulkDeactivate(): void
    {
        User::whereIn('id', $this->selected)->update(['is_active' => false]);
        $this->success(__('Users deactivated.'));
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
    // Bulk — suppression
    // ────────────────────────────────────────────────────────────────────────

    public function confirmBulkDelete(): void
    {
        $this->deleteSelectedModal = true;
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

    public function deleteSelected(): void
    {
        User::whereIn('id', $this->selected)->delete();

        $this->selected = [];
        $this->deleteSelectedModal = false;
        $this->success(__('Selected users deleted.'));
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

    public function mount(): void
    {
        $this->licenceTypes = [
            [
                'id' => 'both',
                'name' => __('Both'),
            ],
            [
                'id' => 'competitive',
                'name' => __('Competitive'),
            ],
            [
                'id' => 'recreative',
                'name' => __('Recreative'),
            ],
        ];
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

    // ────────────────────────────────────────────────────────────────────────
    // Filtres
    // ────────────────────────────────────────────────────────────────────────

    public function resetFilters(): void
    {
        $this->selectedLicenceType = 'both';
        $this->categories = [];
        $this->showInactiveUsers = false;
        $this->resetPage();
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

    /**
     * Liste des équipes pour le select bulk.
     */
    #[Computed]
    public function teams(): Collection
    {
        return Team::with('captain')
            ->orderBy('name')
            ->get()
            ->map(fn (Team $team) => [
                'id' => $team->id,
                'name' => __('Team') . ' ' . $team->name,
                'avatar' => $team->captain->photo ?? '/images/empty-user.jpg',
            ]);
    }

    public function updatedCategories(): void
    {
        $this->resetPage();
    }

    public function updatedShowInactiveUsers(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedLicenceType(): void
    {
        $this->resetPage();
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
                $this->selectedLicenceType === 'competitive',
                fn ($q) => $q->where('is_competitor', true)
            )
            ->when(
                $this->selectedLicenceType === 'recreative',
                fn ($q) => $q->where('is_competitor', false)
            )
            ->when(
                $this->categories,
                fn ($q) => $q->whereIn('gender', $this->categories)
            )
            ->when(
                !$this->showInactiveUsers,
                fn ($q) => $q->where('is_active', true)
            )
            ->when(
                count($this->team_ids) > 0,
                fn ($q) => $q->whereHas(
                    'teams',
                    fn ($teamQuery) => $teamQuery->whereIn('teams.id', $this->team_ids))

            )
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate(15);
    }
};
