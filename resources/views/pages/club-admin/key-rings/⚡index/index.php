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
     * Active members, plus whoever currently holds a ring so a stale holder
     * never silently vanishes from the list they appear in.
     *
     * @return array<int, array{id: int, name: string}>
     */
    #[Computed]
    public function eligibleHolders(): array
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

    public function moveKeyRing(): void
    {
        Gate::authorize(Permission::EquipmentHolderUpdate->value);

        $this->validateOnly('targetHolderUserId');

        $keyRing = KeyRing::findOrFail($this->selectedKeyRingId);

        // Eligibility mirrors the cash register screen: an entrusted object goes
        // to someone who is still a member. Handing it back to the drawer is
        // always allowed.
        if ($this->targetHolderUserId !== null && ! $this->isEligibleHolder($this->targetHolderUserId)) {
            $this->addError('targetHolderUserId', __('This member is not active.'));

            return;
        }

        $keyRing->update(['held_by_user_id' => $this->targetHolderUserId]);

        $this->reset(['targetHolderUserId', 'selectedKeyRingId', 'moveModal']);
        unset($this->keyRings);

        $this->success(__(':ring has been moved.', ['ring' => $keyRing->label()]));
    }

    public function openCreate(): void
    {
        Gate::authorize(Permission::EquipmentHolderUpdate->value);

        $this->reset(['newHolderUserId', 'newNotes']);
        $this->createModal = true;
    }

    public function openMove(int $keyRingId): void
    {
        Gate::authorize(Permission::EquipmentHolderUpdate->value);

        $this->selectedKeyRingId = $keyRingId;
        $this->targetHolderUserId = KeyRing::findOrFail($keyRingId)->held_by_user_id;
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
            'eligibleHolders' => $this->eligibleHolders,
        ]);
    }

    public function restoreKeyRing(int $keyRingId): void
    {
        Gate::authorize(Permission::EquipmentHolderUpdate->value);

        $keyRing = KeyRing::onlyTrashed()->findOrFail($keyRingId);
        $keyRing->restore();

        unset($this->keyRings);

        $this->success(__(':ring is back in service.', ['ring' => $keyRing->label()]));
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

    // ── Render ────────────────────────────────────────────────────────────────

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Key rings'));
    }

    private function isEligibleHolder(int $userId): bool
    {
        return collect($this->eligibleHolders)->contains('id', $userId);
    }
};
