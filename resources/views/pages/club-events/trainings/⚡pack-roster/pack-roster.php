<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Subscriptions\AdjustTrainingCampLineAction;
use App\Actions\ClubAdmin\Subscriptions\DecideTrainingCampRequestAction;
use App\Actions\ClubAdmin\Subscriptions\LeaveTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\MoveMemberBetweenTrainingPacksAction;
use App\Actions\ClubAdmin\Subscriptions\RequestSubscriptionRefundAction;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Subscriptions\Models\SubscriptionTrainingPack;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Services\TrainingAttendanceReport;
use App\Domains\Trainings\Services\TrainingCampBilling;
use App\Domains\Trainings\Services\TrainingPackProrata;
use App\Livewire\Concerns\ViewsTrainingPack;
use App\Support\LocaleSort;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;

/**
 * L'onglet Membres d'un pack : inscrits, demandes, file d'attente, départs.
 */
new class extends Component
{
    use Toast;
    use ViewsTrainingPack;

    public string $campPriceAmount = '';

    // ── Prix d'un membre sur un stage ─────────────────────────────────────────
    public bool $campPriceModal = false;

    public string $campPriceReason = '';

    public int $campPriceUserId = 0;

    public bool $moveMemberModal = false;

    public string $moveMemberName = '';

    public int $moveMemberUserId = 0;

    public int $moveTargetPackId = 0;

    public bool $removeFromRosterModal = false;

    public string $removeFromRosterName = '';

    /** `pending` ou `waiting`/`offered` : la modale ne dit pas la même chose. */
    public string $removeFromRosterStatus = '';

    public int $removeFromRosterUserId = 0;

    /** Accepte en une fois toutes les demandes en attente du stage ouvert. */
    public function acceptAllCampRequests(): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $pack = $this->selectedPack;

        if (! $pack?->is_camp) {
            return;
        }

        $lines = SubscriptionTrainingPack::query()
            ->with('subscription.user', 'trainingPack')
            ->where('training_pack_id', $pack->id)
            ->where('status', 'pending')
            ->where('invoiced_separately', true)
            ->get();

        $decide = new DecideTrainingCampRequestAction;
        $lines->each(fn (SubscriptionTrainingPack $line) => $decide->approve($line));

        $this->forgetRoster();
        $this->success(trans_choice('{1}One request accepted.|[2,*]:count requests accepted.', $lines->count(), ['count' => $lines->count()]));
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    /**
     * Accepte une demande d'inscription à un stage : la place est validée et
     * la facture du stage part aussitôt.
     */
    public function acceptCampRequest(int $userId): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $line = $this->campLineFor($userId);

        if ($line === null) {
            return;
        }

        try {
            (new DecideTrainingCampRequestAction)->approve($line);
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->forgetRoster();
        $this->success(__('Request of :member accepted.', ['member' => $line->subscription->user->full_name]));
    }

    // ── Render ────────────────────────────────────────────────────────────────

    // ── Actions sur le roster ─────────────────────────────────────────────────
    //
    // Les quatre gestes sont gardés par SubscriptionsManage, pas par
    // TrainingsManage qui ouvre l'écran : sortir quelqu'un d'un pack touche à
    // l'argent d'une affiliation, et c'est la même serrure que l'écran
    // Affiliations, d'où ce parcours est copié.

    /** Déplace une place validée vers un autre pack de la même saison. */
    public function confirmMoveMember(): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $pack = $this->selectedPack;
        $subscription = $this->rosterSubscription($this->moveMemberUserId);
        $target = $this->moveTargetPackId === 0 ? null : TrainingPack::find($this->moveTargetPackId);

        if (! $pack || ! $subscription || ! $target) {
            return;
        }

        try {
            $refundable = (new MoveMemberBetweenTrainingPacksAction)(
                $subscription,
                $pack,
                $target,
                $subscription->has_other_family_members ? 2 : 1,
            );
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return;
        }

        $userName = $subscription->user->first_name . ' ' . $subscription->user->last_name;

        $this->warnWhenUnreachable($subscription->user);

        $this->moveMemberModal = false;
        $this->moveMemberUserId = 0;
        $this->moveTargetPackId = 0;
        $this->forgetRoster();

        if ($refundable <= 0.0) {
            $this->success(__(':user moved to :pack.', [
                'user' => $userName,
                'pack' => $target->name,
            ]), icon: 'o-arrows-right-left');

            return;
        }

        (new RequestSubscriptionRefundAction)($subscription, $refundable, __(':member has been moved to :pack, which costs less.', [
            'member' => $userName,
            'pack' => $target->name,
        ]));

        $iban = $subscription->user->iban;

        if ($iban) {
            $this->success(__(':user moved to :pack. Refund of :amount€ to be issued to :iban.', [
                'user' => $userName,
                'pack' => $target->name,
                'amount' => number_format($refundable, 2),
                'iban' => $iban,
            ]));

            return;
        }

        $this->warning(__(':user moved to :pack. Refund of :amount€ required — no IBAN on file, please handle manually.', [
            'user' => $userName,
            'pack' => $target->name,
            'amount' => number_format($refundable, 2),
        ]));
    }

    public function confirmRemoveFromRoster(): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $pack = $this->selectedPack;
        $subscription = $this->rosterSubscription($this->removeFromRosterUserId);

        if (! $pack || ! $subscription) {
            return;
        }

        $pivot = $subscription->trainingPacks()->where('training_pack_id', $pack->id)->first();

        if ($pivot === null || $pivot->pivot->status === 'enrolled') {
            return;
        }

        $campLine = (new TrainingCampBilling)->line($subscription, $pack);

        if ($pivot->pivot->status === 'pending' && $campLine?->invoiced_separately) {
            (new DecideTrainingCampRequestAction)->reject($campLine);

            $this->removeFromRosterModal = false;
            $this->removeFromRosterUserId = 0;
            $this->forgetRoster();
            $this->success(__('Request of :member refused.', ['member' => $subscription->user->full_name]));

            return;
        }

        (new LeaveTrainingPackAction)(
            $subscription,
            $pack,
            $subscription->has_other_family_members ? 2 : 1,
        );

        $this->removeFromRosterModal = false;
        $this->removeFromRosterUserId = 0;
        $this->removeFromRosterName = '';
        $this->removeFromRosterStatus = '';
        $this->forgetRoster();

        $this->success(__(':member removed from :pack.', [
            'member' => $subscription->user->first_name . ' ' . $subscription->user->last_name,
            'pack' => $pack->name,
        ]));
    }

    /**
     * Les packs vers lesquels ce membre peut être déplacé.
     *
     * On ne propose pas les packs retirés de l'offre : y déplacer quelqu'un
     * l'inscrirait à un entraînement que le club a cessé de proposer. Les packs
     * complets ou aux inscriptions closes, eux, restent proposés et marqués —
     * même politique que l'ajout manuel, où le comité franchit le plafond en
     * connaissance de cause.
     *
     * @return list<array{id: int, name: string}>
     */
    #[Computed]
    public function moveTargetOptions(): array
    {
        $pack = $this->selectedPack;

        if (! $pack || $this->moveMemberUserId === 0) {
            return [];
        }

        $subscription = $this->rosterSubscription($this->moveMemberUserId);

        if (! $subscription) {
            return [];
        }

        $held = DB::table('subscription_training_pack')
            ->where('subscription_id', $subscription->id)
            ->whereIn('status', ['enrolled', 'pending', 'offered'])
            ->pluck('training_pack_id');

        $options = TrainingPack::query()
            ->where('season_id', $pack->season_id)
            ->where('is_active', true)
            ->whereKeyNot($pack->id)
            ->whereNotIn('id', $held)
            // `room` autant que `level` : sans plafond propre,
            // effectiveMaxParticipants() retombe sur la capacité de la salle, et
            // hasAvailableSpot() — qui compose le libellé de chaque option — va
            // la chercher. Le chargement paresseux est interdit hors production,
            // donc l'oubli ne dégrade pas la page : il la casse.
            ->with(['level', 'room'])
            ->get()
            ->map(fn (TrainingPack $candidate): array => [
                'id' => $candidate->id,
                'packName' => $candidate->name,
                'name' => $candidate->name
                    . ' · ' . ($candidate->level?->label ?? '—')
                    . ' · ' . number_format((float) $candidate->price, 2, ',', ' ') . ' €'
                    . ($candidate->hasAvailableSpot() ? '' : ' · ' . __('Full')),
            ]);

        return LocaleSort::byKey($options, 'packName')->values()->all();
    }

    public function openCampPrice(int $userId): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $line = $this->campLineFor($userId);

        if ($line === null) {
            return;
        }

        $this->campPriceUserId = $userId;
        $this->campPriceAmount = $line->override_amount !== null ? number_format(((int) $line->override_amount) / 100, 2, '.', '') : '';
        $this->campPriceReason = (string) $line->override_reason;
        $this->campPriceModal = true;
    }

    /**
     * Ouvre la sortie d'une place validée : départ daté ou erreur d'encodage.
     *
     * La modale est partagée avec l'écran Affiliations, d'où le passage par un
     * événement plutôt que par un état de cet écran.
     */
    public function openLeaveMember(int $userId): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $subscription = $this->rosterSubscription($userId);

        if (! $subscription || ! $this->selectedPack) {
            return;
        }

        $this->dispatch('open-training-pack-exit', subscriptionId: $subscription->id, packId: $this->selectedPack->id);
    }

    /** Ouvre le choix du pack de destination. */
    public function openMoveMember(int $userId): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $this->moveMemberUserId = $userId;
        $this->moveMemberName = (string) $this->rosterSubscription($userId)?->user->full_name;
        $this->moveTargetPackId = 0;
        $this->moveMemberModal = true;
        unset($this->moveTargetOptions);
    }

    /**
     * Écarte une demande, ou retire quelqu'un de la file d'attente.
     *
     * Sans confirmation, et c'est voulu : {@see LeaveTrainingPackAction} détache
     * purement et simplement tout ce qui n'est pas `enrolled` — aucune date de
     * sortie, aucun euro, aucune trace. Une place validée, elle, passe par la
     * modale, parce qu'elle peut rendre de l'argent.
     */
    /** Ouvre la confirmation de retrait d'une demande ou d'une place en file. */
    public function openRemoveFromRoster(int $userId): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $pack = $this->selectedPack;
        $subscription = $this->rosterSubscription($userId);

        if (! $pack || ! $subscription) {
            return;
        }

        $pivot = $subscription->trainingPacks()->where('training_pack_id', $pack->id)->first();

        if ($pivot === null || $pivot->pivot->status === 'enrolled') {
            return;
        }

        $this->removeFromRosterUserId = $userId;
        $this->removeFromRosterName = (string) $subscription->user->full_name;
        $this->removeFromRosterStatus = (string) $pivot->pivot->status;
        $this->removeFromRosterModal = true;
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
     * Qui est dans le pack, et à quel titre.
     *
     * Quatre listes plutôt qu'une colonne « statut » : le comité ne se pose pas
     * la même question devant un inscrit, une demande à valider, une file
     * d'attente et quelqu'un qui est parti. Les mélanger obligeait à trier des
     * yeux la seule ligne qu'on cherchait.
     *
     * Les inscriptions non affiliées sont écartées comme ailleurs
     * ({@see TrainingPack::committedCount()}) : une affiliation annulée gardait
     * son nom dans la liste d'un pack qu'elle ne suit plus.
     *
     * @return array{enrolled: list<array<string, mixed>>, pending: list<array<string, mixed>>, waiting: list<array<string, mixed>>, past: list<array<string, mixed>>}
     */
    #[Computed]
    public function packRoster(): array
    {
        $pack = $this->selectedPack;

        if (! $pack) {
            return ['enrolled' => [], 'pending' => [], 'waiting' => [], 'past' => []];
        }

        $attendance = $this->packAttendance;
        $prorata = new TrainingPackProrata;

        // What each stage line still asks, read in one query: a stage is paid on
        // its own line, and the committee needs to see who has settled.
        $campBalances = $pack->is_camp
            ? Payment::query()
                ->where('payable_type', SubscriptionTrainingPack::class)
                ->whereIn('payable_id', DB::table('subscription_training_pack')->where('training_pack_id', $pack->id)->select('id'))
                ->where('status', 'pending')
                ->where(fn ($q) => $q->where('payment_method', '!=', 'refund')->orWhereNull('payment_method'))
                ->get()
                ->groupBy('payable_id')
                ->map(fn ($claims): float => round((float) $claims->sum(fn (Payment $claim): float => $claim->balance()), 2))
            : collect();

        $rows = $pack->subscriptions()
            ->withPivot([
                'id',
                'invoiced_separately',
                'status',
                'waitlist_position',
                'confirmation_deadline',
                'starts_on',
                'ends_on',
                'override_amount',
                'override_reason',
            ])
            ->affiliated()
            ->with('user')
            ->get()
            ->map(function (Subscription $subscription) use ($attendance, $pack, $prorata, $campBalances): array {
                $user = $subscription->user;
                $pivot = $subscription->pivot;

                return [
                    'id' => $user->id,
                    'name' => $user->last_name . ' ' . $user->first_name,
                    'ranking' => $user->ranking?->getLabel(),
                    'status' => $pivot->status,
                    'position' => $pivot->waitlist_position,
                    'deadline' => $pivot->confirmation_deadline,
                    // Datée du début du pack ou sans date, c'est la même
                    // inscription : « depuis le début » dans les deux cas.
                    'startsOn' => $prorata->coversWholePack($pack, $pivot->starts_on) ? null : $pivot->starts_on,
                    'endsOn' => $pivot->ends_on,
                    'overrideAmount' => $pivot->override_amount !== null ? (int) $pivot->override_amount / 100 : null,
                    'overrideReason' => $pivot->override_reason,
                    'unpaid' => $subscription->status === 'pending',
                    'isCampLine' => (bool) $pivot->invoiced_separately,
                    'campBalance' => (bool) $pivot->invoiced_separately ? (float) ($campBalances[$pivot->id] ?? 0.0) : null,
                    'rate' => $attendance['counted'] === 0
                        ? null
                        : (int) round((($attendance['present'][$user->id] ?? 0) / $attendance['counted']) * 100),
                ];
            });

        // Collection de base, pas celle d'Eloquent : `map()` sur des tableaux la
        // dégrade, et le typage ci-dessous est ce qui l'a révélé.
        $byName = fn (Collection $group): array => LocaleSort::byKey($group, 'name')->all();

        $waiting = $rows->whereIn('status', ['waiting', 'offered'])
            ->sortBy([
                // Une offre en cours passe devant : elle a une échéance, la file non.
                fn (array $a, array $b): int => ($b['status'] === 'offered' ? 1 : 0) <=> ($a['status'] === 'offered' ? 1 : 0),
                fn (array $a, array $b): int => ($a['position'] ?? PHP_INT_MAX) <=> ($b['position'] ?? PHP_INT_MAX),
            ])
            ->values()
            ->all();

        return [
            'enrolled' => $byName($rows->where('status', 'enrolled')),
            'pending' => $byName($rows->where('status', 'pending')),
            'waiting' => $waiting,
            'past' => $byName($rows->whereIn('status', ['left', 'cancelled'])),
        ];
    }

    /** La modale partagée a sorti quelqu'un : la liste n'est plus la même. */
    #[On('training-pack-exited')]
    #[On('external-registrations-changed')]
    #[On('training-pack-refresh')]
    public function refreshAfterExit(): void
    {
        $this->forgetRoster();
    }

    /**
     * Fixe le prix d'un membre sur le stage : la facture du stage suit, et ce
     * qui est déjà payé au-delà part en remboursement.
     */
    public function saveCampPrice(): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $line = $this->campLineFor($this->campPriceUserId);

        if ($line === null) {
            return;
        }

        $amount = trim($this->campPriceAmount) === '' ? null : (float) str_replace(',', '.', $this->campPriceAmount);

        try {
            $refunded = (new AdjustTrainingCampLineAction)($line->subscription, $line->trainingPack, $amount, $this->campPriceReason);
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->campPriceModal = false;
        $this->forgetRoster();

        $refunded > 0
            ? $this->warning(__('Price updated. :amount € to refund, sent to the treasury.', ['amount' => number_format($refunded, 2, ',', ' ')]))
            : $this->success(__('Price updated.'));
    }

    /** La ligne de stage de ce membre, sur le stage consulté. */
    private function campLineFor(int $userId): ?SubscriptionTrainingPack
    {
        $pack = $this->selectedPack;
        $subscription = $this->rosterSubscription($userId);

        if (! $pack?->is_camp || ! $subscription) {
            return null;
        }

        $line = (new TrainingCampBilling)->line($subscription, $pack);

        return $line?->invoiced_separately ? $line : null;
    }

    /**
     * Le roster et tout ce qui en dérive, à relire après une écriture. La
     * fiche, elle, recompte ses chiffres de tête.
     */
    private function forgetRoster(): void
    {
        unset(
            $this->selectedPack,
            $this->moveTargetOptions,
            $this->packRoster,
            $this->packAttendance,
        );

        $this->dispatch('training-pack-changed');
    }
};
