<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\GeneratePaymentQR;
use App\Enums\TournamentStatusEnum;
use App\Models\ClubAdmin\Payment\Payment;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Season;
use App\Models\ClubEvents\Tournament\Tournament;
use App\Models\ClubEvents\Tournament\TournamentRegistration;
use App\Models\ClubEvents\Training\Training;
use App\Services\TournamentService;
use App\Support\Breadcrumb;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    public User $user;

    public bool $onlyUpcoming = true;

    public bool $paymentModal = false;

    public ?int $selectedPaymentId = null;

    public ?string $paymentQr = null;

    #[Computed]
    public function upcomingTournaments(): Collection
    {
        return Tournament::where('status', TournamentStatusEnum::PUBLISHED)
            ->where('start_date', '>=', now())
            ->withCount([
                'users AS active_registrations_count' => fn ($q) =>
                    $q->whereIn('tournament_user.registration_status', ['registered', 'confirmed', 'spot_offered']),
            ])
            ->with(['users' => fn ($q) => $q->where('tournament_user.user_id', $this->user->id)])
            ->orderBy('start_date')
            ->get();
    }

    #[Computed]
    public function pendingPayments(): Collection
    {
        return Payment::where('status', 'pending')
            ->whereHasMorph('payable', TournamentRegistration::class,
                fn ($q) => $q->where('user_id', $this->user->id)
            )
            ->with(['payable.tournament'])
            ->get();
    }

    #[Computed]
    public function myPastTournaments(): Collection
    {
        return $this->user->tournaments()
            ->where('start_date', '<', now())
            ->orderByDesc('start_date')
            ->limit(10)
            ->get();
    }

    public function openPaymentModal(int $paymentId): void
    {
        $payment = Payment::findOrFail($paymentId);
        $this->selectedPaymentId = $paymentId;
        $this->paymentQr = (new GeneratePaymentQR)($payment);
        $this->paymentModal = true;
    }

    public function register(int $tournamentId): void
    {
        $tournament = Tournament::findOrFail($tournamentId);
        app(TournamentService::class)->registerUser($tournament, $this->user);
        unset($this->upcomingTournaments);
        $this->success(__('Registration confirmed!'));
    }

    public function cancelRegistration(int $tournamentId): void
    {
        $tournament = Tournament::findOrFail($tournamentId);
        app(TournamentService::class)->cancelRegistration($tournament, $this->user);
        unset($this->upcomingTournaments);
        $this->warning(__('Registration cancelled.'));
    }

    /** @return Collection<int, Training> */
    #[Computed]
    public function upcomingTrainingSessions(): Collection
    {
        $season = Season::where('is_active', true)->first();
        if (! $season) {
            return new Collection();
        }

        // Get training pack IDs the user is subscribed to via their active subscription
        $packIds = $this->user->subscriptions()
            ->where('season_id', $season->id)
            ->whereNotIn('status', ['cancelled'])
            ->with('trainingPacks')
            ->get()
            ->flatMap(fn ($sub) => $sub->trainingPacks->pluck('id'));

        if ($packIds->isEmpty()) {
            return new Collection();
        }

        return Training::with(['trainingPack', 'room'])
            ->whereIn('training_pack_id', $packIds)
            ->where('status', 'scheduled')
            ->where('start', '>=', Carbon::now())
            ->orderBy('start')
            ->limit(5)
            ->get();
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->current(__('Events & Activities'))
                ->toArray(),
        ];
    }
};
