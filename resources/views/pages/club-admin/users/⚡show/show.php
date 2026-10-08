<?php

declare(strict_types=1);

use App\Actions\User\CancelMemberDepartureAction;
use App\Actions\User\DeclareMemberDepartureAction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\DepartureReason;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Enums\Role;
use App\Domains\Shared\Support\IbanNormalizer;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;

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
    use HasBreadcrumbs, Toast;

    public bool $cancelDepartureModal = false;

    public string $departureLeftOn = '';

    // ── Departure ─────────────────────────────────────────────────────────────
    public bool $departureModal = false;

    public string $departureNote = '';

    public string $departureReason = '';

    public User $user;

    /**
     * Take back a departure recorded by mistake. The team places it handed
     * back are not restored — somebody may have taken them since — and the
     * toast says so, so nobody waits for them to come back.
     */
    public function cancelDeparture(): void
    {
        Gate::authorize(Permission::UsersUpdate->value);

        CancelMemberDepartureAction::handle($this->user);

        $this->user->refresh();
        $this->cancelDepartureModal = false;

        $this->success(__('Departure cancelled. Team places, captaincies and places in upcoming interclub matches are not given back: assign them again if needed.'));
    }

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

    /**
     * Record the departure filled in the modal, for the running season.
     */
    public function declareDeparture(): void
    {
        Gate::authorize(Permission::UsersUpdate->value);

        $this->validate([
            'departureReason' => ['required', Rule::enum(DepartureReason::class)],
            'departureLeftOn' => ['required', 'date'],
            'departureNote' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $outcome = DeclareMemberDepartureAction::handle(
                $this->user,
                Carbon::parse($this->departureLeftOn),
                DepartureReason::from($this->departureReason),
                $this->departureNote,
                auth()->user(),
            );
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->user->refresh();
        $this->reset(['departureModal', 'departureReason', 'departureNote']);

        $message = __(':name is marked as left.', ['name' => $this->user->full_name]);

        if ($outcome->teamsWithoutCaptain !== []) {
            $freedTeams = $outcome->teamsWithoutCaptain;
            sort($freedTeams);
            $message .= ' ' . __('Teams left without a captain: :teams', ['teams' => implode(', ', $freedTeams)]);
        }

        $this->success($message . $outcome->fixturesNotice());
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

    public function openDepartureModal(): void
    {
        Gate::authorize(Permission::UsersUpdate->value);

        $this->resetValidation();
        $this->departureLeftOn = today()->toDateString();
        $this->departureModal = true;
    }

    /**
     * The season the "to follow up" wards were last affiliated to.
     */
    #[Computed]
    public function previousSeason(): ?Season
    {
        return Season::current()?->previous();
    }

    /** A guardian sheet was corrected from this file: the next render reads it again. */
    #[On('guardian-updated')]
    public function refreshGuardians(): void
    {
        $this->user->unsetRelation('guardians');
    }

    public function render(): View
    {
        return $this->view()->title($this->user->full_name);
    }

    /**
     * The members this account answers for, when it holds a guardian sheet —
     * each with the facts their status is read from, in the same query.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function wards(): Collection
    {
        return $this->user->wards();
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
