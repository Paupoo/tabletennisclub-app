<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\KeyRing;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Permission;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Mary\Traits\Toast;

/**
 * Where the club's key rings are.
 *
 * A row is a ring, not a member: a ring asleep in the drawer has no member to
 * hang from, and it is exactly the row you came here to find. A member holding
 * two rings therefore appears twice, which is the physical truth.
 *
 * Handing a ring over happens here and nowhere else, so there is one place to
 * make the change and one place for the audit log to record it.
 */
new class extends Component
{
    use HasBreadcrumbs, Toast;

    public bool $createModal = false;

    public bool $moveModal = false;

    #[Rule('nullable|exists:users,id')]
    public ?int $newHolderUserId = null;

    #[Rule('nullable|string|max:500')]
    public ?string $newNotes = null;

    public bool $retireModal = false;

    /** The ring a modal is currently about. */
    public ?int $selectedKeyRingId = null;

    /** Retired rings are out of the way until you ask for them. */
    public bool $showRetired = false;

    #[Rule('nullable|exists:users,id')]
    public ?int $targetHolderUserId = null;

    // ── Actions ───────────────────────────────────────────────────────────────

    public function createKeyRing(): void
    {
        Gate::authorize(Permission::EquipmentHolderUpdate->value);

        $this->validateOnly('newHolderUserId');
        $this->validateOnly('newNotes');

        $keyRing = KeyRing::create([
            'held_by_user_id' => $this->newHolderUserId,
            'notes' => $this->newNotes,
        ]);

        $this->reset(['newHolderUserId', 'newNotes', 'createModal']);
        unset($this->keyRings);

        $this->success(__(':ring has been created.', ['ring' => $keyRing->label()]));
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    /**
     * Everyone a ring may be handed to, filtered in the browser.
     *
     * Active members and nobody else. Listing the current holder too was tried
     * and was worse: a holder who has left the club would be offered and then
     * refused on submit, which is a door that answers 403. The move modal shows
     * who holds the ring as plain text instead.
     *
     * @return array<int, array{id: int, name: string}>
     */
    #[Computed]
    public function holderOptions(): array
    {
        return User::active()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->first_name . ' ' . $user->last_name,
            ])
            ->all();
    }

    /** @return Collection<int, KeyRing> */
    #[Computed]
    public function keyRings(): Collection
    {
        return KeyRing::with('heldBy')
            ->when($this->showRetired, fn ($query) => $query->withTrashed())
            ->orderBy('number')
            ->get();
    }

    /**
     * Hand the ring to someone — including a ring coming back into service.
     *
     * Putting a ring back is the same question as moving one: who has it now?
     * The two cases that actually happen are a ring lost for good, which never
     * comes back at all, and a ring handed in, which sits in the drawer until
     * somebody else needs it. Neither ends up with the person who had it, so
     * the picker starts empty rather than offering them again.
     */
    public function moveKeyRing(): void
    {
        Gate::authorize(Permission::EquipmentHolderUpdate->value);

        $this->validateOnly('targetHolderUserId');

        $keyRing = KeyRing::withTrashed()->findOrFail($this->selectedKeyRingId);

        // Eligibility mirrors the cash register screen: an entrusted object goes
        // to someone who is still a member. Handing it back to the drawer is
        // always allowed.
        if ($this->targetHolderUserId !== null && ! $this->isEligibleHolder($this->targetHolderUserId)) {
            $this->addError('targetHolderUserId', __('This member is not active.'));

            return;
        }

        $wasRetired = $keyRing->trashed();

        if ($wasRetired) {
            $keyRing->restore();
        }

        $keyRing->update(['held_by_user_id' => $this->targetHolderUserId]);

        $this->reset(['targetHolderUserId', 'selectedKeyRingId', 'moveModal']);
        unset($this->keyRings, $this->selectedKeyRing);

        $this->success($wasRetired
            ? __(':ring is back in service.', ['ring' => $keyRing->label()])
            : __(':ring has been moved.', ['ring' => $keyRing->label()]));
    }

    public function openCreate(): void
    {
        Gate::authorize(Permission::EquipmentHolderUpdate->value);

        $this->reset(['newHolderUserId', 'newNotes']);
        $this->createModal = true;
    }

    /**
     * Opens the holder picker, for a ring in service or one coming back.
     *
     * The target always starts empty: a ring is moved because the person who
     * had it should not have it any more.
     */
    public function openMove(int $keyRingId): void
    {
        Gate::authorize(Permission::EquipmentHolderUpdate->value);

        $this->selectedKeyRingId = $keyRingId;
        $this->targetHolderUserId = null;
        $this->resetErrorBag();
        unset($this->selectedKeyRing);
        $this->moveModal = true;
    }

    public function openRetire(int $keyRingId): void
    {
        Gate::authorize(Permission::EquipmentHolderUpdate->value);

        $this->selectedKeyRingId = $keyRingId;
        $this->retireModal = true;
    }

    public function render(): View
    {
        return $this->view([
            'breadcrumbs' => $this->getBreadcrumbs(),
            'keyRings' => $this->keyRings,
            'holderOptions' => $this->holderOptions,
            'selectedKeyRing' => $this->selectedKeyRing,
            'isComingBack' => $this->selectedKeyRing?->trashed() ?? false,
        ]);
    }

    /**
     * Take a ring out of service without erasing it.
     *
     * The holder stays on the row on purpose: a ring that goes missing is
     * missing *from someone*, and that is the first thing you need to know.
     */
    public function retireKeyRing(): void
    {
        Gate::authorize(Permission::EquipmentHolderUpdate->value);

        $keyRing = KeyRing::findOrFail($this->selectedKeyRingId);
        $keyRing->delete();

        $this->reset(['selectedKeyRingId', 'retireModal']);
        unset($this->keyRings);

        $this->success(__(':ring has been retired.', ['ring' => $keyRing->label()]));
    }

    /** The ring the move or retire modal is about. */
    #[Computed]
    public function selectedKeyRing(): ?KeyRing
    {
        return $this->selectedKeyRingId
            ? KeyRing::withTrashed()->with('heldBy')->find($this->selectedKeyRingId)
            : null;
    }

    // ── Render ────────────────────────────────────────────────────────────────

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Key rings'));
    }

    /**
     * Asks the database, never the picker: the option list is capped at ten
     * rows, so checking against it would reject perfectly eligible members who
     * simply were not on screen.
     */
    private function isEligibleHolder(int $userId): bool
    {
        return User::active()->whereKey($userId)->exists();
    }
};
