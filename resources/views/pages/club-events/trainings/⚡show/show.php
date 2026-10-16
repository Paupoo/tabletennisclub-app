<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\InviteToPayAction;
use App\Actions\ClubAdmin\Subscriptions\AddMemberToTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\EnrollInTrainingCampAction;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Services\TrainingAttendanceReport;
use App\Domains\Trainings\Services\TrainingPackProrata;
use App\Domains\Trainings\Services\TrainingStaffing;
use App\Livewire\Concerns\GrantsInlineDiscount;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\ViewsTrainingPack;
use App\Support\Breadcrumb;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Mary\Traits\Toast;

/**
 * La fiche d'un pack : son identité, ses chiffres, et l'onglet ouvert.
 */
new class extends Component
{
    use GrantsInlineDiscount;
    use HasBreadcrumbs;
    use Toast;
    use ViewsTrainingPack;

    // ── Ajout manuel d'un membre par le comité ────────────────────────────────
    public bool $addMemberModal = false;

    /** Le prix d'un stage pour ce membre, en euros ; vide = le prix du stage. */
    public string $addMemberPrice = '';

    public string $addMemberPriceReason = '';

    public string $addMemberStartsOn = '';

    public int $addMemberUserId = 0;

    /** Présent depuis le début du pack, encodé en retard : plein tarif. */
    public bool $addMemberWholePack = false;

    public bool $packSparringModal = false;

    public int $packSparringUserId = 0;

    /** L'onglet ouvert : le seul monté, d'où son adresse. */
    #[Url]
    public string $tab = 'roster';

    /**
     * Membres affiliés à la saison du pack et pas encore dedans.
     *
     * @return array<int, array{id: int, name: string}>
     */
    #[Computed]
    public function addMemberOptions(): array
    {
        $pack = $this->selectedPack;

        if (! $pack) {
            return [];
        }

        $alreadyIn = DB::table('subscription_training_pack')
            ->join('subscriptions', 'subscriptions.id', '=', 'subscription_training_pack.subscription_id')
            ->where('subscription_training_pack.training_pack_id', $pack->id)
            ->whereIn('subscription_training_pack.status', ['enrolled', 'pending', 'offered'])
            ->pluck('subscriptions.user_id');

        return User::query()
            ->whereHas('subscriptions', fn (Builder $q) => $q
                ->where('season_id', $pack->season_id)
                ->whereIn('status', ['pending', 'confirmed', 'paid'])
            )
            ->whereNotIn('id', $alreadyIn)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->last_name . ' ' . $user->first_name,
            ])
            ->toArray();
    }

    /**
     * Le pack déborderait-il si on ajoutait quelqu'un maintenant ?
     *
     * Sert à afficher l'avertissement, pas à interdire : le comité franchit le
     * plafond en connaissance de cause (décision #10).
     */
    #[Computed]
    public function addMemberOverCapacity(): bool
    {
        $pack = $this->selectedPack;

        return $pack !== null && ! $pack->hasAvailableSpot();
    }

    /**
     * Le pack ouvert a-t-il déjà commencé ?
     *
     * Avant son début, tout ajout se facture plein tarif : « présent depuis le
     * début » ne changerait rien et n'est pas proposé.
     */
    #[Computed]
    public function addMemberPackStarted(): bool
    {
        $pack = $this->selectedPack;

        return $pack !== null && $pack->pack_start_date->lt(Carbon::today());
    }

    /**
     * Inscrit un membre dans le pack ouvert, sur décision du comité.
     *
     * Court-circuite volontairement le verrou et le plafond : c'est le pendant
     * de « inscriptions closes », et le moyen de régulariser quelqu'un que la
     * feuille de présence montre là depuis des mois.
     */
    public function addMemberToPack(): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $pack = $this->selectedPack;

        if (! $pack || $this->addMemberUserId === 0) {
            return;
        }

        $subscription = Subscription::where('user_id', $this->addMemberUserId)
            ->where('season_id', $pack->season_id)
            ->first();

        if (! $subscription) {
            $this->error(__('This member has no membership for the season. Affiliate them first.'));

            return;
        }

        if ($pack->is_camp) {
            $this->addMemberToCamp($pack, $subscription);

            return;
        }

        $previousAmountDue = (float) $subscription->amount_due;

        try {
            $complement = (new AddMemberToTrainingPackAction)(
                $subscription,
                $pack,
                $this->addMemberWholePack && $this->addMemberPackStarted
                    ? (new TrainingPackProrata)->wholePackStart($pack)
                    : ($this->addMemberStartsOn ?: null),
                $subscription->has_other_family_members ? 2 : 1,
            );

            // Après l'ajout : le complément est calculé sur l'écart de montant
            // dû, et la remise le rabote ensuite — comme pour la validation
            // d'une demande de pack.
            $subscription = $subscription->fresh();
            $this->applyInlineDiscount($subscription, (float) $subscription->amount_due - $previousAmountDue);
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return;
        }

        // Réclamé une fois la remise posée : l'invitation lit le solde net.
        if ($complement instanceof Payment) {
            (new InviteToPayAction)($complement->fresh());
        }

        $this->warnWhenUnreachable($subscription->user);

        $this->addMemberModal = false;
        $this->addMemberUserId = 0;
        $this->addMemberStartsOn = '';
        $this->addMemberWholePack = false;

        $this->forgetPack();

        $this->success(__(':member added to :pack.', [
            'member' => $subscription->user->first_name . ' ' . $subscription->user->last_name,
            'pack' => $pack->name,
        ]), icon: 'o-user-plus');
    }

    /**
     * Tout membre peut recevoir une séance ou devenir sparring quand c'est le
     * comité qui le décide : c'est ce geste qui fait entrer quelqu'un dans
     * l'encadrement.
     *
     * @return array<int, array{id: int, name: string}>
     */
    #[Computed]
    public function anyMemberOptions(): array
    {
        return User::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('users.id')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->last_name . ' ' . $user->first_name])
            ->all();
    }

    public function attachPackSparring(): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $pack = $this->selectedPack;

        if (! $pack || ! $this->packSparringUserId) {
            return;
        }

        try {
            app(TrainingStaffing::class)->attachToPack($pack, User::findOrFail($this->packSparringUserId), Auth::user());
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->packSparringModal = false;
        $this->packSparringUserId = 0;

        $this->forgetPack();
    }

    public function detachPackSparring(int $userId): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $pack = $this->selectedPack;

        if (! $pack) {
            return;
        }

        app(TrainingStaffing::class)->detachFromPack($pack, User::findOrFail($userId), Auth::user());

        $this->forgetPack();
    }

    public function mount(TrainingPack $pack): void
    {
        $this->packId = $pack->id;

        if (! in_array($this->tab, ['roster', 'sessions', 'attendance'], true)) {
            $this->tab = 'roster';
        }
    }

    public function openAddMember(): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $this->addMemberUserId = 0;
        $this->addMemberStartsOn = '';
        $this->addMemberWholePack = false;
        $this->addMemberPrice = '';
        $this->addMemberPriceReason = '';
        $this->addMemberModal = true;

        unset($this->addMemberOptions, $this->addMemberOverCapacity);
    }

    public function openPackSparring(): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $this->packSparringUserId = 0;
        $this->packSparringModal = true;
    }

    /**
     * Le pointage d'un pack, ramené à ce qu'une fiche a besoin d'afficher.
     *
     * Deux requêtes, pas deux par membre : {@see TrainingAttendanceReport::memberRate()}
     * en fait deux à chaque appel, et la fiche l'appellerait une fois par ligne.
     *
     * Le dénominateur est le nombre de séances **pointées** du pack, le même pour
     * tout le monde — c'est la définition du domaine, et la seule qui permette de
     * comparer deux lignes entre elles. Un membre sans aucune ligne de pointage
     * est donc à 0 %, ce qui est exact : il n'est venu à aucune séance pointée.
     *
     * @return array{counted: int, present: array<int, int>}
     */
    #[Computed]
    public function packAttendance(): array
    {
        $pack = $this->selectedPack;

        if (! $pack) {
            return ['counted' => 0, 'present' => []];
        }

        $counted = Training::query()
            ->where('training_pack_id', $pack->id)
            ->where('status', 'scheduled')
            ->whereNotNull('attendance_taken_at')
            ->count();

        if ($counted === 0) {
            return ['counted' => 0, 'present' => []];
        }

        $present = DB::table('training_user')
            ->join('trainings', 'trainings.id', '=', 'training_user.training_id')
            ->where('trainings.training_pack_id', $pack->id)
            ->where('trainings.status', 'scheduled')
            ->whereNotNull('trainings.attendance_taken_at')
            ->where('training_user.status', 'present')
            ->groupBy('training_user.user_id')
            ->pluck(DB::raw('COUNT(*)'), 'training_user.user_id')
            ->map(intval(...))
            ->all();

        return ['counted' => $counted, 'present' => $present];
    }

    /**
     * Les chiffres de tête de la fiche d'un pack.
     *
     * Comptés en base plutôt que lus dans les onglets : seul l'onglet ouvert
     * est monté, et la fiche ne doit pas charger les trois pour quatre totaux.
     * Les inscriptions non affiliées sont écartées comme dans l'onglet Membres.
     *
     * @return array{enrolled: int, waiting: int, pending: int, max: int, capped: bool, spotsLeft: int|null, sessions: int, held: int, cancelled: int, upcoming: int, turnout: int|null}
     */
    #[Computed]
    public function packSummary(): array
    {
        $pack = $this->selectedPack;

        if (! $pack) {
            return ['enrolled' => 0, 'waiting' => 0, 'pending' => 0, 'max' => 0, 'capped' => false,
                'spotsLeft' => null, 'sessions' => 0, 'held' => 0, 'cancelled' => 0, 'upcoming' => 0, 'turnout' => null];
        }

        $lines = $pack->subscriptions()
            ->withPivot('status')
            ->affiliated()
            ->get(['subscriptions.id', 'subscriptions.user_id']);

        $enrolledIds = $lines->filter(fn (Subscription $line): bool => $line->pivot->status === 'enrolled')->pluck('user_id');
        $attendance = $this->packAttendance;

        $sessions = Training::query()
            ->where('training_pack_id', $pack->id)
            ->selectRaw("COUNT(*) AS total, SUM(status <> 'scheduled') AS cancelled, SUM(status = 'scheduled' AND start < ?) AS held", [Carbon::now()])
            ->first();

        $total = (int) $sessions?->total;
        $cancelled = (int) $sessions?->cancelled;
        $held = (int) $sessions?->held;

        $enrolled = $enrolledIds->count();
        $max = $pack->effectiveMaxParticipants();
        // Un pack en libre-service n'a pas de places à compter : le plafond hérité
        // de la salle ne s'y applique pas (même règle que la carte de la liste).
        $capped = ! $pack->is_open_enrollment && $max > 0;

        $presentAmongEnrolled = $enrolledIds->sum(fn (int $userId): int => $attendance['present'][$userId] ?? 0);

        return [
            'enrolled' => $enrolled,
            'waiting' => $lines->filter(fn (Subscription $line): bool => in_array($line->pivot->status, ['waiting', 'offered'], true))->count(),
            'pending' => $lines->filter(fn (Subscription $line): bool => $line->pivot->status === 'pending')->count(),
            'max' => $max,
            'capped' => $capped,
            'spotsLeft' => $capped ? max(0, $max - $pack->committedCount()) : null,
            'sessions' => $total,
            'held' => $held,
            'cancelled' => $cancelled,
            'upcoming' => $total - $cancelled - $held,
            'turnout' => ($attendance['counted'] === 0 || $enrolled === 0)
                ? null
                : (int) round(($presentAmongEnrolled / ($attendance['counted'] * $enrolled)) * 100),
        ];
    }

    /** Un onglet a écrit : les chiffres de tête ne sont plus les mêmes. */
    #[On('training-pack-changed')]
    public function refreshSummary(): void
    {
        unset($this->selectedPack, $this->packSummary, $this->packAttendance, $this->addMemberOptions, $this->addMemberOverCapacity);
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'selectedPack' => $this->selectedPack,
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->trainings()
            ->current($this->selectedPack?->name ?? __('Training pack'));
    }

    /**
     * Inscrit un membre à un stage depuis l'écran : directement, au-delà du
     * plafond s'il le faut, au prix du stage ou à un prix fixé avec son motif.
     */
    private function addMemberToCamp(TrainingPack $pack, Subscription $subscription): void
    {
        $price = trim($this->addMemberPrice) === '' ? null : (float) str_replace(',', '.', $this->addMemberPrice);

        try {
            (new EnrollInTrainingCampAction)($subscription, $pack, byClub: true, overrideAmount: $price, overrideReason: $this->addMemberPriceReason);
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->warnWhenUnreachable($subscription->user);

        $this->addMemberModal = false;
        $this->addMemberUserId = 0;
        $this->addMemberPrice = '';
        $this->addMemberPriceReason = '';

        $this->forgetPack();

        $this->success(__(':member added to :pack.', [
            'member' => $subscription->user->first_name . ' ' . $subscription->user->last_name,
            'pack' => $pack->name,
        ]), icon: 'o-user-plus');
    }

    /** La fiche a écrit : elle se relit, et l'onglet ouvert aussi. */
    private function forgetPack(): void
    {
        $this->refreshSummary();
        $this->dispatch('training-pack-refresh');
    }
};
