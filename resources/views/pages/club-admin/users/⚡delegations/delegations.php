<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Role;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Mary\Traits\Toast;

/**
 * Who holds which duty, across the whole club.
 *
 * The question "why does this person have access to that?" had no single place to
 * be answered: it was spread over four boolean columns, a statutory title and a
 * handful of inline checks. This screen answers it in one view — and, read the
 * other way, tells the committee which duties nobody is covering.
 *
 * Read-only on purpose: assigning happens on the member's own form, so there is
 * one place where a change is made and audited.
 */
new class extends Component
{
    use HasBreadcrumbs, HasFilterDrawer, Toast;

    #[Url]
    public string $delegationFilter = '';

    public string $search = '';

    /** Rows are people rather than duties when a human is what you are after. */
    #[Url]
    public string $view = 'delegations';

    public function clearFilters(): void
    {
        $this->reset(['delegationFilter', 'search']);
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public function getFilterChips(): array
    {
        $chips = [];

        if ($this->delegationFilter !== '' && Role::tryFrom($this->delegationFilter) instanceof Role) {
            $chips[] = [
                'key' => 'delegationFilter',
                'label' => Role::from($this->delegationFilter)->label(),
            ];
        }

        if ($this->search !== '') {
            $chips[] = ['key' => 'search', 'label' => $this->search];
        }

        return $chips;
    }

    public function removeFilter(string $key): void
    {
        $this->reset($key);
    }

    public function render(): View
    {
        return $this->view()->title(__('Delegations'));
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'filterChips' => $this->getFilterChips(),
        ];
    }

    /**
     * Every délégation with its holders — including the ones nobody holds, which
     * are the point of the screen as much as the filled ones.
     *
     * @return Collection<int, array{role: Role, holders: Collection<int, User>}>
     */
    #[Computed]
    public function delegationRows(): Collection
    {
        $holders = $this->holdersByRole();

        return collect(Role::delegations())
            ->filter(fn (Role $role): bool => $this->delegationFilter === '' || $this->delegationFilter === $role->value)
            ->map(fn (Role $role): array => [
                'role' => $role,
                'holders' => $holders->get($role->value, collect()),
            ])
            ->values();
    }

    /**
     * The same data seen from the members' side.
     *
     * @return Collection<int, array{user: User, roles: Collection<int, Role>}>
     */
    #[Computed]
    public function memberRows(): Collection
    {
        return $this->membersWithRoles()
            ->map(fn (User $user): array => [
                'user' => $user,
                'roles' => $user->roles
                    ->map(fn ($role): ?Role => Role::tryFrom($role->name))
                    ->filter(fn (?Role $role): bool => $role?->isDelegation() ?? false)
                    ->sortBy(fn (Role $role): string => $role->label())
                    ->values(),
            ])
            ->filter(fn (array $row): bool => $row['roles']->isNotEmpty())
            ->values();
    }

    /**
     * Duties nobody covers — the reason a committee opens this screen.
     *
     * @return Collection<int, Role>
     */
    #[Computed]
    public function uncoveredDelegations(): Collection
    {
        $holders = $this->holdersByRole();

        return collect(Role::delegations())
            ->filter(fn (Role $role): bool => $holders->get($role->value, collect())->isEmpty())
            ->values();
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    #[Computed]
    public function delegationOptions(): array
    {
        return array_map(static fn (Role $role): array => [
            'id' => $role->value,
            'name' => $role->label(),
        ], Role::delegations());
    }

    public function committeeTitle(User $user): ?CommitteeRolesEnum
    {
        return $user->committee_role;
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->users()
            ->current(__('Delegations'));
    }

    /**
     * @return Collection<string, Collection<int, User>>
     */
    private function holdersByRole(): Collection
    {
        $byRole = collect();

        foreach ($this->membersWithRoles() as $user) {
            foreach ($user->roles as $role) {
                $byRole->put(
                    $role->name,
                    $byRole->get($role->name, collect())->push($user),
                );
            }
        }

        return $byRole;
    }

    /**
     * @return Collection<int, User>
     */
    private function membersWithRoles(): Collection
    {
        return once(fn (): Collection => User::query()
            ->with('roles')
            ->whereHas('roles', fn ($q) => $q->whereNotIn('name', [
                Role::ADMINISTRATOR->value,
                Role::COMMITTEE->value,
            ]))
            ->when($this->search !== '', fn ($q) => $q->searchName($this->search))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get());
    }
};
