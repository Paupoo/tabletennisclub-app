<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Enums\Role;
use App\Domains\Shared\Support\IbanNormalizer;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/*
 * The member file, read-only.
 *
 * Kept apart from the edit form on purpose: that form never sends the data half
 * to whoever may not write it, and a read mode grafted onto it would have to
 * re-guard every field it binds. Reading and writing are two screens, answering
 * to two permissions.
 */
new class extends Component
{
    use HasBreadcrumbs;

    public User $user;

    /**
     * The bank account as this reader may see it.
     *
     * Nobody reads an IBAN for transparency: it is needed to pay a refund, or
     * kept up to date by whoever edits the file. Everyone else gets enough of it
     * to recognise it.
     */
    /**
     * The affiliation of the running season, if the member asked for one.
     */
    #[Computed]
    public function currentSubscription(): ?Subscription
    {
        $season = Season::current();

        return $season === null ? null : $this->user->subscriptions()
            ->where('season_id', $season->id)
            ->latest('id')
            ->first();
    }

    #[Computed]
    public function displayedIban(): ?string
    {
        $reader = auth()->user();

        return $reader?->can(Permission::UsersUpdate->value) || $reader?->can(Permission::PaymentsRefund->value)
            ? IbanNormalizer::format($this->user->iban)
            : IbanNormalizer::mask($this->user->iban);
    }

    /**
     * The délégations the member holds, in reading order.
     *
     * @return array<int, Role>
     */
    #[Computed]
    public function heldDelegations(): array
    {
        $names = $this->user->getRoleNames()->all();

        return array_values(array_filter(
            Role::delegationsInReadingOrder(),
            static fn (Role $role): bool => in_array($role->value, $names, true),
        ));
    }

    /**
     * Whether the edit form opens for this reader: it holds two halves, the data
     * and the rights, and writing either one is reason enough to go there.
     */
    #[Computed]
    public function mayEdit(): bool
    {
        return Gate::allows('update', $this->user) || Gate::allows('manageAccess', $this->user);
    }

    public function mount(User $user): void
    {
        // Defense in depth: the route already gates this.
        abort_unless(auth()->user()?->can(Permission::UsersView->value), 403);

        $this->user = $user;
    }

    public function render(): View
    {
        return $this->view()->title($this->user->full_name);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->users()
            ->current($this->user->full_name);
    }
};
