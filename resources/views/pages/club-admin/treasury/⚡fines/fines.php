<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Fines\Actions\CancelFine;
use App\Domains\ClubAdmin\Fines\Actions\IssueFine;
use App\Domains\ClubAdmin\Fines\Models\Fine;
use App\Domains\ClubAdmin\Fines\Services\FineCreditor;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\FineReason;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Rules\ValidIban;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs, HasFilterDrawer, Toast, WithPagination;

    public ?float $amount = null;

    public ?int $cancelFineId = null;

    // ── Cancel modal
    public bool $cancelModal = false;

    public string $contactEmail = '';

    public string $contactName = '';

    public string $contactPhone = '';

    // ── Provincial committee drawer
    public bool $creditorDrawer = false;

    public string $creditorIban = '';

    public string $creditorName = '';

    public string $eventDate = '';

    public string $eventLabel = '';

    // ── Issue drawer
    public bool $fineDrawer = false;

    public ?int $memberId = null;

    /** @var array<int, array{id: int, name: string}> Options of the searchable member picker. */
    public array $memberOptions = [];

    /** Set once the committee edits the message, so we stop overwriting it. */
    public bool $messageEdited = false;

    public string $paymentDeadline = '';

    public string $pedagogicalMessage = '';

    public string $reason = '';

    #[Url]
    public string $reasonFilter = '';

    public function cancelFine(CancelFine $cancelFine): void
    {
        Gate::authorize(Permission::FinesCancel->value);

        $fine = Fine::with('payment', 'user.guardians')->findOrFail($this->cancelFineId);

        // A fine from before the direct-payment rule may carry a payment the club
        // collected — block it here (the action guards this too).
        if ($fine->payment?->status === 'paid') {
            $this->cancelModal = false;
            $this->cancelFineId = null;
            $this->error(__('This fine has already been paid — it cannot be cancelled here.'));

            return;
        }

        $cancelFine($fine);

        $this->cancelModal = false;
        $this->cancelFineId = null;
        unset($this->fines);
        $this->success(__('Fine cancelled. The member has been notified.'));
    }

    /**
     * The fine currently targeted by the cancellation modal, for its recap.
     */
    #[Computed]
    public function cancelTarget(): ?Fine
    {
        return $this->cancelFineId
            ? Fine::with('user')->find($this->cancelFineId)
            : null;
    }

    public function clearFilters(): void
    {
        $this->reset(['reasonFilter']);
        $this->resetPage();
    }

    public function confirmCancel(int $fineId): void
    {
        Gate::authorize(Permission::FinesCancel->value);

        $this->cancelFineId = $fineId;
        $this->cancelModal = true;
    }

    #[Computed]
    public function creditor(): FineCreditor
    {
        return app(FineCreditor::class);
    }

    /**
     * @return LengthAwarePaginator<int, Fine>
     */
    #[Computed]
    public function fines(): LengthAwarePaginator
    {
        return Fine::query()
            ->with(['user', 'issuer', 'payment'])
            ->when($this->reasonFilter, fn (EloquentBuilder $q) => $q->where('reason', $this->reasonFilter))
            ->latest()
            ->orderByDesc('fines.id')
            ->paginate(20);
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public function getFilterChips(): array
    {
        return array_values(array_filter([
            $this->reasonFilter !== '' ? [
                'key' => 'reasonFilter',
                'label' => FineReason::tryFrom($this->reasonFilter)?->label() ?? $this->reasonFilter,
            ] : null,
        ]));
    }

    public function issueFine(IssueFine $issueFine): void
    {
        Gate::authorize(Permission::FinesIssue->value);

        if (! $this->creditor->isConfigured()) {
            $this->fineDrawer = false;
            $this->error(__('Enter the provincial committee account before issuing a fine.'));

            return;
        }

        $this->validate();

        $issueFine(
            User::findOrFail($this->memberId),
            Auth::user(),
            FineReason::from($this->reason),
            (float) $this->amount,
            $this->pedagogicalMessage,
            Carbon::parse($this->eventDate),
            trim($this->eventLabel),
            Carbon::parse($this->paymentDeadline),
            FineReason::from($this->reason)->provincialCode(),
        );

        $this->fineDrawer = false;
        unset($this->fines);
        $this->success(__('Fine issued and the member has been notified.'));
    }

    public function mount(): void
    {
        Gate::authorize(Permission::FinesView->value);

        // The picker's first options belong in the first render. Filled only
        // when the drawer opened, they were morphed in outside the picker's
        // Alpine scope: `isActive is not defined`, and a click on a name did
        // nothing until something was typed.
        $this->search();

        // Deep link from a member row: /admin/treasury/fines?member=123
        if ($memberId = request()->integer('member')) {
            $this->openFineDrawer($memberId);
        }
    }

    public function openCreditorDrawer(): void
    {
        Gate::authorize(Permission::FinesIssue->value);

        $creditor = $this->creditor;
        $this->creditorName = $creditor->name() ?? '';
        $this->creditorIban = $creditor->ibanFormatted() ?? '';
        $this->contactName = $creditor->contactName() ?? '';
        $this->contactEmail = $creditor->contactEmail() ?? '';
        $this->contactPhone = $creditor->contactPhone() ?? '';
        $this->resetValidation();
        $this->creditorDrawer = true;
    }

    public function openFineDrawer(?int $memberId = null): void
    {
        Gate::authorize(Permission::FinesIssue->value);

        // Without the committee's account the mail could not say where to pay:
        // send the treasurer to it first rather than let them fill a form in vain.
        if (! $this->creditor->isConfigured()) {
            $this->openCreditorDrawer();

            return;
        }

        $this->reset(['amount', 'eventDate', 'eventLabel', 'paymentDeadline', 'pedagogicalMessage', 'reason', 'messageEdited']);
        $this->resetValidation();
        $this->memberId = $memberId;
        $this->reason = FineReason::UNANNOUNCED_ABSENCE->value;
        $this->pedagogicalMessage = $this->suggestedMessage();
        $this->search();
        $this->fineDrawer = true;
    }

    public function resetMessage(): void
    {
        $this->messageEdited = false;
        $this->pedagogicalMessage = $this->suggestedMessage();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'memberId' => ['required', 'exists:users,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'in:' . implode(',', array_column(FineReason::cases(), 'value'))],
            'eventDate' => ['required', 'date'],
            'eventLabel' => ['required', 'string', 'max:255'],
            'paymentDeadline' => ['required', 'date', 'after_or_equal:eventDate'],
            'pedagogicalMessage' => ['required', 'string', 'min:10'],
        ];
    }

    public function saveCreditor(FineCreditor $creditor): void
    {
        Gate::authorize(Permission::FinesIssue->value);

        $this->validate([
            'creditorName' => ['required', 'string', 'max:70'],
            'creditorIban' => ['required', 'string', 'max:50', new ValidIban],
            'contactName' => ['nullable', 'string', 'max:255'],
            'contactEmail' => ['nullable', 'email', 'max:255'],
            'contactPhone' => ['nullable', 'string', 'max:50'],
        ]);

        $creditor->update($this->creditorName, $this->creditorIban, $this->contactName, $this->contactEmail, $this->contactPhone);

        unset($this->creditor);
        $this->creditorDrawer = false;
        $this->success(__('Provincial committee details saved.'));
    }

    /**
     * Feeds the searchable member picker (maryUI x-choices calls this method).
     * Reuses the compound-name scope, so "Jean Van" finds "Jean-Pierre Van
     * Oudenhove". The selected member is always kept in the list so the choice
     * stays visible once picked.
     */
    public function search(string $value = ''): void
    {
        $selected = $this->memberId
            ? User::whereKey($this->memberId)->get(['id', 'first_name', 'last_name'])
            : new Collection;

        $this->memberOptions = User::query()
            ->when($value, fn (EloquentBuilder $q) => $q->searchName($value))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->take(10)
            ->get(['id', 'first_name', 'last_name'])
            ->merge($selected)
            ->unique('id')
            ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->full_name])
            ->values()
            ->all();
    }

    public function updated(string $property): void
    {
        if ($property === 'pedagogicalMessage') {
            $this->messageEdited = true;

            return;
        }

        // Keep the suggestion in sync until the committee takes over the wording.
        if (in_array($property, ['memberId', 'reason', 'amount'], true) && ! $this->messageEdited) {
            $this->pedagogicalMessage = $this->suggestedMessage();
        }
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'filterChips' => $this->getFilterChips(),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Fines'));
    }

    /**
     * A kind, ready-to-edit message: states the facts, then explains how to
     * avoid it next time. The committee can rewrite it entirely.
     */
    private function suggestedMessage(): string
    {
        $member = $this->memberId ? User::find($this->memberId) : null;
        $reason = $this->reason ? FineReason::tryFrom($this->reason) : null;

        $lines = [
            __('Hello :name,', ['name' => $member?->first_name ?? '']),
            __('The provincial committee has issued a fine concerning you (:reason). The club passes it on, but we mainly want to help you avoid it next time.', [
                'reason' => $reason?->label() ?? '—',
            ]),
            __('A quick message to your captain or the committee as soon as you know about a problem is usually enough to avoid this kind of situation.'),
            __('How to pay the committee is explained below. The club stays available if you want to talk about it.'),
        ];

        return implode("\n\n", $lines);
    }
};
