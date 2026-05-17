<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\GeneratePaymentQR;
use App\Actions\ClubAdmin\Subscriptions\CalculatePriceAction;
use App\Actions\ClubAdmin\Subscriptions\CreateSubscriptionAction;
use App\Enums\Gender;
use App\Enums\TrainingLevel;
use App\Models\ClubAdmin\Subscription\Subscription;
use App\Models\Clubadmin\Users\User;
use App\Models\ClubEvents\Interclub\Season;
use App\Models\ClubEvents\Training\TrainingPack;
use App\Support\Breadcrumb;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    // --- Gestion du Modal "Ajouter un membre" ---
    public bool $addMemberModal = false;

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

    public array $registrations = [];

    // Subscriptions existantes pour la saison courante, indexées par user_id
    public array $existingSubscriptions = [];

    // Modal de détails de paiement
    public bool $paymentModal = false;
    public array $paymentDetails = [];

    // Modal "Add a family member" — recherche vs création
    public string $memberSearchQuery = '';
    public string $memberModalMode   = 'search'; // 'search' | 'create'

    public string $selectedTab;

    // --- Gestion du panier d'inscriptions ---
    // Contient les données d'inscription pour chaque membre : id, formula, trainings[]
    public User $user;

    public function addRegistrationTab(User $user): void
    {
        $season = Season::current();

        // Cherche une subscription existante (non annulée) pour la saison courante
        $existing = $season
            ? Subscription::where('user_id', $user->id)
                ->where('season_id', $season->id)
                ->whereNotIn('status', ['cancelled'])
                ->with('trainingPacks')
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
                'trainings'   => $existing->trainingPacks->pluck('id')->map(fn ($id) => (string) $id)->toArray(),
            ];
        }

        $this->registrations[$user->id] = [
            'user_id'   => $user->id,
            'name'      => $user->first_name . ' ' . $user->last_name,
            'formula'   => $existing?->is_competitive ? 'competitive' : 'recreative',
            'trainings' => $existing ? $existing->trainingPacks->pluck('id')->map(fn ($id) => (string) $id)->toArray() : [],
        ];
    }

    public function confirmSubscription(): void
    {
        $season = Season::current();
        if (! $season) {
            $this->error(__('No active season found.'));
            return;
        }

        if (! $season->registrations_open) {
            $this->error(__('Registrations are currently closed.'));
            return;
        }

        $createAction = new CreateSubscriptionAction;
        $calculateAction = new CalculatePriceAction;

        foreach ($this->registrations as $userId => $reg) {
            // Ignore les membres qui ont déjà une subscription active
            if (isset($this->existingSubscriptions[$userId])) {
                continue;
            }

            $user = User::find((int) $userId);
            $subscription = $createAction->execute($user, $season, [
                'is_competitive'  => ($reg['formula'] ?? 'recreative') === 'competitive',
                'trainings_count' => count($reg['trainings'] ?? []),
            ]);

            if (! empty($reg['trainings'])) {
                $subscription->trainingPacks()->sync($reg['trainings']);
            }

            $calculateAction($subscription);
        }

        $this->success(__('Your registration has been submitted. The club will process it shortly.'));
    }

    public function openPaymentModal(int $userId): void
    {
        $existingSub = $this->existingSubscriptions[$userId] ?? null;
        if (! $existingSub || $existingSub['status'] !== 'confirmed') {
            return;
        }

        $season = Season::current();
        $subscription = Subscription::where('user_id', $userId)
            ->where('season_id', $season->id)
            ->where('status', 'confirmed')
            ->first();

        $payment = $subscription?->payments()
            ->where('status', 'pending')
            ->latest()
            ->first();

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

        // Création rapide du profil avec un mot de passe aléatoire
        $newMember = User::firstOrCreate([
            'email' => $this->new_email,
        ],
            [
                'first_name' => $this->new_first_name,
                'last_name' => $this->new_last_name,
                'email' => $this->new_email,
                'birthdate' => $this->new_birthdate,
                'gender' => $this->new_gender,
                'phone_number' => $this->phone_number ?? User::first()->phone_number,

                // On hérite des infos du parent
                'street' => Auth::user()->street,
                'postal_code' => Auth::user()->postal_code,
                'city' => Auth::user()->city,

                // LA CORRECTION EST ICI 👇
                'password' => Hash::make(Str::random(16)),
            ]);

        $this->addRegistrationTab($newMember);

        $this->reset(['new_first_name', 'new_last_name', 'new_birthdate', 'new_gender', 'new_email', 'new_phone_number', 'addMemberModal', 'memberSearchQuery']);
        $this->memberModalMode = 'search';
        $this->success(__('Member added successfully!'));
    }

    public function mount(): void
    {
        // On initialise avec le membre connecté par défaut
        $this->user = Auth::user();
        $this->addRegistrationTab($this->user);
        $this->selectedTab = (string) 'tab-' . $this->user->id;
    }

    #[Computed()]
    public function stats(): array
    {
        $registrations = collect($this->registrations);
        $countMembers = $registrations->count();

        // 1. Compter les types de licences
        $countCompetitors = $registrations->where('formula', 'competitive')->count();
        $countRecreative = $countMembers - $countCompetitors;

        // 2. Compter le total des sessions d'entraînement choisies par la famille
        $totalSessions = $registrations->sum(fn ($r) => count($r['trainings'] ?? []));

        // 3. Calcul du prix des licences (Base)
        $basePrice = ($countRecreative * 60) + ($countCompetitors * 125);

        // 4. Calcul du prix des entraînements (Logique de ton Alpine)
        $trainingPrice = 0;
        if ($totalSessions > 0) {
            // 90€ si solo, 80€ si famille (>1 membre)
            $firstSessionRate = $countMembers > 1 ? 80 : 90;

            if ($totalSessions === 1) {
                $trainingPrice = $firstSessionRate;
            } else {
                // Première session au tarif plein (ou réduit famille) + les autres à 80€
                $trainingPrice = $firstSessionRate + (($totalSessions - 1) * 80);
            }
        }

        return [
            'base' => $basePrice,
            'training' => $trainingPrice,
            'total' => $basePrice + $trainingPrice,
            'countMembers' => $countMembers,
            'countSessions' => $totalSessions,
        ];
    }

    public function with(): array
    {
        $season = Season::current();
        $alreadyAddedIds = array_keys($this->registrations);

        return [
            'registrationsOpen' => $season?->registrations_open ?? false,
            'memberSearchResults' => strlen($this->memberSearchQuery) >= 2
                ? User::where(function ($q) {
                    $q->where('first_name', 'like', "%{$this->memberSearchQuery}%")
                      ->orWhere('last_name', 'like', "%{$this->memberSearchQuery}%")
                      ->orWhere('email', 'like', "%{$this->memberSearchQuery}%");
                })
                ->whereNotIn('id', $alreadyAddedIds)
                ->limit(6)
                ->get()
                : collect(),
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->current(__('Registration management'))
                ->toArray(),
            'licenceTypes' => collect([
                ['id' => 'competitive', 'value' => 'competitive'],
                ['id' => 'recreative', 'value' => 'recreative'],
            ]),
            'genders' => Gender::options(),
            'trainings' => TrainingPack::with(['trainer', 'room'])
                ->where('season_id', $season?->id)
                ->get()
                ->map(fn (TrainingPack $pack) => [
                    'id'        => $pack->id,
                    'day'       => $pack->name,
                    'time'      => $pack->type->value,
                    'coach'     => $pack->trainer
                        ? $pack->trainer->first_name . ' ' . $pack->trainer->last_name
                        : '—',
                    'level'     => $pack->level->value,
                    'dot_color' => match ($pack->level) {
                        TrainingLevel::ELITE, TrainingLevel::INTERMEDIATE => 'bg-error',
                        TrainingLevel::YOUNG_POTENTIAL                    => 'bg-info',
                        TrainingLevel::KIDS                               => 'bg-warning',
                        TrainingLevel::BEGINNERS                          => 'bg-success',
                        default                                           => 'bg-primary',
                    },
                    'spots' => $pack->effectiveMaxParticipants(),
                    'full'  => $pack->effectiveMaxParticipants() > 0 && $pack->enrolledCount() >= $pack->effectiveMaxParticipants(),
                ])
                ->toArray(),
        ];
    }
};
