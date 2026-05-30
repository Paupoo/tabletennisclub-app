<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\GeneratePaymentQR;
use App\Actions\ClubAdmin\Payments\GeneratePaymentReference;
use App\Actions\ClubAdmin\Subscriptions\ApproveTrainingPacksAction;
use App\Actions\ClubAdmin\Subscriptions\CalculatePriceAction;
use App\Actions\ClubAdmin\Subscriptions\CreateSubscriptionAction;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Mail\PaymentInvitationEmail;
use App\Models\ClubAdmin\Payment\Payment;
use App\Models\ClubAdmin\Subscription\Subscription;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Season;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Subscriptions\Notifications\SubscriptionRejectedNotification;
use App\Domains\Subscriptions\Notifications\TrainingPackRejectedNotification;
use App\Support\Breadcrumb;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast, HasBreadcrumbs;

    public array $familyBasket = [];
    public bool $memberDrawer = false;
    public bool $paymentGenerated = false;
    public bool $reviewModal = false;
    public bool $trainingRequestModal = false;
    public array $paymentData = [];
    public string $search = '';
    public string $searchMember = '';
    public string $statusFilter = '';
    public ?int $selectedSeasonId = null;
    public bool $showFilters = false;
    public ?int $currentRequestId = null;
    public ?int $currentTrainingRequestId = null;

    /** @var int[] Pack IDs that admin wants to approve (pre-checked = all pending) */
    public array $approvedPackIds = [];

    public string $rejectionMessage = '';
    public string $rejectionTemplate = '';

    public bool $refundModal = false;
    public ?int $refundSubscriptionId = null;
    public ?int $refundPackId = null;

    public function mount(): void
    {
        $this->selectedSeasonId = Season::current()?->id;
    }

    #[Computed]
    public function activeFiltersCount(): int
    {
        return filled($this->statusFilter) ? 1 : 0;
    }

    public function resetFilters(): void
    {
        $this->reset(['statusFilter']);
    }

    #[Computed]
    public function seasonOptions(): array
    {
        return Season::orderBy('start_at')
            ->get()
            ->map(fn ($s) => [
                'id'   => $s->id,
                'name' => $s->name . ($s->is_active ? ' ✦' : ''),
            ])
            ->toArray();
    }

    #[Computed]
    public function registrationClosed(): bool
    {
        return ! (Season::current()?->registrations_open ?? false);
    }

    public function addToBasket($userId): void
    {
        $user = User::find($userId);

        $this->familyBasket[$userId] = [
            'name'         => $user->first_name . ' ' . $user->last_name,
            'licence_type' => 'recreative',
            'trainings'    => [],
        ];

        $this->searchMember = '';
    }

    public function approve(): void
    {
        $subscription = Subscription::with(['user', 'trainingPacks'])->find($this->currentRequestId);
        (new CalculatePriceAction)($subscription);
        $subscription->confirm();

        // Approve selected training packs (pending → enrolled)
        if (! empty($this->approvedPackIds)) {
            (new ApproveTrainingPacksAction)($subscription, $this->approvedPackIds);
        }

        // Génère le Payment si aucun n'existe déjà pour cette subscription
        $payment = $subscription->payments()->where('status', 'pending')->first();
        if (! $payment) {
            $payment = $subscription->payments()->create([
                'reference'   => (new GeneratePaymentReference)(),
                'amount_due'  => $subscription->getAmountDue(),
                'amount_paid' => 0,
                'status'      => 'pending',
            ]);
        }

        $this->paymentData = [
            'payment_id'         => $payment->id,
            'reference'          => $payment->reference,
            'amount_due'         => $payment->amount_due,
            'member_name'        => $subscription->user->first_name . ' ' . $subscription->user->last_name,
            'member_email'       => $subscription->user->email,
            'iban'               => 'BE23 7323 3320 8791',
            'bic'                => 'CREGBEBB',
            'beneficiary'        => 'CTT Ottignies-Blocry ASBL',
            'qr_code'            => (new GeneratePaymentQR)($payment),
            'invitation_counter' => $payment->invitation_counter,
        ];

        $this->paymentGenerated = true;
        $this->success(__('Subscription confirmed. Payment information generated.'));
    }

    /**
     * Projected total for a pending (Flux A) affiliation approval.
     * Recalculates as $approvedPackIds changes via wire:model.live.
     */
    #[Computed]
    public function pendingReviewEstimatedTotal(): float
    {
        if (! $this->currentRequestId) {
            return 0.0;
        }

        $subscription = Subscription::with('trainingPacks')->find($this->currentRequestId);
        if (! $subscription) {
            return 0.0;
        }

        $subscriptionPrice = $subscription->is_competitive ? 125.0 : 60.0;
        $approvedPacks = TrainingPack::whereIn('id', $this->approvedPackIds)->get();

        $discountable = $approvedPacks->filter(fn (TrainingPack $p) => $p->allow_discount);
        $fixed = $approvedPacks->filter(fn (TrainingPack $p) => ! $p->allow_discount);

        $familyCount = $subscription->has_other_family_members ? 2 : 1;
        $applyDiscount = $discountable->count() > 1 || $familyCount > 1;

        $trainingTotal = 0.0;
        foreach ($discountable as $pack) {
            $trainingTotal += $applyDiscount
                ? max(0.0, (float) $pack->price - 10.0)
                : (float) $pack->price;
        }
        foreach ($fixed as $pack) {
            $trainingTotal += (float) $pack->price;
        }

        return $subscriptionPrice + $trainingTotal;
    }

    /**
     * Simulates the discount-aware delta for the training request modal preview.
     * Recalculates on every render as $approvedPackIds changes via wire:model.live.
     */
    #[Computed]
    public function trainingRequestEstimatedDelta(): float
    {
        if (! $this->currentTrainingRequestId || empty($this->approvedPackIds)) {
            return 0.0;
        }

        $subscription = Subscription::with('trainingPacks')->find($this->currentTrainingRequestId);
        if (! $subscription) {
            return 0.0;
        }

        $enrolledPacks = $subscription->trainingPacks()->wherePivot('status', 'enrolled')->get();
        $approvedPacks = TrainingPack::whereIn('id', $this->approvedPackIds)->get();
        $allAfter = $enrolledPacks->merge($approvedPacks);

        $discountable = $allAfter->filter(fn (TrainingPack $p) => $p->allow_discount);
        $fixed = $allAfter->filter(fn (TrainingPack $p) => ! $p->allow_discount);

        $familyCount = $subscription->has_other_family_members ? 2 : 1;
        $applyDiscount = $discountable->count() > 1 || $familyCount > 1;

        $trainingTotal = 0.0;
        foreach ($discountable as $pack) {
            $trainingTotal += $applyDiscount
                ? max(0.0, (float) $pack->price - 10.0)
                : (float) $pack->price;
        }
        foreach ($fixed as $pack) {
            $trainingTotal += (float) $pack->price;
        }

        $newAmountDue = (float) $subscription->subscription_price + $trainingTotal;

        return max(0.0, $newAmountDue - (float) $subscription->amount_due);
    }

    /**
     * Per-pack pricing detail for the training request modal, including
     * retroactive discounts triggered on already-enrolled packs.
     *
     * @return array{
     *   apply_discount: bool,
     *   retro_adjustments: array<int, array{name: string, original_price: float, new_price: float}>,
     *   new_packs: array<int, array{full_price: float, effective_price: float, discounted: bool}>
     * }
     */
    #[Computed]
    public function trainingRequestPricingBreakdown(): array
    {
        if (! $this->currentTrainingRequestId) {
            return [];
        }

        $subscription = Subscription::with('trainingPacks')->find($this->currentTrainingRequestId);
        if (! $subscription) {
            return [];
        }

        $enrolledPacks      = $subscription->trainingPacks()->wherePivot('status', 'enrolled')->get();
        $newPacks           = empty($this->approvedPackIds)
            ? collect()
            : TrainingPack::whereIn('id', $this->approvedPackIds)->get();

        $discountableBefore = $enrolledPacks->filter(fn (TrainingPack $p) => $p->allow_discount);
        $discountableNew    = $newPacks->filter(fn (TrainingPack $p) => $p->allow_discount);
        $totalDiscountable  = $discountableBefore->count() + $discountableNew->count();

        $familyCount   = $subscription->has_other_family_members ? 2 : 1;
        $applyDiscount = $totalDiscountable > 1 || $familyCount > 1;
        $hadDiscount   = $discountableBefore->count() > 1 || $familyCount > 1;

        // Enrolled packs that gain a retroactive -10€ discount for the first time
        $retroAdjustments = [];
        if ($applyDiscount && ! $hadDiscount) {
            foreach ($discountableBefore as $pack) {
                $retroAdjustments[$pack->id] = [
                    'name'           => $pack->name,
                    'original_price' => (float) $pack->price,
                    'new_price'      => max(0.0, (float) $pack->price - 10.0),
                ];
            }
        }

        // New packs with their effective (possibly discounted) price
        $newPacksDetail = [];
        foreach ($newPacks as $pack) {
            $full      = (float) $pack->price;
            $effective = ($applyDiscount && $pack->allow_discount) ? max(0.0, $full - 10.0) : $full;
            $newPacksDetail[$pack->id] = [
                'full_price'      => $full,
                'effective_price' => $effective,
                'discounted'      => $effective < $full,
            ];
        }

        return [
            'apply_discount'    => $applyDiscount,
            'retro_adjustments' => $retroAdjustments,
            'new_packs'         => $newPacksDetail,
        ];
    }

    public function approveTrainingRequest(): void
    {
        $subscription = Subscription::with(['user', 'season', 'trainingPacks'])->find($this->currentTrainingRequestId);
        if (! $subscription) {
            return;
        }

        $allPendingIds = $subscription->trainingPacks
            ->filter(fn ($p) => $p->pivot->status === 'pending')
            ->pluck('id')
            ->toArray();

        // Capture before approval so we can compute the correct discount-aware delta
        $previousAmountDue = $subscription->amount_due;

        (new ApproveTrainingPacksAction)(
            $subscription,
            $this->approvedPackIds,
            $subscription->has_other_family_members ? 2 : 1,
        );

        $subscription->refresh();

        // Delta = new total − what was already owed (CalculatePriceAction applied discount inside)
        $deltaCost = max(0.0, $subscription->amount_due - $previousAmountDue);

        if ($deltaCost > 0) {
            $payment = $subscription->payments()->create([
                'reference'   => (new GeneratePaymentReference)(),
                'amount_due'  => $deltaCost,
                'amount_paid' => 0,
                'status'      => 'pending',
            ]);

            $this->paymentData = [
                'payment_id'         => $payment->id,
                'reference'          => $payment->reference,
                'amount_due'         => $payment->amount_due,
                'member_name'        => $subscription->user->first_name . ' ' . $subscription->user->last_name,
                'member_email'       => $subscription->user->email,
                'iban'               => 'BE23 7323 3320 8791',
                'bic'                => 'CREGBEBB',
                'beneficiary'        => 'CTT Ottignies-Blocry ASBL',
                'qr_code'            => (new GeneratePaymentQR)($payment),
                'invitation_counter' => 0,
            ];
            $this->paymentGenerated = true;
        }

        $rejectedIds = array_diff($allPendingIds, $this->approvedPackIds);
        if (! empty($rejectedIds)) {
            $rejectedPacks = TrainingPack::whereIn('id', $rejectedIds)->get();
            foreach ($rejectedPacks as $pack) {
                $subscription->user->notify(new TrainingPackRejectedNotification(
                    $subscription,
                    $pack,
                    $this->rejectionMessage,
                    $this->rejectionTemplate,
                ));
            }
        }

        $this->approvedPackIds = [];
        $this->rejectionMessage = '';
        $this->rejectionTemplate = '';
        $this->success(__('Training requests approved.'));
    }

    public function sendPaymentEmail(): void
    {
        if (empty($this->paymentData['payment_id'])) {
            return;
        }

        $payment = Payment::with(['payable.user', 'payable.season'])
            ->find($this->paymentData['payment_id']);

        if (! $payment?->payable?->user) {
            $this->error(__('Could not find user for this payment.'));

            return;
        }

        Mail::to($payment->payable->user)->send(new PaymentInvitationEmail($payment));

        $payment->increment('invitation_counter');
        $this->paymentData['invitation_counter'] = $payment->invitation_counter + 1;

        $this->success(__('Payment invitation sent to :email.', ['email' => $payment->payable->user->email]));
    }

    public function headers(): array
    {
        return [
            ['key' => 'name', 'label' => __('Member')],
            ['key' => 'type', 'label' => __('Licence'), 'class' => 'hidden md:table-cell'],
            ['key' => 'trainings_count', 'label' => __('Training'), 'sortable' => false],
            ['key' => 'amount_due', 'label' => __('Amount'), 'sortable' => false],
            ['key' => 'status', 'label' => __('Status')],
        ];
    }

    public function registrations(): Collection
    {
        $statusOrder = ['pending' => 1, 'confirmed' => 2, 'paid' => 3, 'cancelled' => 4];

        return Subscription::with(['user', 'trainingPacks', 'payments'])
            ->when($this->selectedSeasonId, fn ($q) => $q->where('season_id', $this->selectedSeasonId))
            ->when($this->statusFilter, fn ($q) => $this->statusFilter === 'pending'
                ? $q->where(fn ($sub) => $sub
                    ->where('status', 'pending')
                    ->orWhere(fn ($withPacks) => $withPacks
                        ->whereIn('status', ['confirmed', 'paid'])
                        ->whereHas('trainingPacks', fn ($tp) => $tp->where('subscription_training_pack.status', 'pending'))
                    )
                )
                : $q->where('status', $this->statusFilter)
            )
            ->when($this->search, fn ($q) => $q->whereHas('user', fn ($u) => $u
                ->where('first_name', 'like', "%{$this->search}%")
                ->orWhere('last_name', 'like', "%{$this->search}%")
            ))
            ->get()
            ->sortBy(fn ($sub) => $statusOrder[$sub->status] ?? 5)
            ->map(fn (Subscription $sub) => (object) [
                'id'               => $sub->id,
                'first_name'       => $sub->user->first_name,
                'last_name'        => $sub->user->last_name,
                'name'             => $sub->user->first_name . ' ' . $sub->user->last_name,
                'type'             => $sub->is_competitive ? __('Compétition') : __('Récréative'),
                'status'           => $sub->status,
                'amount_due'       => $sub->amount_due,
                'trainings_count'  => $sub->trainings_count,
                'pending_packs'    => $sub->trainingPacks->filter(fn ($p) => $p->pivot->status === 'pending'),
                'enrolled_packs'   => $sub->trainingPacks->filter(fn ($p) => $p->pivot->status === 'enrolled'),
                'has_pending_packs' => $sub->trainingPacks->filter(fn ($p) => $p->pivot->status === 'pending')->isNotEmpty(),
                'subscription_price' => $sub->is_competitive ? 125.0 : 60.0,
                'members'          => [[
                    'first_name' => $sub->user->first_name,
                    'last_name'  => $sub->user->last_name,
                    'trainings'  => $sub->trainingPacks->pluck('name')->toArray(),
                ]],
                'total_price'      => $sub->amount_due,
                'payments'         => $sub->payments->map(fn ($p) => [
                    'reference'  => $p->reference,
                    'amount_due' => $p->amount_due,
                    'status'     => $p->status,
                ])->values()->toArray(),
                'payment_status' => $sub->payments->sortByDesc('created_at')->first()?->status,
            ]);
    }

    #[Computed]
    public function pendingSubscriptions(): Collection
    {
        return Subscription::where('status', 'pending')
            ->when($this->selectedSeasonId, fn ($q) => $q->where('season_id', $this->selectedSeasonId))
            ->when($this->search, fn ($q) => $q->whereHas('user', fn ($u) => $u
                ->where('first_name', 'like', "%{$this->search}%")
                ->orWhere('last_name', 'like', "%{$this->search}%")
            ))
            ->with(['user'])
            ->get();
    }

    #[Computed]
    public function trainingRequests(): Collection
    {
        return Subscription::whereIn('status', ['confirmed', 'paid'])
            ->when($this->selectedSeasonId, fn ($q) => $q->where('season_id', $this->selectedSeasonId))
            ->whereHas('trainingPacks', fn ($q) => $q->where('subscription_training_pack.status', 'pending'))
            ->with(['user', 'trainingPacks' => fn ($q) => $q->wherePivot('status', 'pending')])
            ->when($this->search, fn ($q) => $q->whereHas('user', fn ($u) => $u
                ->where('first_name', 'like', "%{$this->search}%")
                ->orWhere('last_name', 'like', "%{$this->search}%")
            ))
            ->get();
    }

    public function reject(): void
    {
        $subscription = Subscription::with(['user', 'season'])->find($this->currentRequestId);
        $subscription->user->notify(new SubscriptionRejectedNotification(
            $subscription,
            $this->rejectionMessage,
            $this->rejectionTemplate,
        ));
        $subscription->cancel();
        $this->warning(__('Request rejected.'));
        $this->reviewModal = false;
        $this->currentRequestId = null;
        $this->rejectionMessage = '';
        $this->rejectionTemplate = '';
    }

    public function rejectTrainingRequest(): void
    {
        $subscription = Subscription::with(['user', 'season', 'trainingPacks'])->find($this->currentTrainingRequestId);
        if (! $subscription) {
            return;
        }

        $pendingPacks = $subscription->trainingPacks->filter(fn ($p) => $p->pivot->status === 'pending');

        foreach ($pendingPacks as $pack) {
            $subscription->user->notify(new TrainingPackRejectedNotification(
                $subscription,
                $pack,
                $this->rejectionMessage,
                $this->rejectionTemplate,
            ));
        }

        $subscription->trainingPacks()->wherePivot('status', 'pending')->detach();

        $this->trainingRequestModal = false;
        $this->currentTrainingRequestId = null;
        $this->rejectionMessage = '';
        $this->rejectionTemplate = '';
        $this->warning(__('Training requests rejected.'));
    }

    public function openRefundModal(int $subscriptionId, int $packId): void
    {
        $this->refundSubscriptionId = $subscriptionId;
        $this->refundPackId = $packId;
        $this->refundModal = true;
    }

    public function confirmRefund(): void
    {
        $subscription = Subscription::with(['user', 'season', 'trainingPacks', 'payments'])->find($this->refundSubscriptionId);
        if (! $subscription) {
            return;
        }

        $pack = TrainingPack::find($this->refundPackId);
        if (! $pack) {
            return;
        }

        $pivot = $subscription->trainingPacks()->where('training_pack_id', $pack->id)->first();
        if (! $pivot || $pivot->pivot->status !== 'enrolled') {
            $this->error(__('This pack is not enrolled and cannot be refunded this way.'));
            return;
        }

        $packPrice = (float) $pack->price;

        $subscription->trainingPacks()->detach($pack->id);

        (new CalculatePriceAction)($subscription);

        // Create a refund payment record so the treasurer knows a refund is owed
        $subscription->payments()->create([
            'reference'   => (new GeneratePaymentReference)(),
            'amount_due'  => -$packPrice,   // negative = amount to refund
            'amount_paid' => 0,
            'status'      => 'pending',
            'payment_method' => 'refund',
        ]);

        $this->refundModal = false;
        $this->refundSubscriptionId = null;
        $this->refundPackId = null;

        $userName = $subscription->user->first_name . ' ' . $subscription->user->last_name;
        $userIban = $subscription->user->iban;

        if ($userIban) {
            $this->success(__(':user removed from :pack. Refund of :amount€ to be issued to :iban.', [
                'user'   => $userName,
                'pack'   => $pack->name,
                'amount' => number_format($packPrice, 2),
                'iban'   => $userIban,
            ]));
        } else {
            $this->warning(__(':user removed from :pack. Refund of :amount€ required — no IBAN on file, please handle manually.', [
                'user'   => $userName,
                'pack'   => $pack->name,
                'amount' => number_format($packPrice, 2),
            ]));
        }
    }

    public function removeFromBasket($userId): void
    {
        unset($this->familyBasket[$userId]);
    }


    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__("Registrations"));
    }

        public function render(): View
    {
        $statsBase = Subscription::when($this->selectedSeasonId, fn ($q) => $q->where('season_id', $this->selectedSeasonId));

        return $this->view([
            'headers'       => $this->headers(),
            'registrations' => $this->registrations(),
            'stats'         => [
                'total'     => (clone $statsBase)->count(),
                'pending'   => (clone $statsBase)->where('status', 'pending')->count(),
                'confirmed' => (clone $statsBase)->where('status', 'confirmed')->count(),
                'paid'      => (clone $statsBase)->where('status', 'paid')->count(),
            ],
            'statusOptions' => [
                ['id' => 'pending',   'name' => __('To process')],
                ['id' => 'confirmed', 'name' => __('Confirmed')],
                ['id' => 'paid',      'name' => __('Paid')],
                ['id' => 'cancelled', 'name' => __('Cancelled')],
            ],
        ]);
    }

    public function review(int $id): void
    {
        $this->currentRequestId = $id;
        $this->paymentGenerated = false;
        $this->paymentData = [];
        $this->reviewModal = true;

        $subscription = Subscription::with(['trainingPacks'])->find($id);
        $this->approvedPackIds = $subscription
            ?->trainingPacks
            ->filter(fn ($p) => $p->pivot->status === 'pending')
            ->pluck('id')
            ->toArray() ?? [];
    }

    public function reviewTrainingRequest(int $subscriptionId): void
    {
        $this->currentTrainingRequestId = $subscriptionId;
        $this->paymentGenerated = false;
        $this->paymentData = [];
        $this->trainingRequestModal = true;

        $subscription = Subscription::with(['trainingPacks'])->find($subscriptionId);
        $this->approvedPackIds = $subscription
            ?->trainingPacks
            ->filter(fn ($p) => $p->pivot->status === 'pending')
            ->pluck('id')
            ->toArray() ?? [];
    }

    public function saveFamilyRegistration(): void
    {
        $season = Season::current();
        if (! $season) {
            $this->error(__('No active season found.'));

            return;
        }

        $createAction = new CreateSubscriptionAction;
        $calculateAction = new CalculatePriceAction;

        foreach ($this->familyBasket as $userId => $config) {
            $user = User::find((int) $userId);
            $subscription = $createAction->execute($user, $season, [
                'is_competitive'  => $config['licence_type'] === 'competitive',
                'trainings_count' => count($config['trainings']),
            ]);

            if (! empty($config['trainings'])) {
                $subscription->trainingPacks()->sync($config['trainings']);
            }

            $calculateAction($subscription);
        }

        $this->success(__('Group registration successful!'));
        $this->memberDrawer = false;
        $this->familyBasket = [];
    }

    public function toggleRegistrations(): void
    {
        $season = Season::current();
        if (! $season) {
            $this->error(__('No active season found.'));

            return;
        }

        if ($season->registrations_open) {
            $season->closeRegistrations();
            $this->warning(__('Registrations are now closed.'));
        } else {
            $season->openRegistrations();
            $this->success(__('Registrations are now open.'));
        }

        unset($this->registrationClosed);
    }

    public function trainingOptions(): array
    {
        $season = Season::current();
        if (! $season) {
            return [];
        }

        return TrainingPack::where('season_id', $season->id)
            ->get()
            ->map(fn ($pack) => ['id' => $pack->id, 'name' => $pack->name])
            ->toArray();
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'currentRequest' => $this->currentRequestId
                ? $this->registrations()->firstWhere('id', $this->currentRequestId)
                : null,
            'currentTrainingRequest' => $this->currentTrainingRequestId
                ? Subscription::with(['user', 'trainingPacks' => fn ($q) => $q->wherePivot('status', 'pending')])
                    ->find($this->currentTrainingRequestId)
                : null,
            'membersFound' => strlen($this->searchMember) > 2
                ? User::where(function ($q) {
                    $q->where('first_name', 'like', "%{$this->searchMember}%")
                        ->orWhere('last_name', 'like', "%{$this->searchMember}%")
                        ->orWhere('email', 'like', "%{$this->searchMember}%");
                })->limit(5)->get()
                : [],
        ];
    }
};
