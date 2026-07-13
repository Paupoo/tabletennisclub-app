<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\GeneratePaymentQR;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Meetings\Models\MeetingUser;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use HasBreadcrumbs, HasFilterDrawer, WithPagination;

    /** Payable types a member can hold — the whole scope of the hub. */
    private const PAYABLE_TYPES = [
        Subscription::class,
        TournamentRegistration::class,
        MeetingUser::class,
    ];

    public bool $paymentModal = false;

    public ?string $paymentQr = null;

    #[Url]
    public ?int $personFilter = null;

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $typeFilter = '';

    public ?int $selectedPaymentId = null;

    public User $user;

    public function mount(User $user): void
    {
        abort_unless(Auth::user()->is($user), 403);

        $this->user = $user;
    }

    /**
     * Payments for this member and the users they guard, newest first.
     * Scoped strictly to {@see User::payableUserIds()} — never widened by filters.
     *
     * @return LengthAwarePaginator<int, Payment>
     */
    #[Computed]
    public function payments(): LengthAwarePaginator
    {
        $ids = $this->user->payableUserIds();

        return Payment::query()
            ->with(['payable' => fn (MorphTo $m) => $m->morphWith([
                Subscription::class => ['user', 'season'],
                TournamentRegistration::class => ['user', 'tournament'],
                MeetingUser::class => ['user', 'meeting'],
            ])])
            ->whereHasMorph('payable', self::PAYABLE_TYPES, fn ($q) => $q->whereIn('user_id', $ids))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->typeFilter, fn ($q) => $q->where('payable_type', $this->typeFilter))
            ->when($this->personFilter, fn ($q) => $q->whereHasMorph(
                'payable',
                self::PAYABLE_TYPES,
                fn ($q2) => $q2->where('user_id', $this->personFilter)
            ))
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    /**
     * The people whose payments are in scope (self + guarded users), for the
     * person filter. Only meaningful when the member guards someone.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function payableUsers(): Collection
    {
        return User::query()
            ->whereIn('id', $this->user->payableUserIds())
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name']);
    }

    /**
     * @return array<string, string>
     */
    public function typeOptions(): array
    {
        return [
            Subscription::class => __('Subscription'),
            TournamentRegistration::class => __('Tournament'),
            MeetingUser::class => __('Meeting'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function statusOptions(): array
    {
        return [
            'pending' => __('Pending'),
            'paid' => __('Paid'),
        ];
    }

    #[Computed]
    public function ourClub(): ?Club
    {
        return Club::ourClub()->first();
    }

    public function openPaymentModal(int $paymentId): void
    {
        $payment = Payment::with('payable')->findOrFail($paymentId);

        // Never generate a QR for a payment outside this member's scope.
        abort_unless(
            in_array($payment->payable?->user_id, $this->user->payableUserIds(), true),
            403
        );

        $this->selectedPaymentId = $paymentId;
        $this->paymentQr = (new GeneratePaymentQR)($payment);
        $this->paymentModal = true;
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public function getFilterChips(): array
    {
        return array_values(array_filter([
            $this->statusFilter !== '' ? ['key' => 'statusFilter', 'label' => $this->statusOptions()[$this->statusFilter] ?? $this->statusFilter] : null,
            $this->typeFilter !== '' ? ['key' => 'typeFilter', 'label' => $this->typeOptions()[$this->typeFilter] ?? $this->typeFilter] : null,
            $this->personFilter ? [
                'key' => 'personFilter',
                'label' => $this->payableUsers->firstWhere('id', $this->personFilter)?->full_name ?? (string) $this->personFilter,
            ] : null,
        ]));
    }

    public function clearFilters(): void
    {
        $this->reset(['statusFilter', 'typeFilter', 'personFilter']);
        $this->resetPage();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['statusFilter', 'typeFilter', 'personFilter'], true)) {
            $this->resetPage();
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
            ->current(__('My payments'));
    }
};
