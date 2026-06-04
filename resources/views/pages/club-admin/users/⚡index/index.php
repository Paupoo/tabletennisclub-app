<?php

declare(strict_types=1);

use App\Actions\User\AnonymizeUserAction;
use App\Actions\User\CreateUserAction;
use App\Actions\User\RecalculateForceListAction;
use App\Actions\User\RestoreUserAction;
use App\Actions\User\SendInvitationAction;
use App\Actions\User\SoftDeleteUserAction;
use App\Data\User\CreateUserData;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Gender;
use Illuminate\Validation\Rule as ValidationRule;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast, HasBreadcrumbs;
    use WithPagination;

    public array $categories = [];

    // ── Quick invite ─────────────────────────────────────────────────────────
    public bool $quickInviteDrawer = false;

    // ── Anonymize modal ───────────────────────────────────────────────────────
    public bool $anonymizeModal = false;

    public ?int $anonymizeUserId = null;

    public string $anonymizeConfirmText = '';

    public string $inviteFirstName = '';

    public string $inviteLastName = '';

    public string $inviteEmail = '';

    // ── Modales ──────────────────────────────────────────────────────────────
    public bool $deleteModal = false;

    public bool $deleteSelectedModal = false;

    public ?int $event_id = null;

    public array $licenceTypes = [];   // ex: ['competitive', 'recreational']

    public bool $showArchived = false;

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

    public function recalculateForceList(): void
    {
        abort_unless(Auth::user()->is_admin || Auth::user()->is_committee_member, 403);

        RecalculateForceListAction::handle();

        $this->success(__('Force list recalculated.'));
    }

    public function quickInvite(): void
    {
        $this->validate([
            'inviteFirstName' => ['required', 'string', 'max:255'],
            'inviteLastName'  => ['required', 'string', 'max:255'],
            'inviteEmail'     => ['required', 'email', ValidationRule::unique('users', 'email')],
        ]);

        $email = $this->inviteEmail;

        CreateUserAction::handle(
            new CreateUserData(
                first_name: $this->inviteFirstName,
                last_name: $this->inviteLastName,
                email: $email,
                gender: Gender::MEN,
            ),
            Auth::user()
        );

        $this->reset(['inviteFirstName', 'inviteLastName', 'inviteEmail']);
        $this->quickInviteDrawer = false;

        $this->success(__('Invitation sent to :email.', ['email' => $email]));
    }

    public function sendInvitation(int $userId): void
    {
        $user = User::findOrFail($userId);

        SendInvitationAction::handle($user);

        $this->success(__('Invitation sent to :email.', ['email' => $user->email]));
    }

    public function openAnonymizeModal(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->authorize('anonymize', $user);

        $this->anonymizeUserId = $userId;
        $this->anonymizeConfirmText = '';
        $this->anonymizeModal = true;
    }

    public function confirmAnonymize(): void
    {
        if (strtoupper($this->anonymizeConfirmText) !== 'ANONYMIZE') {
            $this->error(__('Type ANONYMIZE to confirm.'));

            return;
        }

        $user = User::findOrFail($this->anonymizeUserId);
        $this->authorize('anonymize', $user);

        AnonymizeUserAction::handle($user);

        $this->anonymizeModal = false;
        $this->anonymizeUserId = null;
        $this->anonymizeConfirmText = '';

        $this->success(__('User anonymized (GDPR). All personal data has been erased.'));
    }

    public function confirmDelete(int $userId): void
    {
        $this->userToDelete = $userId;
        $this->deleteModal = true;
    }

    public function delete(): void
    {
        $user = User::findOrFail($this->userToDelete);

        $this->userToDelete = null;
        $this->deleteModal = false;

        if (Auth::user()->is($user)) {
            $this->error(__('You cannot archive your own account.'));

            return;
        }

        $this->authorize('delete', $user);

        SoftDeleteUserAction::handle($user);

        $this->success(__('User archived.'));
    }

    public function deleteSelected(): void
    {
        abort_unless(Auth::user()->is_admin, 403);

        $selfIncluded = in_array((string) Auth::id(), array_map('strval', $this->selected));

        User::whereIn('id', $this->selected)
            ->where('id', '!=', Auth::id())
            ->each(fn (User $user) => SoftDeleteUserAction::handle($user));

        $this->selected = [];
        $this->deleteSelectedModal = false;

        if ($selfIncluded) {
            $this->warning(__('Users archived. Your own account was excluded from the selection.'));
        } else {
            $this->success(__('Selected users archived.'));
        }
    }

    public function restoreUser(int $userId): void
    {
        $user = User::withTrashed()->findOrFail($userId);
        $this->authorize('restore', $user);

        RestoreUserAction::handle($user);

        $this->success(__('User restored.'));
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

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->users()
            ->current(__('List'));
    }

    public function render()
    {
        return $this->view([
            'users' => $this->users,
            'headers' => $this->headers,
            'teams' => $this->teams,
            'subscriptions' => $this->subscriptions,
            'breadcrumbs' => $this->getBreadcrumbs(),
            'activeFiltersCount' => $this->activeFiltersCount,
            'stats'              => $this->stats,
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

    public function updatedShowArchived(): void
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

    #[Computed]
    public function stats(): array
    {
        return [
            'total'       => User::count(),
            'active'      => User::where('is_active', true)->count(),
            'competitive' => User::where('is_competitor', true)->count(),
            'inactive'    => User::where('is_active', false)->count(),
        ];
    }

    /**
     * Données de la table avec filtres appliqués.
     */
    #[Computed]
    public function users()
    {
        $query = $this->showArchived
            ? User::onlyTrashed()
            : User::query();

        return $query
            ->when($this->search, fn ($q) => $q->where(
                fn ($q) => $q->where('first_name', 'like', "%{$this->search}%")
                    ->orWhere('last_name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
            ))
            ->when(
                ! $this->showArchived && $this->selectedLicenceType === 'competitive',
                fn ($q) => $q->where('is_competitor', true)
            )
            ->when(
                ! $this->showArchived && $this->selectedLicenceType === 'recreative',
                fn ($q) => $q->where('is_competitor', false)
            )
            ->when(
                $this->categories,
                fn ($q) => $q->whereIn('gender', $this->categories)
            )
            ->when(
                ! $this->showArchived && ! $this->showInactiveUsers,
                fn ($q) => $q->where('is_active', true)
            )
            ->when(
                count($this->team_ids) > 0,
                fn ($q) => $q->whereHas(
                    'teams',
                    fn ($teamQuery) => $teamQuery->whereIn('teams.id', $this->team_ids)
                )
            )
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate(15);
    }
};
