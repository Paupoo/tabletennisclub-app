<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\GeneratePaymentQR;
use App\Actions\ClubAdmin\Payments\GeneratePaymentReference;
use App\Actions\ClubAdmin\Subscriptions\CalculatePriceAction;
use App\Actions\ClubAdmin\Subscriptions\CreateSubscriptionAction;
use App\Mail\PaymentInvitationEmail;
use App\Models\ClubAdmin\Payment\Payment;
use App\Models\ClubAdmin\Subscription\Subscription;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Season;
use App\Models\ClubEvents\Training\TrainingPack;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    public array $familyBasket = [];
    public bool $memberDrawer = false;
    public bool $paymentGenerated = false;
    public bool $reviewModal = false;
    public array $paymentData = [];
    public string $search = '';
    public string $searchMember = '';
    public ?int $currentRequestId = null;

    #[Computed]
    public function registrationClosed(): bool
    {
        return ! (Season::current()?->registrations_open ?? false);
    }

    public function addToBasket($userId): void
    {
        $user = User::find($userId);

        $this->familyBasket[$userId] = [
            'name' => $user->first_name . ' ' . $user->last_name,
            'licence_type' => 'recreative',
            'trainings' => [],
        ];

        $this->searchMember = '';
    }

    public function approve(): void
    {
        $subscription = Subscription::with(['user', 'trainingPacks'])->find($this->currentRequestId);
        (new CalculatePriceAction)($subscription);
        $subscription->confirm();

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
            ['key' => 'id', 'label' => '#', 'class' => 'w-16 text-gray-400'],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'type', 'label' => 'Licence'],
            ['key' => 'members_count', 'label' => 'Members'],
            ['key' => 'trainings_count', 'label' => 'Trainings', 'sortable' => false],
            ['key' => 'status', 'label' => 'Status'],
        ];
    }

    public function registrations(): Collection
    {
        $season = Season::current();

        return Subscription::with(['user', 'trainingPacks'])
            ->when($season, fn ($q) => $q->forSeason($season))
            ->where('status', 'pending')
            ->when($this->search, fn ($q) => $q->whereHas('user', fn ($u) => $u
                ->where('first_name', 'like', "%{$this->search}%")
                ->orWhere('last_name', 'like', "%{$this->search}%")
            ))
            ->get()
            ->map(fn (Subscription $sub) => (object) [
                'id'             => $sub->id,
                'first_name'     => $sub->user->first_name,
                'last_name'      => $sub->user->last_name,
                'name'           => $sub->user->first_name . ' ' . $sub->user->last_name,
                'type'           => $sub->is_competitive ? __('Compétition') : __('Récréative'),
                'members_count'  => 1,
                'trainings_count' => $sub->trainings_count,
                'status'         => $sub->status,
                'members'        => [[
                    'first_name' => $sub->user->first_name,
                    'last_name'  => $sub->user->last_name,
                    'trainings'  => $sub->trainingPacks->pluck('name')->toArray(),
                ]],
                'total_price' => $sub->amount_due,
            ]);
    }

    public function reject(): void
    {
        $subscription = Subscription::find($this->currentRequestId);
        $subscription->cancel();
        $this->warning(__('Request rejected.'));
        $this->reviewModal = false;
        $this->currentRequestId = null;
    }

    public function removeFromBasket($userId): void
    {
        unset($this->familyBasket[$userId]);
    }

    public function render(): View
    {
        return $this->view([
            'headers'       => $this->headers(),
            'registrations' => $this->registrations(),
        ]);
    }

    public function review(int $id): void
    {
        $this->currentRequestId = $id;
        $this->paymentGenerated = false;
        $this->paymentData = [];
        $this->reviewModal = true;
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
            'currentRequest' => $this->currentRequestId
                ? $this->registrations()->firstWhere('id', $this->currentRequestId)
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
