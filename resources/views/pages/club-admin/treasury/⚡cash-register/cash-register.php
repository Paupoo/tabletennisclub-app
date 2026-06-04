<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast, HasBreadcrumbs;

    public bool $manualEntryModal = false;
    public bool $createRegisterModal = false;

    #[Rule('required|integer|not_in:0')]
    public int $entryAmount = 0;

    #[Rule('required|string|in:tournament_payment,training_payment,manual')]
    public string $entryReason = 'manual';

    #[Rule('nullable|string|max:500')]
    public ?string $entryNotes = null;

    #[Rule('required|string|max:100')]
    public string $newRegisterName = 'Caisse principale';

    public ?int $selectedRegisterId = null;

    public function mount(): void
    {
        $register = CashRegister::first();
        $this->selectedRegisterId = $register?->id;
    }

    public function createRegister(): void
    {
        $this->validateOnly('newRegisterName');

        CashRegister::create(['name' => $this->newRegisterName]);

        $this->reset(['newRegisterName', 'createRegisterModal']);
        unset($this->register);
        $this->success(__('Cash register created.'));
    }

    public function openManualEntry(): void
    {
        $this->reset(['entryAmount', 'entryReason', 'entryNotes']);
        $this->entryReason = 'manual';
        $this->manualEntryModal = true;
    }

    public function saveManualEntry(): void
    {
        $this->validate([
            'entryAmount' => 'required|integer|not_in:0',
            'entryReason' => 'required|string',
            'entryNotes'  => 'nullable|string|max:500',
        ]);

        $register = CashRegister::find($this->selectedRegisterId);
        if (! $register) {
            $this->error(__('No cash register selected.'));
            return;
        }

        CashRegisterEntry::create([
            'cash_register_id' => $register->id,
            'amount'           => $this->entryAmount * 100,
            'reason'           => $this->entryReason,
            'notes'            => $this->entryNotes,
            'recorded_by_id'   => Auth::id(),
        ]);

        unset($this->register);
        $this->reset(['entryAmount', 'entryReason', 'entryNotes', 'manualEntryModal']);
        $this->success(__('Entry recorded.'));
    }

    #[Computed]
    public function register(): ?CashRegister
    {
        if (! $this->selectedRegisterId) {
            return null;
        }

        return CashRegister::with(['entries.recordedBy'])->find($this->selectedRegisterId);
    }

    #[Computed]
    public function registers(): \Illuminate\Database\Eloquent\Collection
    {
        return CashRegister::orderBy('name')->get();
    }

    #[Computed]
    public function balance(): int
    {
        return $this->register?->currentBalance() ?? 0;
    }

    public function reasonOptions(): array
    {
        return [
            ['id' => 'manual',             'name' => __('Manual')],
            ['id' => 'tournament_payment', 'name' => __('Tournament payment')],
            ['id' => 'training_payment',   'name' => __('Training payment')],
        ];
    }


    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__("Treasury — Cash Register"));
    }

        public function render(): View
    {
        return $this->view([
            'breadcrumbs' => $this->getBreadcrumbs(),
            'reasonOptions' => $this->reasonOptions(),
        ]);
    }
};
