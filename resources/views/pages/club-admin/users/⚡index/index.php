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
use App\Livewire\Concerns\HasBulkActions;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast, HasBreadcrumbs, WithPagination;
    use HasBulkActions, HasFilterDrawer;

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

    // ── Modals ───────────────────────────────────────────────────────────────
    public bool $deleteModal = false;

    public bool $confirmArchiveModal = false;

    public bool $addToTeamModal = false;

    public bool $subscribeModal = false;

    public bool $showArchived = false;

    public bool $incompleteProfile = false;

    public bool $unpaidSubscription = false;

    public bool $hasKey = false;

    public bool $hasCashRegister = false;

    // ── Filters & sort ───────────────────────────────────────────────────────
    public string $search = '';

    public string $selectedLicenceType = 'both';

    /** @var array{column: string, direction: string} */
    public array $sortBy = ['column' => 'last_name', 'direction' => 'asc'];

    public ?string $subscription_id = null;

    public ?int $team_id = null;

    /** @var array<int, int> */
    public array $team_ids = [];

    public ?int $userToDelete = null;

    // ── HasBulkActions ────────────────────────────────────────────────────────

    /** @return array<int, string> */
    protected function getPageIds(): array
    {
        return $this->users
            ->pluck('id')
            ->map(fn (int $id) => (string) $id)
            ->toArray();
    }

    public function getTotalMatchingCount(): int
    {
        return $this->users->total();
    }

    // ── HasFilterDrawer ───────────────────────────────────────────────────────

    /** @return array<int, array{key: string, label: string}> */
    public function getFilterChips(): array
    {
        $chips = [];

        if ($this->selectedLicenceType !== 'both') {
            $chips[] = [
                'key'   => 'selectedLicenceType',
                'label' => $this->selectedLicenceType === 'competitive' ? __('Competitive') : __('Recreational'),
            ];
        }

        foreach (Gender::cases() as $gender) {
            if (in_array($gender->value, $this->categories, true)) {
                $chips[] = [
                    'key'   => "categories_{$gender->value}",
                    'label' => $gender->getLabel(),
                ];
            }
        }

        if ($this->incompleteProfile) {
            $chips[] = ['key' => 'incompleteProfile', 'label' => __('Incomplete profile')];
        }

        if ($this->unpaidSubscription) {
            $chips[] = ['key' => 'unpaidSubscription', 'label' => __('Unpaid subscription')];
        }

        if ($this->hasKey) {
            $chips[] = ['key' => 'hasKey', 'label' => __('Has a key')];
        }

        if ($this->hasCashRegister) {
            $chips[] = ['key' => 'hasCashRegister', 'label' => __('Has a cash register')];
        }

        if (! empty($this->team_ids)) {
            $chips[] = [
                'key'   => 'team_ids',
                'label' => trans_choice('{1} 1 team|[2,*] :count teams', count($this->team_ids), ['count' => count($this->team_ids)]),
            ];
        }

        return $chips;
    }

    public function clearFilters(): void
    {
        $this->selectedLicenceType = 'both';
        $this->categories          = [];
        $this->incompleteProfile   = false;
        $this->unpaidSubscription  = false;
        $this->hasKey              = false;
        $this->hasCashRegister     = false;
        $this->team_ids            = [];
        $this->resetPage();
    }

    public function removeFilter(string $key): void
    {
        if (str_starts_with($key, 'categories_')) {
            $value            = substr($key, strlen('categories_'));
            $this->categories = array_values(
                array_filter($this->categories, fn (string $v) => $v !== $value)
            );
        } else {
            $this->reset([$key]);
        }

        $this->resetPage();
    }

    // ── Bulk actions ──────────────────────────────────────────────────────────

    public function bulkAddToTeam(): void
    {
        if (! $this->team_id) {
            return;
        }

        // TODO: real logic when Team model is complete
        $this->team_id        = null;
        $this->addToTeamModal = false;
        $this->clearSelection();
        $this->success(__('Users added to the team.'));
    }

    public function bulkSubscribe(): void
    {
        if (! $this->subscription_id) {
            return;
        }

        // TODO: real logic when Event/Training models are complete
        $this->subscription_id = null;
        $this->subscribeModal  = false;
        $this->clearSelection();
        $this->success(__('Users subscribed.'));
    }

    public function confirmBulkArchive(): void
    {
        $this->confirmArchiveModal = true;
    }

    public function bulkArchive(): void
    {
        abort_unless(Auth::user()->is_admin, 403);

        $selfIncluded = in_array((string) Auth::id(), array_map('strval', $this->selected));

        User::whereIn('id', $this->selected)
            ->where('id', '!=', Auth::id())
            ->each(fn (User $user) => SoftDeleteUserAction::handle($user));

        $this->confirmArchiveModal = false;
        $this->clearSelection();

        if ($selfIncluded) {
            $this->warning(__('Users archived. Your own account was excluded from the selection.'));
        } else {
            $this->success(__('Selected users archived.'));
        }
    }

    // ── Single-record actions ─────────────────────────────────────────────────

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
                last_name:  $this->inviteLastName,
                email:      $email,
                gender:     Gender::MEN,
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

        $this->anonymizeUserId      = $userId;
        $this->anonymizeConfirmText = '';
        $this->anonymizeModal       = true;
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

        $this->anonymizeModal       = false;
        $this->anonymizeUserId      = null;
        $this->anonymizeConfirmText = '';

        $this->success(__('User anonymized (GDPR). All personal data has been erased.'));
    }

    public function confirmDelete(int $userId): void
    {
        $this->userToDelete = $userId;
        $this->deleteModal  = true;
    }

    public function delete(): void
    {
        $user = User::findOrFail($this->userToDelete);

        $this->userToDelete = null;
        $this->deleteModal  = false;

        if (Auth::user()->is($user)) {
            $this->error(__('You cannot archive your own account.'));

            return;
        }

        $this->authorize('delete', $user);

        SoftDeleteUserAction::handle($user);

        $this->success(__('User archived.'));
    }

    public function restoreUser(int $userId): void
    {
        $user = User::withTrashed()->findOrFail($userId);
        $this->authorize('restore', $user);

        RestoreUserAction::handle($user);

        $this->success(__('User restored.'));
    }

    // ── Pagination hooks ──────────────────────────────────────────────────────

    public function updatedCategories(): void          { $this->resetPage(); }

    public function updatedShowArchived(): void        { $this->resetPage(); }

    public function updatedIncompleteProfile(): void   { $this->resetPage(); }

    public function updatedUnpaidSubscription(): void  { $this->resetPage(); }

    public function updatedHasKey(): void              { $this->resetPage(); }

    public function updatedHasCashRegister(): void     { $this->resetPage(); }

    public function updatedSearch(): void              { $this->resetPage(); }

    public function updatedSelectedLicenceType(): void { $this->resetPage(); }

    // ── Computed ──────────────────────────────────────────────────────────────

    /** @return array<string, int> */
    #[Computed]
    public function stats(): array
    {
        return [
            'total'        => User::count(),
            'registered'   => User::affiliatedForCurrentSeason()->count(),
            'competitive'  => User::competitor()->count(),
            'unregistered' => User::count() - User::affiliatedForCurrentSeason()->count(),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    #[Computed]
    public function subscriptions(): Collection
    {
        return collect([
            ['id' => 'event-1',    'name' => 'Tournoi printemps',     'group' => __('Events')],
            ['id' => 'event-2',    'name' => 'Coupe régionale',       'group' => __('Events')],
            ['id' => 'event-3',    'name' => 'Championnat été',       'group' => __('Events')],
            ['id' => 'training-1', 'name' => 'Entraînement lundi',    'group' => __('Trainings')],
            ['id' => 'training-2', 'name' => 'Entraînement mercredi', 'group' => __('Trainings')],
        ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    #[Computed]
    public function teams(): Collection
    {
        return Team::with('captain')
            ->orderBy('name')
            ->get()
            ->map(fn (Team $team) => [
                'id'     => $team->id,
                'name'   => __('Team') . ' ' . $team->name,
                'avatar' => $team->captain->photo ?? '/images/empty-user.jpg',
            ]);
    }

    /** @return array<int, array<string, mixed>> */
    #[Computed]
    public function headers(): array
    {
        return [
            ['key' => 'photo',          'label' => '',            'sortable' => false],
            ['key' => 'name',           'label' => __('Name'),    'sortable' => true],
            ['key' => 'email',          'label' => __('Email'),   'sortable' => true],
            ['key' => 'is_competitive', 'label' => __('Licence'), 'sortable' => true],
            ['key' => 'ranking',        'label' => __('Ranking'), 'sortable' => true],
        ];
    }

    /** @return array<int, array{id: string, name: string}> */
    #[Computed]
    public function licenceTypes(): array
    {
        return [
            ['id' => 'both',        'name' => __('Both')],
            ['id' => 'competitive', 'name' => __('Competitive')],
            ['id' => 'recreative',  'name' => __('Recreative')],
        ];
    }

    /** @return array<int, array{key: string, label: string}> */
    #[Computed]
    public function filterChips(): array
    {
        return $this->getFilterChips();
    }

    #[Computed]
    public function users(): LengthAwarePaginator
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
                fn ($q) => $q->competitor()
            )
            ->when(
                ! $this->showArchived && $this->selectedLicenceType === 'recreative',
                fn ($q) => $q->whereDoesntHave('subscriptions', fn ($s) => $s
                    ->where('season_id', \App\Domains\Competitions\Interclub\Models\Season::current()?->id)
                    ->where('is_competitive', true)
                )
            )
            ->when(
                $this->categories,
                fn ($q) => $q->whereIn('gender', $this->categories)
            )
            ->when(
                count($this->team_ids) > 0,
                fn ($q) => $q->whereHas(
                    'teams',
                    fn ($teamQuery) => $teamQuery->whereIn('teams.id', $this->team_ids)
                )
            )
            ->when($this->incompleteProfile, fn ($q) => $q->withIncompleteProfile())
            ->when($this->unpaidSubscription, fn ($q) => $q->unpaid())
            ->when($this->hasKey, fn ($q) => $q->where('has_key', true))
            ->when($this->hasCashRegister, fn ($q) => $q->whereHas('heldCashRegisters'))
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate(15);
    }

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
            'users'         => $this->users,
            'headers'       => $this->headers,
            'teams'         => $this->teams,
            'subscriptions' => $this->subscriptions,
            'breadcrumbs'   => $this->getBreadcrumbs(),
            'filterChips'   => $this->filterChips,
            'licenceTypes'  => $this->licenceTypes,
            'stats'         => $this->stats,
        ]);
    }
};
