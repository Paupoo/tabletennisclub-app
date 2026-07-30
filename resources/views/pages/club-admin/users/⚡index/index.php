<?php

declare(strict_types=1);

use App\Actions\User\AnonymizeUserAction;
use App\Actions\User\CreateUserAction;
use App\Actions\User\RecalculateForceListAction;
use App\Actions\User\RestoreUserAction;
use App\Actions\User\SendInvitationAction;
use App\Jobs\SendMemberInvitationJob;
use App\Actions\User\SoftDeleteUserAction;
use App\Data\User\CreateUserData;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\Gender;
use App\Domains\Shared\Enums\Permission;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasBulkActions;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule as ValidationRule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs, Toast, WithPagination;
    use HasBulkActions, HasFilterDrawer;

    public bool $addToTeamModal = false;

    public string $anonymizeConfirmText = '';

    // ── Anonymize modal ───────────────────────────────────────────────────────
    public bool $anonymizeModal = false;

    public ?int $anonymizeUserId = null;

    /** @var array<int, string> */
    #[Url]
    public array $categories = [];

    public bool $confirmArchiveModal = false;

    /** Guards against silently invalidating an invitation somebody is holding. */
    public bool $confirmReinviteModal = false;

    // ── Modals ───────────────────────────────────────────────────────────────
    public bool $deleteModal = false;

    #[Url]
    public bool $hasCashRegister = false;

    #[Url]
    public bool $hasKey = false;

    #[Url]
    public bool $incompleteProfile = false;

    /** The grown members the club still cannot hand a login to. */
    #[Url]
    public bool $adultWithoutAddress = false;

    /**
     * Where a member stands with their account, mirroring
     * {@see User::invitationStatus()}. This is how the secretary finds the
     * members an import brought in and has not written to yet.
     */
    #[Url]
    public string $invitationState = '';

    public string $inviteEmail = '';

    public string $inviteFirstName = '';

    public string $inviteLastName = '';

    // ── Quick invite ─────────────────────────────────────────────────────────
    public bool $quickInviteDrawer = false;

    // ── Filters & sort ───────────────────────────────────────────────────────
    #[Url]
    public string $search = '';

    #[Url]
    public string $selectedLicenceType = 'both';

    #[Url]
    public bool $showArchived = false;

    /** @var array{column: string, direction: string} */
    #[Url]
    public array $sortBy = ['column' => 'last_name', 'direction' => 'asc'];

    public bool $subscribeModal = false;

    public ?string $subscription_id = null;

    public ?int $team_id = null;

    /** @var array<int, int> */
    #[Url]
    public array $team_ids = [];

    #[Url]
    public bool $unpaidSubscription = false;

    public ?int $userToDelete = null;

    /** How many of the selected members are still holding a live invitation. */
    public int $waitingOnInvitation = 0;

    /**
     * Whitelist of sortable columns. Maps the header key (which may be a virtual
     * attribute such as `name`) to the real database columns to order by. Any key
     * not listed here — including a tampered `sortBy` URL value — falls back to a
     * safe default instead of reaching `orderBy()` with a raw, unknown column.
     *
     * @var array<string, array<int, string>>
     */
    protected array $sortableColumns = [
        'name' => ['first_name', 'last_name'],
        'last_name' => ['last_name', 'first_name'],
        'email' => ['email'],
        'is_competitive' => ['is_competitive'],
        'ranking' => ['ranking'],
    ];

    // ── Bulk actions ──────────────────────────────────────────────────────────

    public function bulkAddToTeam(): void
    {
        Gate::authorize(Permission::UsersUpdate->value);

        if (! $this->team_id) {
            return;
        }

        // TODO: real logic when Team model is complete
        $this->team_id = null;
        $this->addToTeamModal = false;
        $this->clearSelection();
        $this->success(__('Users added to the team.'));
    }

    public function bulkArchive(): void
    {
        Gate::authorize(Permission::UsersDelete->value);

        $selfIncluded = in_array((string) Auth::id(), array_map('strval', $this->selected));

        User::whereIn('id', $this->selected)
            ->where('id', '!=', Auth::id())
            ->get()
            // Members with an unresolved subscription for the active season are
            // skipped: archiving them would silently orphan a live subscription.
            ->filter(fn (User $user): bool => ! $user->isAffiliatedForCurrentSeason())
            ->each(fn (User $user) => SoftDeleteUserAction::handle($user));

        $this->confirmArchiveModal = false;
        $this->clearSelection();

        if ($selfIncluded) {
            $this->warning(__('Users archived. Your own account was excluded from the selection.'));
        } else {
            $this->success(__('Selected users archived.'));
        }
    }

    /**
     * Hand a whole selection of members their login.
     *
     * The gesture the import deliberately does not make. It comes after it, by
     * a human, on a selection they filtered themselves — usually the members a
     * listing brought in, sitting under "not invited".
     */
    public function bulkInvite(): void
    {
        Gate::authorize('sendEmail', User::class);

        $this->dispatchInvitations(includeWaiting: false);
    }

    public function bulkSubscribe(): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        if (! $this->subscription_id) {
            return;
        }

        // TODO: real logic when Event/Training models are complete
        $this->subscription_id = null;
        $this->subscribeModal = false;
        $this->clearSelection();
        $this->success(__('Users subscribed.'));
    }

    public function clearFilters(): void
    {
        $this->selectedLicenceType = 'both';
        $this->categories = [];
        $this->invitationState = '';
        $this->incompleteProfile = false;
        $this->adultWithoutAddress = false;
        $this->unpaidSubscription = false;
        $this->hasKey = false;
        $this->hasCashRegister = false;
        $this->team_ids = [];
        $this->showArchived = false;
        $this->resetPage();
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

    public function confirmBulkArchive(): void
    {
        Gate::authorize(Permission::UsersDelete->value);

        $this->confirmArchiveModal = true;
    }

    /**
     * Send again to the members who were already waiting on a first invitation.
     */
    public function confirmBulkInvite(): void
    {
        Gate::authorize('sendEmail', User::class);

        $this->dispatchInvitations(includeWaiting: true);
    }

    /**
     * Only opens the confirmation modal — deliberately not authorized on the
     * instance, so that archiving one's own account still reaches the friendly
     * message in {@see self::delete()} rather than a 403.
     */
    public function confirmDelete(int $userId): void
    {
        Gate::authorize(Permission::UsersDelete->value);

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

        try {
            SoftDeleteUserAction::handle($user);
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->success(__('User archived.'));
    }

    /** @return array<int, array{key: string, label: string}> */
    #[Computed]
    public function filterChips(): array
    {
        return $this->getFilterChips();
    }

    // ── HasFilterDrawer ───────────────────────────────────────────────────────

    /** @return array<int, array{key: string, label: string}> */
    public function getFilterChips(): array
    {
        $chips = [];

        if ($this->selectedLicenceType !== 'both') {
            $chips[] = [
                'key' => 'selectedLicenceType',
                'label' => $this->selectedLicenceType === 'competitive' ? __('Competitive') : __('Recreational'),
            ];
        }

        foreach (Gender::cases() as $gender) {
            if (in_array($gender->value, $this->categories, true)) {
                $chips[] = [
                    'key' => "categories_{$gender->value}",
                    'label' => $gender->getLabel(),
                ];
            }
        }

        if ($this->invitationState !== '') {
            $chips[] = [
                'key' => 'invitationState',
                'label' => match ($this->invitationState) {
                    'not_invited' => __('Not invited'),
                    'pending' => __('Pending'),
                    'expired' => __('Expired'),
                    default => __('Account created'),
                },
            ];
        }

        if ($this->adultWithoutAddress) {
            $chips[] = ['key' => 'adultWithoutAddress', 'label' => __('Adult without an address')];
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
                'key' => 'team_ids',
                'label' => trans_choice('{1} 1 team|[2,*] :count teams', count($this->team_ids), ['count' => count($this->team_ids)]),
            ];
        }

        if ($this->showArchived) {
            $chips[] = ['key' => 'showArchived', 'label' => __('Archived')];
        }

        return $chips;
    }

    public function getTotalMatchingCount(): int
    {
        return $this->users->total();
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

    /**
     * Where a member stands with their account, in the order they travel through
     * it. "Not invited" is the one that matters after an import: those are the
     * members the club recorded and has not written to yet.
     *
     * @return array<int, array{id: string, name: string}>
     */
    #[Computed]
    public function invitationStates(): array
    {
        return [
            ['id' => '',            'name' => __('All')],
            ['id' => 'not_invited', 'name' => __('Not invited')],
            ['id' => 'pending',     'name' => __('Pending')],
            ['id' => 'expired',     'name' => __('Expired')],
            ['id' => 'active',      'name' => __('Account created')],
        ];
    }

    public function openAnonymizeModal(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->authorize('anonymize', $user);

        $this->anonymizeUserId = $userId;
        $this->anonymizeConfirmText = '';
        $this->anonymizeModal = true;
    }

    public function quickInvite(): void
    {
        // Creating the member and inviting them are one gesture here, so the
        // stricter of the two rights is the one asked for.
        Gate::authorize('create', User::class);

        $this->validate([
            'inviteFirstName' => ['required', 'string', 'max:255'],
            'inviteLastName' => ['required', 'string', 'max:255'],
            'inviteEmail' => ['required', 'email', ValidationRule::unique('users', 'email')],
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

    // ── Single-record actions ─────────────────────────────────────────────────

    public function recalculateForceList(): void
    {
        Gate::authorize('setOrUpdateForceList', User::class);

        RecalculateForceListAction::handle();

        $this->success(__('Force list recalculated.'));
    }

    public function removeFilter(string $key): void
    {
        if (str_starts_with($key, 'categories_')) {
            $value = substr($key, strlen('categories_'));
            $this->categories = array_values(
                array_filter($this->categories, fn (string $v) => $v !== $value)
            );
        } else {
            $this->reset([$key]);
        }

        $this->resetPage();
    }

    public function render()
    {
        return $this->view([
            'users' => $this->users,
            'headers' => $this->headers,
            'teams' => $this->teams,
            'subscriptions' => $this->subscriptions,
            'breadcrumbs' => $this->getBreadcrumbs(),
            'filterChips' => $this->filterChips,
            'licenceTypes' => $this->licenceTypes,
            'invitationStates' => $this->invitationStates,
            'stats' => $this->stats,
        ]);
    }

    public function restoreUser(int $userId): void
    {
        $user = User::withTrashed()->findOrFail($userId);
        $this->authorize('restore', $user);

        RestoreUserAction::handle($user);

        $this->success(__('User restored.'));
    }

    public function sendInvitation(int $userId): void
    {
        Gate::authorize('sendEmail', User::class);

        $user = User::findOrFail($userId);

        if (! SendInvitationAction::handle($user)) {
            $this->error(__('This member has no address of their own yet, so they cannot be invited.'));

            return;
        }

        $this->success(__('Invitation sent to :email.', ['email' => $user->email]));
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    /** @return array<string, int> */
    #[Computed]
    public function stats(): array
    {
        return [
            'total' => User::count(),
            'registered' => User::affiliatedForCurrentSeason()->count(),
            'competitive' => User::competitor()->count(),
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
                'id' => $team->id,
                'name' => __('Team') . ' ' . $team->name,
                'avatar' => $team->captain->photo ?? '/images/empty-user.jpg',
            ]);
    }

    // ── Pagination hooks ──────────────────────────────────────────────────────

    public function updatedCategories(): void
    {
        $this->resetPage();
    }

    public function updatedHasCashRegister(): void
    {
        $this->resetPage();
    }

    public function updatedHasKey(): void
    {
        $this->resetPage();
    }

    public function updatedIncompleteProfile(): void
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

    public function updatedShowArchived(): void
    {
        $this->resetPage();
    }

    public function updatedUnpaidSubscription(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function users(): LengthAwarePaginator
    {
        $query = $this->showArchived
            ? User::onlyTrashed()
            : User::query();

        $sortColumns = $this->sortableColumns[$this->sortBy['column']] ?? ['first_name', 'last_name'];

        return $query
            ->when($this->search, fn ($q) => $q->searchName($this->search))
            ->when(
                ! $this->showArchived && $this->selectedLicenceType === 'competitive',
                fn ($q) => $q->competitor()
            )
            ->when(
                // Complément exact de competitor() : une inscription compétitive
                // annulée ne fait plus classer le membre en compétiteur.
                // Volontairement sans filtre d'appartenance : la liste inclut
                // aussi les utilisateurs sans inscription, comme avant.
                ! $this->showArchived && $this->selectedLicenceType === 'recreative',
                fn ($q) => $q->whereDoesntHave('subscriptions', fn ($s) => $s
                    ->where('season_id', Season::current()?->id)
                    ->active()
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
            ->when($this->invitationState !== '', fn ($q) => $q->withInvitationState($this->invitationState))
            ->when($this->incompleteProfile, fn ($q) => $q->withIncompleteProfile())
            ->when($this->adultWithoutAddress, fn ($q) => $q->adultWithoutOwnAddress())
            ->when($this->unpaidSubscription, fn ($q) => $q->unpaid())
            ->when($this->hasKey, fn ($q) => $q->where('has_key', true))
            ->when($this->hasCashRegister, fn ($q) => $q->whereHas('heldCashRegisters'))
            ->tap(function ($query) use ($sortColumns): void {
                foreach ($sortColumns as $column) {
                    $query->orderBy($column, $this->sortBy['direction']);
                }
            })
            ->paginate(15);
    }

    /**
     * Queue the invitations the selection actually calls for, and say out loud
     * who was left out.
     *
     * Three kinds of member are not invited by a bulk send. One who already has
     * an account has nothing to accept. One with no address of their own has
     * nowhere to receive a login — the link would land in a guardian's mailbox
     * and set a password on somebody else's account. And one still holding a
     * valid invitation is only sent a second one on purpose: the new link
     * invalidates the one they may be about to click.
     */
    private function dispatchInvitations(bool $includeWaiting): void
    {
        $members = User::query()->whereIn('id', $this->selected)->get();

        $registered = $members->filter(fn (User $member): bool => $member->invitationStatus() === 'active');
        $unreachable = $members->filter(fn (User $member): bool => $member->invitationStatus() !== 'active' && $member->email === null);
        $waiting = $members->filter(fn (User $member): bool => $member->invitationStatus() === 'pending' && $member->email !== null);

        if (! $includeWaiting && $waiting->isNotEmpty()) {
            $this->waitingOnInvitation = $waiting->count();
            $this->confirmReinviteModal = true;

            return;
        }

        $targets = $members
            ->diff($registered)
            ->diff($unreachable)
            ->when(! $includeWaiting, fn (Collection $ready): Collection => $ready->diff($waiting));

        $this->confirmReinviteModal = false;
        $this->clearSelection();

        if ($targets->isEmpty()) {
            $this->warning(__('Nobody in this selection can be invited.'));

            return;
        }

        Bus::batch(
            $targets->map(fn (User $member): SendMemberInvitationJob => new SendMemberInvitationJob($member->id))->all()
        )->name('invitations')->dispatch();

        $message = __(':count invitation(s) on their way.', ['count' => $targets->count()]);

        if ($unreachable->isNotEmpty()) {
            $message .= ' ' . __(':count member(s) have no address of their own and were not invited.', ['count' => $unreachable->count()]);
        }

        if ($registered->isNotEmpty()) {
            $message .= ' ' . __(':count already have an account.', ['count' => $registered->count()]);
        }

        $this->success($message);
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->users()
            ->current(__('List'));
    }

    // ── HasBulkActions ────────────────────────────────────────────────────────

    /** @return array<int, string> */
    protected function getPageIds(): array
    {
        return $this->users
            ->pluck('id')
            ->map(fn (int $id) => (string) $id)
            ->toArray();
    }
};
