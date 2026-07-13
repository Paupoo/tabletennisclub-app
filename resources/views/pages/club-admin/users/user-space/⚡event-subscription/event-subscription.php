<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\GeneratePaymentQR;
use App\Actions\Meetings\RespondToMeetingRsvp;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentPair;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Competitions\Tournament\Services\TournamentService;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingUser;
use App\Domains\Shared\Enums\MeetingStatusEnum;
use App\Domains\Shared\Enums\MeetingUserStatusEnum;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Domains\Trainings\Models\Training;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs, HasFilterDrawer, Toast;

    public ?int $cancelConfirmId = null;

    public bool $cancelConfirmModal = false;

    // ── Filters (R-filtres: drawer + chips)
    public string $eventType = '';

    // ── Meeting RSVP modal
    public bool $meetingRsvpModal = false;

    public bool $onlyPayable = false;

    // ── Doubles self-pairing
    public int $partnerTournamentId = 0;

    public bool $paymentModal = false;

    public ?string $paymentQr = null;

    public string $rsvpAttendance = 'confirmed';

    public string $rsvpMeal = 'skip';

    public ?int $rsvpMeetingId = null;

    public int $selectedPartnerId = 0;

    public ?int $selectedPaymentId = null;

    public User $user;

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

    public function cancelRegistration(int $tournamentId): void
    {
        $tournament = Tournament::findOrFail($tournamentId);
        app(TournamentService::class)->cancelRegistration($tournament, $this->user);
        unset($this->upcomingTournaments);
        $this->cancelConfirmModal = false;
        $this->cancelConfirmId = null;
        $this->warning(__('Registration cancelled.'));
    }

    public function confirmCancel(): void
    {
        if ($this->cancelConfirmId) {
            $this->cancelRegistration($this->cancelConfirmId);
        }
    }

    public function confirmTournamentSpot(int $tournamentId): void
    {
        TournamentRegistration::where('user_id', $this->user->id)
            ->where('tournament_id', $tournamentId)
            ->where('registration_status', 'spot_offered')
            ->update([
                'registration_status' => 'confirmed',
                'confirmation_deadline' => null,
            ]);

        unset($this->upcomingTournaments);
        $this->success(__('Spot confirmed!'));
    }

    /**
     * This user's meeting registrations (with payment), keyed by meeting id —
     * powers the row status/meal badges and the RSVP modal prefill.
     *
     * @return Illuminate\Support\Collection<int, MeetingUser>
     */
    #[Computed]
    public function meetingRegistrations(): Illuminate\Support\Collection
    {
        // Use the unfiltered base list: depending on the filtered
        // upcomingMeetings would create a circular computed dependency
        // when the "to pay only" filter is active.
        $meetingIds = $this->baseUpcomingMeetings()->pluck('id');

        if ($meetingIds->isEmpty()) {
            return collect();
        }

        return MeetingUser::with('payment')
            ->where('user_id', $this->user->id)
            ->whereIn('meeting_id', $meetingIds)
            ->get()
            ->keyBy('meeting_id');
    }

    public function clearFilters(): void
    {
        $this->reset(['eventType', 'onlyPayable']);
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public function getFilterChips(): array
    {
        return array_values(array_filter([
            $this->eventType !== '' ? [
                'key' => 'eventType',
                'label' => $this->eventTypeOptions()[$this->eventType] ?? $this->eventType,
            ] : null,
            $this->onlyPayable ? ['key' => 'onlyPayable', 'label' => __('To pay only')] : null,
        ]));
    }

    /**
     * No pagination on this page: plain property reset.
     */
    public function removeFilter(string $key): void
    {
        $this->reset([$key]);
    }

    /**
     * @return array<string, string>
     */
    public function eventTypeOptions(): array
    {
        return [
            'tournament' => __('Tournaments'),
            'meeting' => __('Meetings'),
            'training' => __('Trainings'),
        ];
    }

    public function mount(User $user): void
    {
        abort_unless(Auth::user()->is($user), 403);

        $this->user = $user;
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

    public function openCancelConfirm(int $tournamentId): void
    {
        $this->cancelConfirmId = $tournamentId;
        $this->cancelConfirmModal = true;
    }

    public function openMeetingRsvp(int $meetingId): void
    {
        $registration = $this->meetingRegistrations[$meetingId] ?? null;

        $this->rsvpMeetingId = $meetingId;
        $this->rsvpAttendance = $registration?->status === MeetingUserStatusEnum::DECLINED ? 'declined' : 'confirmed';
        $this->rsvpMeal = $registration?->meal_reserved === true ? 'reserve' : 'skip';
        $this->meetingRsvpModal = true;
    }

    public function openPartnerSelect(int $tournamentId): void
    {
        $this->partnerTournamentId = $tournamentId;
        $this->selectedPartnerId = 0;
        unset($this->availablePartners);
    }

    public function openPaymentModal(int $paymentId): void
    {
        $payment = Payment::findOrFail($paymentId);
        $this->selectedPaymentId = $paymentId;
        $this->paymentQr = (new GeneratePaymentQR)($payment);
        $this->paymentModal = true;
    }

    #[Computed]
    public function ourClub(): Club
    {
        return Club::ourClub()->first();
    }

    #[Computed]
    public function pendingPayments(): Collection
    {
        return Payment::where('status', 'pending')
            ->whereHasMorph('payable', [TournamentRegistration::class, MeetingUser::class],
                fn ($q) => $q->where('user_id', $this->user->id)
            )
            ->with(['payable' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                TournamentRegistration::class => ['tournament'],
                MeetingUser::class => ['meeting'],
            ])])
            ->get();
    }

    public function register(int $tournamentId): void
    {
        $tournament = Tournament::findOrFail($tournamentId);
        app(TournamentService::class)->registerUser($tournament, $this->user);
        unset($this->upcomingTournaments);
        $this->success(__('Registration confirmed!'));
    }

    public function registerAsPair(int $tournamentId): void
    {
        if (! $this->selectedPartnerId) {
            $this->error(__('Please select a partner.'));

            return;
        }

        TournamentPair::create([
            'tournament_id' => $tournamentId,
            'player1_id' => $this->user->id,
            'player2_id' => $this->selectedPartnerId,
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

    public function saveMeetingRsvp(): void
    {
        $meeting = Meeting::findOrFail($this->rsvpMeetingId);

        $attending = $this->rsvpAttendance === 'confirmed';
        $mealReserved = $meeting->has_meal ? ($this->rsvpMeal === 'reserve') : null;

        (new RespondToMeetingRsvp)($meeting, $this->user, $attending, $mealReserved);

        $this->meetingRsvpModal = false;
        unset($this->upcomingMeetings, $this->meetingRegistrations, $this->pendingPayments);
        $this->success(__('Your participation has been updated.'), icon: 'o-calendar-days');
    }

    /**
     * User's upcoming confirmed meetings, unfiltered — the base list shared by
     * both {@see upcomingMeetings()} and {@see meetingRegistrations()}.
     */
    private function baseUpcomingMeetings(): Collection
    {
        return $this->user->meetings()
            ->where('meetings.status', MeetingStatusEnum::CONFIRMED->value)
            ->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at')
            ->get();
    }

    #[Computed]
    public function upcomingMeetings(): Collection
    {
        return $this->baseUpcomingMeetings()
            ->when($this->onlyPayable, fn (Collection $meetings) => $meetings
                ->filter(function ($meeting): bool {
                    $payment = $this->meetingRegistrations->get($meeting->id)?->payment;

                    return $payment && $payment->status === 'pending';
                })
                ->values());
    }

    #[Computed]
    public function upcomingTournaments(): Collection
    {
        return Tournament::where('status', TournamentStatusEnum::PUBLISHED)
            ->where('start_date', '>=', now())
            ->withCount([
                'users AS active_registrations_count' => fn ($q) => $q->whereIn('tournament_user.registration_status', ['registered', 'confirmed', 'spot_offered']),
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
            ->get()
            ->when($this->onlyPayable, fn (Collection $tournaments) => $tournaments
                ->filter(function (Tournament $tournament): bool {
                    $pivot = $tournament->users->first()?->pivot;

                    return $pivot?->payment_id && ! $pivot->has_paid;
                })
                ->values());
    }

    /** @return Collection<int, Training> */
    #[Computed]
    public function upcomingTrainingSessions(): Collection
    {
        // Training sessions never carry a standalone payment.
        if ($this->onlyPayable) {
            return new Collection;
        }

        $season = Season::where('is_active', true)->first();
        if (! $season) {
            return new Collection;
        }

        // Get training pack IDs the user is subscribed to via their active subscription
        $packIds = $this->user->subscriptions()
            ->where('season_id', $season->id)
            ->whereNotIn('status', ['cancelled'])
            ->with('trainingPacks')
            ->get()
            ->flatMap(fn ($sub) => $sub->trainingPacks->pluck('id'));

        if ($packIds->isEmpty()) {
            return new Collection;
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
            'breadcrumbs' => $this->getBreadcrumbs(),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Events & Activities'));
    }
};
