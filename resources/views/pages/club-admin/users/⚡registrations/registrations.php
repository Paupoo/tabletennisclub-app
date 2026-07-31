<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\GeneratePaymentQR;
use App\Actions\ClubAdmin\Payments\GeneratePaymentReference;
use App\Actions\ClubAdmin\Subscriptions\ApproveTrainingPacksAction;
use App\Actions\ClubAdmin\Subscriptions\CalculatePriceAction;
use App\Actions\ClubAdmin\Subscriptions\CancelSubscriptionWithRefundAction;
use App\Actions\ClubAdmin\Subscriptions\ChangeSubscriptionFormulaAction;
use App\Actions\ClubAdmin\Subscriptions\CreateSubscriptionAction;
use App\Actions\ClubAdmin\Subscriptions\EnrollInTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\LeaveTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\ReconcileTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\RequestSubscriptionRefundAction;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Enums\Ranking;
use App\Domains\Subscriptions\Notifications\SubscriptionFormulaChangedNotification;
use App\Domains\Subscriptions\Notifications\SubscriptionRejectedNotification;
use App\Domains\Subscriptions\Notifications\TrainingPackRejectedNotification;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Services\TrainingPackProrata;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Livewire\Concerns\ManagesGuardians;
use App\Mail\PaymentInvitationEmail;
use App\Support\Breadcrumb;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule as ValidationRule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs, HasFilterDrawer, ManagesGuardians, Toast;

    /** @var int[] Pack IDs that admin wants to approve (pre-checked = all pending) */
    public array $approvedPackIds = [];

    public string $cancelMessage = '';

    public bool $cancelModal = false;

    public ?float $cancelRefundAmount = null;

    public ?int $cancelSubscriptionId = null;

    public ?int $currentRequestId = null;

    public ?int $currentTrainingRequestId = null;

    public array $familyBasket = [];

    public bool $memberDrawer = false;

    public array $paymentData = [];

    public bool $paymentGenerated = false;

    public ?string $reconcileEndsOn = null;

    // ── Réconciliation manuelle d'une ligne d'entraînement ────────────────────
    public bool $reconcileModal = false;

    public ?string $reconcileOverrideAmount = null;

    public string $reconcileOverrideReason = '';

    public ?int $reconcilePackId = null;

    public ?string $reconcileStartsOn = null;

    public ?int $reconcileSubscriptionId = null;

    public bool $refundModal = false;

    public ?int $refundPackId = null;

    public ?int $refundSubscriptionId = null;

    public string $rejectionMessage = '';

    public string $rejectionTemplate = '';

    /** Licence number being reviewed: pre-filled from the member, editable before accepting. */
    public ?string $reviewLicence = null;

    public bool $reviewModal = false;

    /** Ranking being reviewed: pre-filled from the member, editable before accepting. */
    public ?string $reviewRanking = null;

    public string $search = '';

    public string $searchMember = '';

    public ?int $selectedSeasonId = null;

    public string $statusFilter = '';

    public bool $trainingRequestModal = false;

    public function addToBasket($userId): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $user = User::find($userId);

        $this->familyBasket[$userId] = [
            'name' => $user->first_name . ' ' . $user->last_name,
            'licence_type' => 'recreative',
            'trainings' => [],
        ];

        $this->searchMember = '';
    }

    #[Computed]
    public function affiliationsClosed(): bool
    {
        return ! (Season::current()?->affiliations_open ?? false);
    }

    public function approve(): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $subscription = Subscription::with(['user', 'trainingPacks'])->find($this->currentRequestId);

        $licence = filled($this->reviewLicence) ? trim($this->reviewLicence) : $subscription->user->licence;
        $ranking = filled($this->reviewRanking) ? $this->reviewRanking : $subscription->user->ranking;

        // An affiliation is what ties a member to the federation: accepting one
        // without a licence number would register someone the AFTT cannot identify.
        if (blank($licence)) {
            $this->error(__('A licence number is required before this affiliation can be accepted.'));

            return;
        }

        $validator = Validator::make(
            ['licence' => $licence],
            ['licence' => ['digits:6', ValidationRule::unique('users', 'licence')->ignore($subscription->user->id)]],
            ['licence.digits' => __('A licence number is made of exactly 6 digits.')],
        );

        if ($validator->fails()) {
            $this->error($validator->errors()->first('licence'));

            return;
        }

        // NA means "no ranking on file", never a ranking in its own right: an
        // unranked player is NC, which the federation does recognise.
        if (blank($ranking) || $ranking === Ranking::NA->name) {
            $this->error(__('A ranking is required before this affiliation can be accepted.'));

            return;
        }

        $subscription->user->update(['licence' => $licence, 'ranking' => $ranking]);

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
                'reference' => (new GeneratePaymentReference)(),
                'amount_due' => $subscription->getAmountDue(),
                'amount_paid' => 0,
                'status' => 'pending',
            ]);
        }

        $this->paymentData = [
            'payment_id' => $payment->id,
            'reference' => $payment->reference,
            'amount_due' => $payment->amount_due,
            'member_name' => $subscription->user->first_name . ' ' . $subscription->user->last_name,
            'member_email' => $subscription->user->email,
            'iban' => Club::ourClub()->first()->bank_account,
            'bic' => Club::ourClub()->first()->bic,
            'beneficiary' => 'CTT Ottignies-Blocry ASBL',
            'qr_code' => (new GeneratePaymentQR)($payment),
            'invitation_counter' => $payment->invitation_counter,
        ];

        $this->paymentGenerated = true;
        $this->success(__('Subscription confirmed. Payment information generated.'));
    }

    public function approveTrainingRequest(): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

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
                'reference' => (new GeneratePaymentReference)(),
                'amount_due' => $deltaCost,
                'amount_paid' => 0,
                'status' => 'pending',
            ]);

            $this->paymentData = [
                'payment_id' => $payment->id,
                'reference' => $payment->reference,
                'amount_due' => $payment->amount_due,
                'member_name' => $subscription->user->first_name . ' ' . $subscription->user->last_name,
                'member_email' => $subscription->user->email,
                'iban' => Club::ourClub()->first()->bank_account,
                'bic' => Club::ourClub()->first()->bic,
                'beneficiary' => 'CTT Ottignies-Blocry ASBL',
                'qr_code' => (new GeneratePaymentQR)($payment),
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

    /**
     * Switches an affiliation between the recreative and the competitive formula.
     *
     * The formula belongs to the affiliation, never to the member record: this is
     * the only place it can be changed, and it can never be changed for free —
     * the price follows, and so does the money still owed either way.
     */
    public function changeFormula(): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $subscription = Subscription::with(['user', 'trainingPacks', 'payments'])->find($this->currentRequestId);

        if (! $subscription) {
            return;
        }

        // Leaving the competition while still fielded would leave the season with a
        // player in a line-up who is no longer allowed to play it. The selections are
        // built by hand, so we refuse rather than silently unpick them — and we name
        // the teams, since that is what the admin has to go and fix.
        if ($subscription->is_competitive) {
            $teams = $subscription->user->teams()
                ->where('season_id', $subscription->season_id)
                ->pluck('name');

            if ($teams->isNotEmpty()) {
                $this->error(__('Remove :name from team(s) :teams before switching them back to recreative.', [
                    'name' => $subscription->user->first_name . ' ' . $subscription->user->last_name,
                    'teams' => $teams->implode(', '),
                ]));

                return;
            }
        }

        // Whether the member has already been invoiced is what decides if they hear
        // about this: before that, nothing has been announced to them yet.
        $wasInvoiced = $subscription->payments()->exists();

        $delta = (new ChangeSubscriptionFormulaAction)(
            $subscription,
            $subscription->has_other_family_members ? 2 : 1,
        );

        $complement = null;

        if ($wasInvoiced && $delta > 0) {
            $complement = $subscription->payments()->create([
                'reference' => (new GeneratePaymentReference)(),
                'amount_due' => $delta,
                'amount_paid' => 0,
                'status' => 'pending',
            ]);
        }

        // A refund is capped by what actually came in: we never hand back a euro
        // that was never received (same reasoning as LeaveTrainingPackAction).
        $refundable = $delta < 0
            ? round(min(abs($delta), $subscription->netAmountPaid()), 2)
            : 0.0;

        if ($wasInvoiced) {
            $subscription->user->notify(new SubscriptionFormulaChangedNotification(
                $subscription,
                $delta > 0 ? $delta : -$refundable,
                $complement?->reference,
            ));
        }

        $this->reviewModal = false;

        if ($complement !== null) {
            $this->success(__('Formula changed. A complement of :amount € has been invoiced to the member.', [
                'amount' => number_format($delta, 2),
            ]));

            return;
        }

        if ($refundable > 0) {
            $this->warning(__('Formula changed. :amount € are to be refunded to the member (:iban).', [
                'amount' => number_format($refundable, 2),
                'iban' => $subscription->user->iban ?: __('no IBAN on file'),
            ]));

            return;
        }

        $this->success(__('Affiliation formula changed.'));
    }

    public function clearFilters(): void
    {
        $this->statusFilter = '';
        $this->selectedSeasonId = Season::current()?->id;
    }

    public function confirmCancelSubscription(): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $subscription = Subscription::with(['user', 'season', 'trainingPacks', 'payments'])->find($this->cancelSubscriptionId);
        if (! $subscription || ! in_array($subscription->status, ['confirmed', 'paid'], true)) {
            return;
        }

        $totalPaid = $subscription->totalPaid();
        $refundAmount = $totalPaid > 0 ? (float) ($this->cancelRefundAmount ?? 0) : 0.0;

        if ($totalPaid > 0 && ($refundAmount <= 0 || $refundAmount > $totalPaid)) {
            $this->error(__('The refund amount must be between 0 and the total paid (:total €).', [
                'total' => number_format($totalPaid, 2),
            ]));

            return;
        }

        (new CancelSubscriptionWithRefundAction)($subscription, $refundAmount, $this->cancelMessage);

        $this->cancelModal = false;
        $this->reviewModal = false;
        $this->cancelSubscriptionId = null;
        $this->cancelRefundAmount = null;
        $this->cancelMessage = '';
        $this->currentRequestId = null;

        $userName = $subscription->user->first_name . ' ' . $subscription->user->last_name;

        if ($refundAmount <= 0) {
            $this->warning(__('Subscription of :user cancelled.', ['user' => $userName]));

            return;
        }

        if ($subscription->user->iban) {
            $this->success(__('Subscription of :user cancelled. Refund of :amount € to be issued to :iban.', [
                'user' => $userName,
                'amount' => number_format($refundAmount, 2),
                'iban' => $subscription->user->iban,
            ]));
        } else {
            $this->warning(__('Subscription of :user cancelled. Refund of :amount € required — no IBAN on file, please handle manually.', [
                'user' => $userName,
                'amount' => number_format($refundAmount, 2),
            ]));
        }
    }

    public function confirmRefund(): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

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

        // Detach + price recalculation + waitlist promotion for the freed spot.
        // The refundable amount is the resulting overpayment, not the pack price:
        // losing a pack can also lose the multi-pack discount on the ones kept.
        $refundable = (new LeaveTrainingPackAction)($subscription, $pack, $subscription->has_other_family_members ? 2 : 1, notifyUser: false);

        $this->refundModal = false;
        $this->refundSubscriptionId = null;
        $this->refundPackId = null;

        $userName = $subscription->user->first_name . ' ' . $subscription->user->last_name;

        if ($refundable <= 0.0) {
            $this->success(__(':user removed from :pack. Nothing to refund — their balance is settled.', [
                'user' => $userName,
                'pack' => $pack->name,
            ]));

            return;
        }

        // Refund enters the treasury workflow (to_refund) and notifies the treasurer & secretary
        (new RequestSubscriptionRefundAction)($subscription, $refundable, __(':member has been removed from :pack after having paid.', [
            'member' => $userName,
            'pack' => $pack->name,
        ]));

        $userIban = $subscription->user->iban;

        if ($userIban) {
            $this->success(__(':user removed from :pack. Refund of :amount€ to be issued to :iban.', [
                'user' => $userName,
                'pack' => $pack->name,
                'amount' => number_format($refundable, 2),
                'iban' => $userIban,
            ]));
        } else {
            $this->warning(__(':user removed from :pack. Refund of :amount€ required — no IBAN on file, please handle manually.', [
                'user' => $userName,
                'pack' => $pack->name,
                'amount' => number_format($refundable, 2),
            ]));
        }
    }

    /**
     * What the federation says about the open request, when it says otherwise.
     *
     * The club and the federation each hold their own answer to "does this member
     * play competition", and they are allowed to differ — a member takes it up,
     * or stops. What must not happen is the difference passing unseen by whoever
     * accepts the affiliation, since accepting it is what registers the member
     * with the federation for the season.
     *
     * The date matters as much as the answer: a listing two days old and one ten
     * months old are not the same argument.
     */
    #[Computed]
    public function federationFormulaGap(): ?string
    {
        $subscription = $this->currentRequestId === null
            ? null
            : Subscription::with('user')->find($this->currentRequestId);

        $member = $subscription?->user;

        if ($member === null || ! $member->contradictsFederationLicenceType($subscription->is_competitive)) {
            return null;
        }

        return __('The member asked for :formula. Federation: :type on :date', [
            'formula' => $subscription->is_competitive ? __('Competitive') : __('Recreational'),
            'type' => $member->federation_licence_type,
            'date' => $member->federation_synced_at->format('d/m/Y'),
        ]);
    }

    /** @return array<int, array{key: string, label: string}> */
    #[Computed]
    public function filterChips(): array
    {
        return $this->getFilterChips();
    }

    // ── HasFilterDrawer ───────────────────────────────────────────────────────

    /** @return array<int, array{key: string, label: string}> */
    public function getFilterChips(): array
    {
        $chips = [];

        if ($this->selectedSeasonId !== Season::current()?->id) {
            $seasonName = Season::find($this->selectedSeasonId)?->name ?? __('All seasons');
            $chips[] = ['key' => 'selectedSeasonId', 'label' => __('Season') . ': ' . $seasonName];
        }

        if (filled($this->statusFilter)) {
            $label = match ($this->statusFilter) {
                'pending' => __('To process'),
                'confirmed' => __('Confirmed'),
                'paid' => __('Paid'),
                'refunded' => __('Refunded'),
                'cancelled' => __('Cancelled'),
                default => $this->statusFilter,
            };
            $chips[] = ['key' => 'statusFilter', 'label' => __('Status') . ': ' . $label];
        }

        return $chips;
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

    public function mount(): void
    {
        Gate::authorize(Permission::SubscriptionsView->value);

        $this->selectedSeasonId = Season::current()?->id;
    }

    public function openCancelModal(int $subscriptionId): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $subscription = Subscription::with('payments')->find($subscriptionId);
        if (! $subscription || ! in_array($subscription->status, ['confirmed', 'paid'], true)) {
            return;
        }

        $totalPaid = $subscription->totalPaid();

        // Proposition, pas décision : on retranche les mois d'entraînement déjà
        // suivis, que le club garde. Le trésorier reste libre du montant.
        $consumed = (new CalculatePriceAction)->consumedTrainingTotal(
            $subscription,
            $subscription->has_other_family_members ? 2 : 1,
        );

        $this->cancelSubscriptionId = $subscriptionId;
        $this->cancelRefundAmount = $totalPaid > 0
            ? round(max(0.0, $totalPaid - $consumed), 2)
            : null;
        $this->cancelMessage = '';
        $this->cancelModal = true;
    }

    public function openReconcileModal(int $subscriptionId, int $packId): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $pivot = DB::table('subscription_training_pack')
            ->where('subscription_id', $subscriptionId)
            ->where('training_pack_id', $packId)
            ->first();

        if (! $pivot) {
            return;
        }

        $this->reconcileSubscriptionId = $subscriptionId;
        $this->reconcilePackId = $packId;
        $this->reconcileStartsOn = $pivot->starts_on !== null ? Carbon::parse($pivot->starts_on)->toDateString() : null;
        $this->reconcileEndsOn = $pivot->ends_on !== null ? Carbon::parse($pivot->ends_on)->toDateString() : null;
        $this->reconcileOverrideAmount = $pivot->override_amount !== null
            ? number_format(((int) $pivot->override_amount) / 100, 2, '.', '')
            : null;
        $this->reconcileOverrideReason = (string) ($pivot->override_reason ?? '');
        $this->reconcileModal = true;
    }

    public function openRefundModal(int $subscriptionId, int $packId): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $this->refundSubscriptionId = $subscriptionId;
        $this->refundPackId = $packId;
        $this->refundModal = true;
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

    /**
     * Aperçu vivant de la modale de réconciliation : ce que la période saisie
     * ferait payer, avant que le trésorier ne décide de forcer un montant.
     *
     * @return array{pack: TrainingPack, member: string, net_price: float, ratio: float, amount: float, prorata_available: bool}|null
     */
    #[Computed]
    public function reconcilePreview(): ?array
    {
        $subscription = $this->reconcileSubscriptionId
            ? Subscription::with('user')->find($this->reconcileSubscriptionId)
            : null;
        $pack = $this->reconcilePackId ? TrainingPack::find($this->reconcilePackId) : null;

        if (! $subscription || ! $pack) {
            return null;
        }

        $familyCount = $subscription->has_other_family_members ? 2 : 1;
        $enrolled = $subscription->trainingPacks()->wherePivot('status', 'enrolled')->get();
        $applyDiscount = $enrolled->filter(fn (TrainingPack $p) => $p->allow_discount)->count() > 1
            || $familyCount > 1;

        $netPrice = $applyDiscount && $pack->allow_discount
            ? max(0.0, (float) $pack->price - 10.0)
            : (float) $pack->price;

        $prorata = new TrainingPackProrata;
        $ratio = $prorata->ratio($pack, $this->reconcileStartsOn, $this->reconcileEndsOn);

        return [
            'pack' => $pack,
            'member' => $subscription->user->first_name . ' ' . $subscription->user->last_name,
            'net_price' => $netPrice,
            'ratio' => $ratio,
            'amount' => round($netPrice * $ratio, 2),
            'prorata_available' => $pack->pack_start_date !== null && $pack->pack_end_date !== null,
        ];
    }

    public function registrations(): Collection
    {
        $statusOrder = ['pending' => 1, 'confirmed' => 2, 'paid' => 3, 'refunded' => 4, 'cancelled' => 5];

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
            ->map(function (Subscription $sub) {
                $enrolledPacks = $sub->trainingPacks->filter(fn ($p) => $p->pivot->status === 'enrolled');
                $pendingPacks = $sub->trainingPacks->filter(fn ($p) => $p->pivot->status === 'pending');
                $cancelledPacks = $sub->trainingPacks->filter(fn ($p) => $p->pivot->status === 'cancelled');
                // Packs quittés : encore facturés au pro rata des mois suivis,
                // donc toujours visibles dans le détail de la cotisation.
                $leftPacks = $sub->trainingPacks->filter(fn ($p) => $p->pivot->status === 'left');

                // A voided affiliation drags its trainings down with it, so any
                // pack still flagged pending/enrolled reads as cancelled here —
                // this also rescues rows cancelled before the status cascade.
                if (in_array($sub->status, ['cancelled', 'refunded'], true)) {
                    $cancelledPacks = $cancelledPacks->concat($pendingPacks)->concat($enrolledPacks);
                    $pendingPacks = $enrolledPacks = collect();
                }

                return (object) [
                    'id' => $sub->id,
                    'first_name' => $sub->user->first_name,
                    'last_name' => $sub->user->last_name,
                    'name' => $sub->user->first_name . ' ' . $sub->user->last_name,
                    'type' => $sub->is_competitive ? __('Competition') : __('Recreational'),
                    'status' => $sub->status,
                    'amount_due' => $sub->amount_due,
                    'total_paid' => (float) $sub->payments->whereIn('status', ['paid', 'refunded'])->sum('amount_paid'),
                    'trainings_count' => $sub->trainings_count,
                    'pending_packs' => $pendingPacks,
                    'enrolled_packs' => $enrolledPacks,
                    'cancelled_packs' => $cancelledPacks,
                    'left_packs' => $leftPacks,
                    'has_pending_packs' => $pendingPacks->isNotEmpty(),
                    'subscription_price' => $sub->is_competitive ? 125.0 : 60.0,
                    'members' => [[
                        'first_name' => $sub->user->first_name,
                        'last_name' => $sub->user->last_name,
                        'trainings' => $sub->trainingPacks->pluck('name')->toArray(),
                    ]],
                    'total_price' => $sub->amount_due,
                    'payments' => $sub->payments->map(fn ($p) => [
                        'reference' => $p->reference,
                        'amount_due' => $p->amount_due,
                        'status' => $p->status,
                    ])->values()->toArray(),
                    'payment_status' => $sub->payments->sortByDesc('created_at')->first()?->status,
                ];
            });
    }

    public function reject(): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

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
        Gate::authorize(Permission::SubscriptionsManage->value);

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

    public function removeFilter(string $key): void
    {
        if ($key === 'selectedSeasonId') {
            $this->selectedSeasonId = Season::current()?->id;

            return;
        }

        $this->reset([$key]);
    }

    public function removeFromBasket($userId): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        unset($this->familyBasket[$userId]);
    }

    public function render(): View
    {
        $statsBase = Subscription::when($this->selectedSeasonId, fn ($q) => $q->where('season_id', $this->selectedSeasonId));

        return $this->view([
            'headers' => $this->headers(),
            'registrations' => $this->registrations(),
            // NA is deliberately absent: an affiliation being accepted cannot carry it.
            'rankings' => Ranking::options(includeNA: false),
            'stats' => [
                'total' => (clone $statsBase)->count(),
                'pending' => (clone $statsBase)->where('status', 'pending')->count(),
                'confirmed' => (clone $statsBase)->where('status', 'confirmed')->count(),
                'paid' => (clone $statsBase)->where('status', 'paid')->count(),
                'refunded' => (clone $statsBase)->where('status', 'refunded')->count(),
            ],
            'statusOptions' => [
                ['id' => 'pending',   'name' => __('To process')],
                ['id' => 'confirmed', 'name' => __('Confirmed')],
                ['id' => 'paid',      'name' => __('Paid')],
                ['id' => 'refunded',  'name' => __('Refunded')],
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

        $subscription = Subscription::with(['user', 'trainingPacks'])->find($id);
        $this->approvedPackIds = $subscription
            ?->trainingPacks
            ->filter(fn ($p) => $p->pivot->status === 'pending')
            ->pluck('id')
            ->toArray() ?? [];

        // Accepting an affiliation is the moment the licence number is checked
        // against the federation, so it is offered for edit right here.
        $this->reviewLicence = $subscription?->user?->licence;
        $this->reviewRanking = $subscription?->user?->ranking;
    }

    /**
     * Détail facturé de chaque pack de l'inscription ouverte dans la modale.
     *
     * Le prix affiché sur une ligne n'est plus celui du pack : remise, pro rata
     * et montant forcé peuvent tous le déplacer.
     *
     * @return array<int, array{name: string, status: string, amount: float, overridden: bool, ratio: float}>
     */
    #[Computed]
    public function reviewPackLines(): array
    {
        if (! $this->currentRequestId) {
            return [];
        }

        $subscription = Subscription::find($this->currentRequestId);

        if (! $subscription) {
            return [];
        }

        return (new CalculatePriceAction)->quote(
            $subscription,
            $subscription->has_other_family_members ? 2 : 1,
        )['lines'];
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

    /**
     * Whether the basket describes a family, and therefore needs the guardian
     * that ties its members together.
     *
     * A single member affiliates for himself: nothing to tie, nothing to ask.
     */
    public function requiresFamilyGuardian(): bool
    {
        return count($this->familyBasket) > 1;
    }

    public function saveFamilyRegistration(): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $season = Season::current();
        if (! $season) {
            $this->error(__('No active season found.'));

            return;
        }

        if ($this->requiresFamilyGuardian() && $this->guardianIds === []) {
            $this->error(__('Add the guardian who links these members before validating the group.'));

            return;
        }

        $createAction = new CreateSubscriptionAction;
        $calculateAction = new CalculatePriceAction;

        // Une famille s'inscrit d'un bloc : laisser deux affiliations derrière
        // soi parce que la troisième a échoué obligerait l'admin à réparer à la
        // main ce qu'il croyait avoir annulé.
        try {
            DB::transaction(function () use ($season, $createAction, $calculateAction): void {
                foreach ($this->familyBasket as $userId => $config) {
                    $user = User::find((int) $userId);

                    // Le membre est peut-être passé par le formulaire public entre
                    // deux ouvertures du drawer : sans ce garde-fou,
                    // CreateSubscriptionAction lève une DomainException nue.
                    $alreadyAffiliated = Subscription::where('user_id', $user->id)
                        ->where('season_id', $season->id)
                        ->affiliated()
                        ->exists();

                    if ($alreadyAffiliated) {
                        throw new DomainException(__(':name is already affiliated for this season.', [
                            'name' => $user->first_name . ' ' . $user->last_name,
                        ]));
                    }

                    $subscription = $createAction->execute($user, $season, [
                        'is_competitive' => $config['licence_type'] === 'competitive',
                        'trainings_count' => count($config['trainings']),
                    ]);

                    // Le lien familial est ce que le guichet est le mieux placé
                    // pour établir : la famille est là, devant l'admin. C'est
                    // lui qui ouvre droit à la remise famille.
                    //
                    // La mère inscrite avec ses enfants est souvent la tutrice
                    // désignée : elle ne devient pas sa propre tutrice.
                    $guardianIdsForMember = $this->linkedGuardians
                        ->reject(fn (Guardian $guardian): bool => $guardian->user_id === $user->id)
                        ->pluck('id')
                        ->all();

                    if ($guardianIdsForMember !== []) {
                        $user->guardians()->syncWithoutDetaching($guardianIdsForMember);
                    }

                    // Le guichet ne crée pas de place : un pack complet met le
                    // membre en liste d'attente, comme partout ailleurs. Seules
                    // les lignes réellement obtenues (`pending`) sont validables.
                    $claimedPackIds = [];

                    if (! empty($config['trainings'])) {
                        $packs = TrainingPack::whereIn('id', $config['trainings'])->get();

                        foreach ($packs as $pack) {
                            if ((new EnrollInTrainingPackAction)($subscription, $pack) === 'pending') {
                                $claimedPackIds[] = $pack->id;
                            }
                        }
                    }

                    $calculateAction($subscription);

                    // C'est un admin qui inscrit au guichet : sa décision vaut
                    // validation, pour peu que la fédération puisse identifier
                    // le membre. Sinon la demande attend, sans bloquer l'admin.
                    if ($this->canBeConfirmedDirectly($user)) {
                        $subscription->confirm();

                        // Pose `starts_on` au pro rata et remet le prix à jour.
                        if (! empty($claimedPackIds)) {
                            (new ApproveTrainingPacksAction)($subscription, $claimedPackIds);
                        }

                        $subscription->payments()->create([
                            'reference' => (new GeneratePaymentReference)(),
                            'amount_due' => $subscription->getAmountDue(),
                            'amount_paid' => 0,
                            'status' => 'pending',
                        ]);
                    }
                }
            });
        } catch (DomainException $exception) {
            $this->error($exception->getMessage());

            return;
        }

        $this->success(__('Group affiliation successful!'));
        $this->memberDrawer = false;
        $this->familyBasket = [];
        $this->guardianIds = [];
        $this->resetGuardianForm();
    }

    public function saveReconciliation(): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $subscription = Subscription::with('user')->find($this->reconcileSubscriptionId);
        $pack = TrainingPack::find($this->reconcilePackId);

        if (! $subscription || ! $pack) {
            return;
        }

        try {
            (new ReconcileTrainingPackAction)(
                $subscription,
                $pack,
                $this->reconcileStartsOn,
                $this->reconcileEndsOn,
                filled($this->reconcileOverrideAmount) ? (float) $this->reconcileOverrideAmount : null,
                $this->reconcileOverrideReason,
                $subscription->has_other_family_members ? 2 : 1,
            );
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return;
        }

        unset($this->reviewPackLines);

        $this->reconcileModal = false;
        $this->reconcileSubscriptionId = null;
        $this->reconcilePackId = null;

        $this->success(__('Training pack adjusted. New total: :amount €', [
            'amount' => number_format((float) $subscription->fresh()->amount_due, 2),
        ]));
    }

    #[Computed]
    public function seasonOptions(): array
    {
        return Season::orderBy('start_at')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name . ($s->is_active ? ' ✦' : ''),
            ])
            ->toArray();
    }

    public function sendPaymentEmail(): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

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
        $this->paymentData['invitation_counter'] = $payment->invitation_counter;

        $this->success(__('Payment invitation sent to :email.', ['email' => $payment->payable->user->email]));
    }

    #[Computed]
    public function subscriptionToCancel(): ?Subscription
    {
        if (! $this->cancelSubscriptionId) {
            return null;
        }

        return Subscription::with(['user', 'payments'])->find($this->cancelSubscriptionId);
    }

    public function toggleAffiliations(): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $season = Season::current();
        if (! $season) {
            $this->error(__('No active season found.'));

            return;
        }

        if ($season->affiliations_open) {
            $season->closeAffiliations();
            $this->warning(__('Affiliations are now closed.'));
        } else {
            $season->openAffiliations();
            $this->success(__('Affiliations are now open.'));
        }

        unset($this->affiliationsClosed);
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

        $enrolledPacks = $subscription->trainingPacks()->wherePivot('status', 'enrolled')->get();
        $newPacks = empty($this->approvedPackIds)
            ? collect()
            : TrainingPack::whereIn('id', $this->approvedPackIds)->get();

        $discountableBefore = $enrolledPacks->filter(fn (TrainingPack $p) => $p->allow_discount);
        $discountableNew = $newPacks->filter(fn (TrainingPack $p) => $p->allow_discount);
        $totalDiscountable = $discountableBefore->count() + $discountableNew->count();

        $familyCount = $subscription->has_other_family_members ? 2 : 1;
        $applyDiscount = $totalDiscountable > 1 || $familyCount > 1;
        $hadDiscount = $discountableBefore->count() > 1 || $familyCount > 1;

        // Enrolled packs that gain a retroactive -10€ discount for the first time
        $retroAdjustments = [];
        if ($applyDiscount && ! $hadDiscount) {
            foreach ($discountableBefore as $pack) {
                $retroAdjustments[$pack->id] = [
                    'name' => $pack->name,
                    'original_price' => (float) $pack->price,
                    'new_price' => max(0.0, (float) $pack->price - 10.0),
                ];
            }
        }

        // New packs with their effective (possibly discounted) price
        $newPacksDetail = [];
        foreach ($newPacks as $pack) {
            $full = (float) $pack->price;
            $effective = ($applyDiscount && $pack->allow_discount) ? max(0.0, $full - 10.0) : $full;
            $newPacksDetail[$pack->id] = [
                'full_price' => $full,
                'effective_price' => $effective,
                'discounted' => $effective < $full,
            ];
        }

        return [
            'apply_discount' => $applyDiscount,
            'retro_adjustments' => $retroAdjustments,
            'new_packs' => $newPacksDetail,
        ];
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

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'filterChips' => $this->filterChips,
            'currentRequest' => $this->currentRequestId
                ? $this->registrations()->firstWhere('id', $this->currentRequestId)
                : null,
            'currentTrainingRequest' => $this->currentTrainingRequestId
                ? Subscription::with(['user', 'trainingPacks' => fn ($q) => $q->wherePivot('status', 'pending')])
                    ->find($this->currentTrainingRequestId)
                : null,
            // Proposer un membre déjà affilié n'aboutirait qu'à un refus au
            // moment d'enregistrer : autant ne pas le proposer.
            'membersFound' => strlen($this->searchMember) > 2
                ? User::query()
                    ->whereNotIn('id', User::affiliatedForCurrentSeason()->select('users.id'))
                    ->searchName($this->searchMember)
                    ->limit(5)
                    ->get()
                : collect(),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Affiliations'));
    }

    /**
     * Mêmes exigences que approve() : une affiliation ne peut être validée que
     * si la fédération peut identifier le membre — licence à 6 chiffres, unique,
     * et un vrai classement. NA veut dire « pas de classement au dossier », pas
     * « non classé » (ça, c'est NC, que la fédération reconnaît).
     *
     * Le guichet ne s'arrête pas pour autant : sans ça, l'inscription reste en
     * attente et le secrétariat la reprendra depuis la modale de validation.
     */
    private function canBeConfirmedDirectly(User $user): bool
    {
        if (blank($user->licence) || blank($user->ranking) || $user->ranking === Ranking::NA->name) {
            return false;
        }

        return ! Validator::make(
            ['licence' => trim($user->licence)],
            ['licence' => ['digits:6', ValidationRule::unique('users', 'licence')->ignore($user->id)]],
        )->fails();
    }
};
