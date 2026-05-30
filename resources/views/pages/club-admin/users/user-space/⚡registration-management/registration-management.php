<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\GeneratePaymentQR;
use App\Models\ClubAdmin\Payment\Payment;
use App\Actions\ClubAdmin\Subscriptions\CreateSubscriptionAction;
use App\Actions\ClubAdmin\Subscriptions\EnrollInTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\LeaveTrainingPackAction;
use App\Domains\Shared\Enums\Gender;
use App\Domains\Shared\Enums\TrainingLevel;
use App\Models\ClubAdmin\Subscription\Subscription;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Season;
use App\Models\ClubEvents\Training\TrainingPack;
use App\Notifications\Subscription\SubscriptionCreatedNotification;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast, WithFileUploads, HasBreadcrumbs;

    // --- Modal "Ajouter un membre" ---
    public bool $addMemberModal = false;

    // --- Modal confirmation annulation affiliation ---
    public bool $cancelAffiliationModal = false;

    public int $cancelAffiliationUserId = 0;

    // --- Modal confirmation quitter/annuler un pack ---
    public bool $leavePackModal = false;

    public int $leavePackId = 0;

    public int $leavePackUserId = 0;

    public string $leavePackContext = 'leave';

    #[Rule('required|string')]
    public string $new_birthdate = '';

    #[Rule('required|string|email')]
    public string $new_email = '';

    #[Rule('required|string')]
    public string $new_first_name = '';

    #[Rule('required|string')]
    public string $new_gender = '';

    #[Rule('required|string')]
    public string $new_last_name = '';

    #[Rule('nullable|string')]
    public string $new_phone_number = '';

    /** @var array<int, array<string, mixed>> */
    public array $registrations = [];

    /** @var array<int, array<string, mixed>> */
    public array $existingSubscriptions = [];

    public bool $paymentModal = false;

    /** @var array<string, mixed> */
    public array $paymentDetails = [];

    public string $memberSearchQuery = '';

    public string $memberModalMode = 'search';

    public string $selectedTab = '';

    public $medicalCertificate = null;

    public $parentalConsent = null;

    public User $user;

    /**
     * Pack IDs pre-selected before affiliation submission, keyed by user ID.
     *
     * @var array<int, int[]>
     */
    public array $pendingPackIds = [];

    // ──────────────────────────────────────────────────────────────────────────
    // Lifecycle
    // ──────────────────────────────────────────────────────────────────────────

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Registration management'));
    }

    public function mount(): void
    {
        $this->user = Auth::user();
        $this->addRegistrationTab($this->user);
        $this->selectedTab = 'tab-' . $this->user->id;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Member management
    // ──────────────────────────────────────────────────────────────────────────

    public function addRegistrationTab(User $user): void
    {
        $season = Season::current();

        $existing = $season
            ? Subscription::where('user_id', $user->id)
                ->where('season_id', $season->id)
                ->whereNotIn('status', ['cancelled'])
                ->first()
            : null;

        if ($existing) {
            $paidPayment = $existing->status === 'paid'
                ? $existing->payments()->where('status', 'paid')->latest()->first()
                : null;

            $this->existingSubscriptions[$user->id] = [
                'status'      => $existing->status,
                'amount_due'  => $existing->amount_due,
                'amount_paid' => $paidPayment?->amount_paid ?? 0,
                'paid_at'     => $paidPayment?->updated_at?->format('d/m/Y'),
                'formula'     => $existing->is_competitive ? 'competitive' : 'recreative',
            ];
        }

        $this->registrations[$user->id] = [
            'user_id'                  => $user->id,
            'name'                     => $user->first_name . ' ' . $user->last_name,
            'formula'                  => $existing?->is_competitive ? 'competitive' : 'recreative',
            'is_minor'                 => $user->birthdate && $user->birthdate->age < 18,
            'medical_certificate_path' => $user->medical_certificate_path,
            'parental_consent_path'    => $user->parental_consent_path,
        ];

        if (! isset($this->pendingPackIds[$user->id])) {
            $this->pendingPackIds[$user->id] = [];
        }
    }

    public function addExistingMember(int $userId): void
    {
        $user = User::find($userId);
        if (! $user) {
            return;
        }

        $this->addRegistrationTab($user);
        $this->reset(['addMemberModal', 'memberSearchQuery']);
        $this->memberModalMode = 'search';
        $this->success(__(':name added to the registration.', ['name' => $user->first_name]));
    }

    public function createFamilyMember(): void
    {
        $this->validate();

        $newMember = User::firstOrCreate(
            ['email' => $this->new_email],
            [
                'first_name'   => $this->new_first_name,
                'last_name'    => $this->new_last_name,
                'email'        => $this->new_email,
                'birthdate'    => $this->new_birthdate,
                'gender'       => $this->new_gender,
                'phone_number' => $this->new_phone_number ?: null,
                'street'       => Auth::user()->street,
                'city_code'    => Auth::user()->city_code,
                'city_name'    => Auth::user()->city_name,
                'password'     => Hash::make(Str::random(16)),
            ]
        );

        $this->addRegistrationTab($newMember);
        $this->reset(['new_first_name', 'new_last_name', 'new_birthdate', 'new_gender', 'new_email', 'new_phone_number', 'addMemberModal', 'memberSearchQuery']);
        $this->memberModalMode = 'search';
        $this->success(__('Member added successfully!'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Affiliation
    // ──────────────────────────────────────────────────────────────────────────

    public function confirmAffiliation(int $userId): void
    {
        $season = Season::current();
        if (! $season || ! $season->registrations_open) {
            $this->error(__('Registrations are currently closed.'));

            return;
        }

        $user = User::find($userId);
        if (! $user) {
            return;
        }

        $reg = $this->registrations[$userId] ?? null;
        if (! $reg) {
            return;
        }

        $subscription = (new CreateSubscriptionAction)->execute($user, $season, [
            'is_competitive' => ($reg['formula'] ?? 'recreative') === 'competitive',
        ]);

        $selectedPackIds = $this->pendingPackIds[$userId] ?? [];
        if (! empty($selectedPackIds)) {
            $attachData = array_fill_keys(
                $selectedPackIds,
                ['status' => 'pending']
            );
            $subscription->trainingPacks()->attach($attachData);
        }

        $subscription->load('season');
        $user->notify(new SubscriptionCreatedNotification($subscription));

        $this->existingSubscriptions[$userId] = [
            'status'      => $subscription->status,
            'amount_due'  => $subscription->amount_due,
            'amount_paid' => 0,
            'paid_at'     => null,
            'formula'     => $reg['formula'],
        ];

        $this->pendingPackIds[$userId] = [];
        $this->success(__('Your registration has been submitted. The club will process it shortly.'));
    }

    public function confirmCancelAffiliation(int $userId): void
    {
        $this->cancelAffiliationUserId = $userId;
        $this->cancelAffiliationModal = true;
    }

    public function cancelAffiliation(): void
    {
        $userId = $this->cancelAffiliationUserId;
        $season = Season::current();
        if (! $season) {
            return;
        }

        $subscription = Subscription::where('user_id', $userId)
            ->where('season_id', $season->id)
            ->where('status', 'pending')
            ->first();

        if (! $subscription) {
            return;
        }

        $subscription->cancel();

        unset($this->existingSubscriptions[$userId]);
        $this->cancelAffiliationModal = false;
        $this->warning(__('Your registration request has been cancelled.'));
    }

    public function openPaymentModal(int $userId, int $paymentId): void
    {
        $payment = Payment::find($paymentId);

        if (! $payment) {
            $this->error(__('No payment found. Please contact the club.'));

            return;
        }

        $this->paymentDetails = [
            'name'        => $this->registrations[$userId]['name'] ?? '',
            'reference'   => $payment->reference,
            'amount_due'  => $payment->amount_due,
            'iban'        => 'BE23 7323 3320 8791',
            'bic'         => 'CREGBEBB',
            'beneficiary' => 'CTT Ottignies-Blocry ASBL',
            'qr_code'     => (new GeneratePaymentQR)($payment),
        ];
        $this->paymentModal = true;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Training enrollment
    // ──────────────────────────────────────────────────────────────────────────

    public function enrollInPack(int $packId, int $userId): void
    {
        $season = Season::current();
        if (! $season) {
            return;
        }

        $pack = TrainingPack::find($packId);
        if (! $pack) {
            return;
        }

        if ($pack->trainer_id === $userId) {
            $this->error(__('You cannot enroll in a training pack you coach.'));

            return;
        }

        $subscription = Subscription::where('user_id', $userId)
            ->where('season_id', $season->id)
            ->whereNotIn('status', ['cancelled'])
            ->first();

        if (! $subscription) {
            $this->error(__('You need an active club membership to enroll in training.'));

            return;
        }

        try {
            $status = (new EnrollInTrainingPackAction)(
                $subscription,
                $pack,
                count($this->registrations)
            );

            if ($status === 'pending') {
                $this->success(__('Your request for :pack has been submitted for validation.', ['pack' => $pack->name]));
            } else {
                $this->warning(__('Added to the waiting list for :pack.', ['pack' => $pack->name]));
            }
        } catch (\DomainException $e) {
            $this->error($e->getMessage());
        }
    }

    public function confirmLeaveTrainingPack(int $packId, int $userId, string $context = 'leave'): void
    {
        $this->leavePackId = $packId;
        $this->leavePackUserId = $userId;
        $this->leavePackContext = $context;
        $this->leavePackModal = true;
    }

    public function leaveTrainingPack(int $packId, int $userId): void
    {
        $season = Season::current();
        if (! $season) {
            return;
        }

        $subscription = Subscription::where('user_id', $userId)
            ->where('season_id', $season->id)
            ->whereNotIn('status', ['cancelled'])
            ->first();

        if (! $subscription) {
            return;
        }

        $pack = TrainingPack::find($packId);
        if (! $pack) {
            return;
        }

        $pivot = $subscription->trainingPacks()->where('training_pack_id', $packId)->first();
        if ($pivot?->pivot->status === 'enrolled') {
            $this->error(__('You cannot leave a validated training pack. Please contact the club.'));
            return;
        }

        (new LeaveTrainingPackAction)($subscription, $pack, count($this->registrations));
        $this->success(__('Removed from :pack.', ['pack' => $pack->name]));
    }

    public function leaveTrainingPackConfirmed(): void
    {
        $this->leaveTrainingPack($this->leavePackId, $this->leavePackUserId);
        $this->leavePackModal = false;
    }

    public function confirmWaitlistOffer(int $packId, int $userId): void
    {
        $season = Season::current();
        if (! $season) {
            return;
        }

        $subscription = Subscription::where('user_id', $userId)
            ->where('season_id', $season->id)
            ->whereNotIn('status', ['cancelled'])
            ->first();

        if (! $subscription) {
            return;
        }

        $subscription->trainingPacks()->updateExistingPivot($packId, [
            'status'                => 'enrolled',
            'waitlist_position'     => null,
            'confirmation_deadline' => null,
        ]);

        $this->success(__('Spot confirmed!'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Documents
    // ──────────────────────────────────────────────────────────────────────────

    public function uploadMedicalCertificate(int $userId): void
    {
        $this->validate([
            'medicalCertificate' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);

        $user = User::find($userId);
        if (! $user) {
            return;
        }

        if ($user->medical_certificate_path) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $user->medical_certificate_path));
        }

        $extension = $this->medicalCertificate->getClientOriginalExtension();
        $path = $this->medicalCertificate->storeAs("documents/{$userId}", "medical.{$extension}", 'public');

        $user->update(['medical_certificate_path' => "/storage/{$path}"]);
        $this->registrations[$userId]['medical_certificate_path'] = "/storage/{$path}";
        $this->medicalCertificate = null;
        $this->success(__('Medical certificate uploaded successfully.'));
    }

    public function uploadParentalConsent(int $userId): void
    {
        $this->validate([
            'parentalConsent' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);

        $user = User::find($userId);
        if (! $user) {
            return;
        }

        if ($user->parental_consent_path) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $user->parental_consent_path));
        }

        $extension = $this->parentalConsent->getClientOriginalExtension();
        $path = $this->parentalConsent->storeAs("documents/{$userId}", "parental_consent.{$extension}", 'public');

        $user->update(['parental_consent_path' => "/storage/{$path}"]);
        $this->registrations[$userId]['parental_consent_path'] = "/storage/{$path}";
        $this->parentalConsent = null;
        $this->success(__('Parental consent uploaded successfully.'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // View data
    // ──────────────────────────────────────────────────────────────────────────

    public function with(): array
    {
        $season = Season::current();
        $userIds = array_keys($this->registrations);
        $alreadyAddedIds = $userIds;

        // Current season subscriptions (keyed by user_id, excludes cancelled)
        $currentSubs = $season
            ? Subscription::whereIn('user_id', $userIds)
                ->where('season_id', $season->id)
                ->whereNotIn('status', ['cancelled'])
                ->with(['trainingPacks'])
                ->get()
                ->keyBy('user_id')
            : collect();

        // All subscriptions for history (all seasons, including cancelled)
        $allSubs = Subscription::whereIn('user_id', $userIds)
            ->with(['season', 'trainingPacks', 'payments'])
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('user_id');

        // Subscription history per user + can_reaffiliate flag
        $subscriptionHistory = collect($userIds)->mapWithKeys(function (int $uid) use ($allSubs, $season, $currentSubs): array {
            $subs = $allSubs->get($uid, collect());
            $currentSub = $currentSubs->get($uid);

            $hasCancelledCurrentSeason = $season && $subs
                ->where('season_id', $season->id)
                ->where('status', 'cancelled')
                ->isNotEmpty();

            $canReAffiliate = $hasCancelledCurrentSeason && ! $currentSub;

            return [$uid => [
                'history' => $subs->map(fn ($sub) => [
                    'season_name'       => $sub->season?->name ?? '—',
                    'season_id'         => $sub->season_id,
                    'status'            => $sub->status,
                    'is_competitive'    => $sub->is_competitive,
                    'amount_due'        => $sub->amount_due,
                    'amount_paid'       => $sub->amount_paid,
                    'enrolled_packs'    => $sub->trainingPacks
                        ->filter(fn ($p) => in_array($p->pivot->status, ['enrolled', 'pending'], true))
                        ->map(fn ($p) => ['name' => $p->name, 'status' => $p->pivot->status])
                        ->values()
                        ->toArray(),
                    'is_current_season'  => $season && $sub->season_id === $season->id,
                    'pending_payments'   => $sub->payments
                        ->where('status', 'pending')
                        ->map(fn ($p) => [
                            'id'         => $p->id,
                            'reference'  => $p->reference,
                            'amount_due' => (float) $p->amount_due,
                        ])
                        ->values()
                        ->toArray(),
                ])->toArray(),
                'can_reaffiliate' => $canReAffiliate,
            ]];
        })->toArray();

        // Available training packs for current season with per-user enrollment
        $availablePacks = [];
        if ($season) {
            $availablePacks = TrainingPack::with(['trainer', 'room'])
                ->where('season_id', $season->id)
                ->where('is_active', true)
                ->get()
                ->map(function (TrainingPack $pack) use ($userIds, $currentSubs): array {
                    $enrollments = [];
                    foreach ($userIds as $uid) {
                        $sub = $currentSubs->get($uid);
                        if (! $sub) {
                            $enrollments[$uid] = ['status' => null];
                            continue;
                        }
                        $enrolled = $sub->trainingPacks->firstWhere('id', $pack->id);
                        $enrollments[$uid] = $enrolled ? [
                            'status'   => $enrolled->pivot->status,
                            'position' => $enrolled->pivot->waitlist_position,
                            'deadline' => $enrolled->pivot->confirmation_deadline,
                        ] : ['status' => null];
                    }

                    return [
                        'id'              => $pack->id,
                        'name'            => $pack->name,
                        'description'     => $pack->description,
                        'price'           => (float) $pack->price,
                        'allow_discount'  => $pack->allow_discount,
                        'trainer_id'      => $pack->trainer_id,
                        'level'           => $pack->level->value,
                        'dot_color'       => match ($pack->level) {
                            TrainingLevel::ELITE, TrainingLevel::INTERMEDIATE => 'bg-error',
                            TrainingLevel::YOUNG_POTENTIAL                    => 'bg-info',
                            TrainingLevel::KIDS                               => 'bg-warning',
                            TrainingLevel::BEGINNERS                          => 'bg-success',
                            default                                           => 'bg-primary',
                        },
                        'coach'           => $pack->trainer
                            ? $pack->trainer->first_name . ' ' . $pack->trainer->last_name
                            : '—',
                        'is_open_enrollment' => $pack->is_open_enrollment,
                        'spots_remaining' => max(0, $pack->effectiveMaxParticipants() - $pack->enrolledCount()),
                        'waitlist_count'  => $pack->waitlistCount(),
                        'is_full'         => ! $pack->hasAvailableSpot(),
                        'enrollments'     => $enrollments,
                    ];
                })
                ->toArray();
        }

        return [
            'registrationsOpen'    => $season?->registrations_open ?? false,
            'currentSeasonName'    => $season?->name ?? '—',
            'subscriptionHistory'  => $subscriptionHistory,
            'availablePacks'       => $availablePacks,
            'memberSearchResults'  => strlen($this->memberSearchQuery) >= 2
                ? User::where(function ($q): void {
                    $q->where('first_name', 'like', "%{$this->memberSearchQuery}%")
                        ->orWhere('last_name', 'like', "%{$this->memberSearchQuery}%")
                        ->orWhere('email', 'like', "%{$this->memberSearchQuery}%");
                })
                ->whereNotIn('id', $alreadyAddedIds)
                ->limit(6)
                ->get()
                : collect(),
            'breadcrumbs' => $this->getBreadcrumbs(),
            'genders'              => Gender::options(),
        ];
    }
};
