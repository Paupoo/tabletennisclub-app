<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Fines\Actions\IssueFine;
use App\Domains\ClubAdmin\Fines\Models\Fine;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\FineReason;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs, HasFilterDrawer, Toast, WithPagination;

    public ?float $amount = null;

    public string $description = '';

    public string $federationReference = '';

    // ── Issue drawer
    public bool $fineDrawer = false;

    public ?int $memberId = null;

    /** @var array<int, array{id: int, name: string}> Options of the searchable member picker. */
    public array $memberOptions = [];

    /** Set once the committee edits the message, so we stop overwriting it. */
    public bool $messageEdited = false;

    public string $pedagogicalMessage = '';

    public string $reason = '';

    #[Url]
    public string $statusFilter = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->canManageFinances(), 403);

        // Deep link from a member row: /admin/treasury/fines?member=123
        if ($memberId = request()->integer('member')) {
            $this->openFineDrawer($memberId);
        }
    }

    /**
     * @return LengthAwarePaginator<int, Fine>
     */
    #[Computed]
    public function fines(): LengthAwarePaginator
    {
        return Fine::query()
            ->with(['user', 'issuer', 'payment'])
            ->when($this->statusFilter, fn (EloquentBuilder $q) => $q->whereHas(
                'payment',
                fn (EloquentBuilder $p) => $p->where('status', $this->statusFilter)
            ))
            ->latest()
            ->paginate(20);
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

    public function openFineDrawer(?int $memberId = null): void
    {
        abort_unless(Auth::user()->canManageFinances(), 403);

        $this->reset(['amount', 'description', 'federationReference', 'pedagogicalMessage', 'reason', 'messageEdited']);
        $this->memberId = $memberId;
        $this->reason = FineReason::UNJUSTIFIED_ABSENCE->value;
        $this->pedagogicalMessage = $this->suggestedMessage();
        $this->search();
        $this->fineDrawer = true;
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
            'reason' => ['required', 'string', 'in:'.implode(',', array_column(FineReason::cases(), 'value'))],
            'pedagogicalMessage' => ['required', 'string', 'min:10'],
            'federationReference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function issueFine(IssueFine $issueFine): void
    {
        abort_unless(Auth::user()->canManageFinances(), 403);

        $this->validate();

        $issueFine(
            User::findOrFail($this->memberId),
            Auth::user(),
            FineReason::from($this->reason),
            (float) $this->amount,
            $this->pedagogicalMessage,
            $this->federationReference ?: null,
            $this->description ?: null,
        );

        $this->fineDrawer = false;
        unset($this->fines);
        $this->success(__('Fine issued and the member has been notified.'));
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public function getFilterChips(): array
    {
        return array_values(array_filter([
            $this->statusFilter !== '' ? [
                'key' => 'statusFilter',
                'label' => $this->statusFilter === 'paid' ? __('Paid') : __('Pending'),
            ] : null,
        ]));
    }

    public function clearFilters(): void
    {
        $this->reset(['statusFilter']);
        $this->resetPage();
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
            __('The federation has issued a fine concerning you (:reason). The club has to pass it on, but we mainly want to help you avoid it next time.', [
                'reason' => $reason?->label() ?? '—',
            ]),
            __('A quick message to your captain or the committee as soon as you know about a problem is usually enough to avoid this kind of situation.'),
            __('You will find the payment details in your payments space. The committee stays available if you want to talk about it.'),
        ];

        return implode("\n\n", $lines);
    }
};
