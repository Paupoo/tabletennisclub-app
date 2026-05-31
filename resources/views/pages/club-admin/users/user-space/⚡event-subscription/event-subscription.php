<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\GeneratePaymentQR;
use App\Domains\Shared\Enums\MeetingStatusEnum;
use App\Domains\Shared\Enums\MeetingUserStatusEnum;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Models\ClubAdmin\Users\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentPair;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Trainings\Models\Training;
use App\Domains\Competitions\Tournament\Services\TournamentService;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast, HasBreadcrumbs;

    public User $user;

    public bool $onlyUpcoming = true;

    public bool $paymentModal = false;

    public ?int $selectedPaymentId = null;

    public bool $cancelConfirmModal = false;

    public ?int $cancelConfirmId = null;

    public ?string $paymentQr = null;

    // ── Doubles self-pairing
    public int $partnerTournamentId = 0;

    public int $selectedPartnerId = 0;

    #[Computed]
    public function upcomingTournaments(): Collection
    {
        return Tournament::where('status', TournamentStatusEnum::PUBLISHED)
            ->where('start_date', '>=', now())
            ->withCount([
                'users AS active_registrations_count' => fn ($q) =>
                    $q->whereIn('tournament_user.registration_status', ['registered', 'confirmed', 'spot_offered']),
            ])
            ->with([
                'users' => fn ($q) => $q->where('tournament_user.user_id', $this->user->id),
                'pairs' => fn ($q) => $q
                    ->where(fn ($q2) => $q2
                        ->where('player1_id', $this->user->id)
                        ->orWhere('player2_id', $this->user->id)
                    )
                    ->with(['player1', 'player2']),
            ])
            ->orderBy('start_date')
            ->get();
    }

    #[Computed]
    public function availablePartners(): array
    {
        if (! $this->partnerTournamentId) {
            return [];
        }

        $pairedIds = TournamentPair::where('tournament_id', $this->partnerTournamentId)
            ->get()
            ->flatMap(fn ($p) => [$p->player1_id, $p->player2_id])
            ->unique()
            ->toArray();

        return Tournament::findOrFail($this->partnerTournamentId)
            ->users()
            ->wherePivotIn('registration_status', ['registered', 'confirmed', 'spot_offered'])
            ->whereNotIn('users.id', $pairedIds)
            ->where('users.id', '!=', $this->user->id)
            ->get()
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->full_name])
            ->toArray();
    }

    public function openPartnerSelect(int $tournamentId): void
    {
        $this->partnerTournamentId = $tournamentId;
        $this->selectedPartnerId = 0;
        unset($this->availablePartners);
    }

    public function registerAsPair(int $tournamentId): void
    {
        if (! $this->selectedPartnerId) {
            $this->error(__('Please select a partner.'));

            return;
        }

        TournamentPair::create([
            'tournament_id' => $tournamentId,
            'player1_id'    => $this->user->id,
            'player2_id'    => $this->selectedPartnerId,
            'registered_by' => $this->user->id,
        ]);

        $partner = User::find($this->selectedPartnerId);
        if ($partner) {
            $tournament = Tournament::findOrFail($tournamentId);
            if (! $tournament->users()->where('users.id', $this->selectedPartnerId)
                ->wherePivotIn('registration_status', ['registered', 'confirmed', 'spot_offered'])
                ->exists()
            ) {
                app(TournamentService::class)->registerUser($tournament, $partner);
            }
        }

        $this->partnerTournamentId = 0;
        $this->selectedPartnerId = 0;
        unset($this->upcomingTournaments, $this->availablePartners);
        $this->success(__('Pair registered!'), icon: 'o-user-group');
    }

    public function removeFromPair(int $tournamentId): void
    {
        TournamentPair::where('tournament_id', $tournamentId)
            ->where(fn ($q) => $q
                ->where('player1_id', $this->user->id)
                ->orWhere('player2_id', $this->user->id)
            )
            ->delete();

        unset($this->upcomingTournaments);
        $this->warning(__('Pair removed.'));
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

    public function confirmTournamentSpot(int $tournamentId): void
    {
        TournamentRegistration::where('user_id', $this->user->id)
            ->where('tournament_id', $tournamentId)
            ->where('registration_status', 'spot_offered')
            ->update([
                'registration_status'   => 'confirmed',
                'confirmation_deadline' => null,
            ]);

        unset($this->upcomingTournaments);
        $this->success(__('Spot confirmed!'));
    }

    public function openCancelConfirm(int $tournamentId): void
    {
        $this->cancelConfirmId    = $tournamentId;
        $this->cancelConfirmModal = true;
    }

    public function cancelRegistration(int $tournamentId): void
    {
        $tournament = Tournament::findOrFail($tournamentId);
        app(TournamentService::class)->cancelRegistration($tournament, $this->user);
        unset($this->upcomingTournaments);
        $this->cancelConfirmModal = false;
        $this->cancelConfirmId    = null;
        $this->warning(__('Registration cancelled.'));
    }

    public function confirmCancel(): void
    {
        if ($this->cancelConfirmId) {
            $this->cancelRegistration($this->cancelConfirmId);
        }
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

    #[Computed]
    public function upcomingMeetings(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->user->meetings()
            ->where('status', MeetingStatusEnum::CONFIRMED->value)
            ->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at')
            ->get();
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Events & Activities'));
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
        ];
    }
};
