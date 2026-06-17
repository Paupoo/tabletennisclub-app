<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs, Toast;

    public bool $changeHolderModal = false;

    public bool $createRegisterModal = false;

    #[Rule('required|integer|not_in:0')]
    public int $entryAmount = 0;

    #[Rule('nullable|string|max:500')]
    public ?string $entryNotes = null;

    #[Rule('required|string|in:tournament_payment,training_payment,manual')]
    public string $entryReason = 'manual';

    public bool $manualEntryModal = false;

    #[Rule('nullable|exists:users,id')]
    public ?int $newHolderUserId = null;

    #[Rule('nullable|exists:users,id')]
    public ?int $newRegisterHolderUserId = null;

    #[Rule('required|string|max:100')]
    public string $newRegisterName = 'Caisse principale';

    public ?int $selectedRegisterId = null;

    #[Computed]
    public function balance(): int
    {
        return $this->register?->currentBalance() ?? 0;
    }

    public function confirmChangeHolder(): void
    {
        /** @var User $auth */
        $auth = Auth::user();

        if (! $auth->is_admin && $auth->committee_role !== CommitteeRolesEnum::TREASURER) {
            abort(403);
        }

        $this->validateOnly('newHolderUserId');

        $register = CashRegister::findOrFail($this->selectedRegisterId);
        $register->update(['held_by_user_id' => $this->newHolderUserId]);

        $this->changeHolderModal = false;
        unset($this->register);
        $this->success(__('Holder updated.'));
    }

    public function createRegister(): void
    {
        $this->validateOnly('newRegisterName');
        $this->validateOnly('newRegisterHolderUserId');

        /** @var User $auth */
        $auth = Auth::user();

        if (! $auth->is_admin && $auth->committee_role !== CommitteeRolesEnum::TREASURER) {
            abort(403);
        }

        $register = CashRegister::create([
            'name' => $this->newRegisterName,
            'held_by_user_id' => $this->newRegisterHolderUserId,
        ]);

        $this->selectedRegisterId = $register->id;
        $this->reset(['newRegisterName', 'newRegisterHolderUserId', 'createRegisterModal']);
        unset($this->register, $this->registers);
        $this->success(__('Cash register created.'));
    }

    public function mount(): void
    {
        $register = CashRegister::first();
        $this->selectedRegisterId = $register?->id;
    }

    public function openChangeHolder(): void
    {
        /** @var User $auth */
        $auth = Auth::user();

        if (! $auth->is_admin && $auth->committee_role !== CommitteeRolesEnum::TREASURER) {
            abort(403);
        }

        $this->newHolderUserId = $this->register?->held_by_user_id;
        $this->changeHolderModal = true;
    }

    public function openManualEntry(): void
    {
        $this->reset(['entryAmount', 'entryReason', 'entryNotes']);
        $this->entryReason = 'manual';
        $this->manualEntryModal = true;
    }

    public function reasonOptions(): array
    {
        return [
            ['id' => 'manual',             'name' => __('Manual')],
            ['id' => 'tournament_payment', 'name' => __('Tournament payment')],
            ['id' => 'training_payment',   'name' => __('Training payment')],
        ];
    }

    #[Computed]
    public function register(): ?CashRegister
    {
        if (! $this->selectedRegisterId) {
            return null;
        }

        return CashRegister::with(['entries.recordedBy', 'heldBy'])->find($this->selectedRegisterId);
    }

    #[Computed]
    public function registers(): Illuminate\Database\Eloquent\Collection
    {
        return CashRegister::orderBy('name')->get();
    }

    public function render(): View
    {
        return $this->view([
            'breadcrumbs' => $this->getBreadcrumbs(),
            'reasonOptions' => $this->reasonOptions(),
            'users' => $this->users,
        ]);
    }

    public function saveManualEntry(): void
    {
        $this->validate([
            'entryAmount' => 'required|integer|not_in:0',
            'entryReason' => 'required|string',
            'entryNotes' => 'nullable|string|max:500',
        ]);

        $register = CashRegister::find($this->selectedRegisterId);
        if (! $register) {
            $this->error(__('No cash register selected.'));

            return;
        }

        CashRegisterEntry::create([
            'cash_register_id' => $register->id,
            'amount' => $this->entryAmount * 100,
            'reason' => $this->entryReason,
            'notes' => $this->entryNotes,
            'recorded_by_id' => Auth::id(),
        ]);

        unset($this->register);
        $this->reset(['entryAmount', 'entryReason', 'entryNotes', 'manualEntryModal']);
        $this->success(__('Entry recorded.'));
    }

    #[Computed]
    public function users(): Collection
    {
        return User::where('is_active', true)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->first_name . ' ' . $u->last_name]);
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Treasury — Cash Register'));
    }
};
