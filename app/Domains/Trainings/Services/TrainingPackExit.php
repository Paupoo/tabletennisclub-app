<?php

declare(strict_types=1);

namespace App\Domains\Trainings\Services;

use App\Actions\ClubAdmin\Subscriptions\CalculatePriceAction;
use App\Actions\ClubAdmin\Subscriptions\CancelTrainingPackEnrolmentAction;
use App\Actions\ClubAdmin\Subscriptions\LeaveTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\ReduceOutstandingInvoiceAction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Trainings\Models\TrainingPack;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Ce que deux sorties d'un pack partagent : un départ daté
 * ({@see LeaveTrainingPackAction}) et une annulation pour erreur d'encodage
 * ({@see CancelTrainingPackEnrolmentAction}).
 *
 * Les deux touchent au pointage. La validation d'une séance écrit `absent`
 * tout inscrit que le coach n'a pas touché : un joueur inscrit par erreur, ou
 * resté inscrit après son départ, accumule des absences qui ne disent rien.
 * Un autre statut — présent, excusé — a été posé par le coach : c'est un fait,
 * et il borne ce qu'une sortie a le droit de réécrire.
 */
class TrainingPackExit
{
    public function __construct(private readonly TrainingPackProrata $prorata = new TrainingPackProrata) {}

    /**
     * Absences pointées sur les séances du pack, après `$after` s'il est donné.
     */
    public function absencesCount(Subscription $subscription, TrainingPack $pack, CarbonInterface|string|null $after = null): int
    {
        return $this->absences($subscription, $pack, $after)->count();
    }

    /**
     * Date de départ la plus précoce : l'entrée dans le pack, ou la dernière
     * séance où le membre a été pointé, la plus tardive des deux.
     *
     * Jamais après aujourd'hui : un membre qui renonce à un pack pas encore
     * commencé part le jour même, et le pro rata ne lui facture rien.
     */
    public function earliestDeparture(Subscription $subscription, TrainingPack $pack): CarbonImmutable
    {
        $startsOn = DB::table('subscription_training_pack')
            ->where('subscription_id', $subscription->id)
            ->where('training_pack_id', $pack->id)
            ->value('starts_on');

        $arrival = CarbonImmutable::parse($startsOn ?? $pack->pack_start_date)->startOfDay();
        $lastMarked = $this->lastMarkedSession($subscription, $pack);
        $earliest = $lastMarked !== null && $lastMarked->gt($arrival) ? $lastMarked : $arrival;

        return $earliest->min(CarbonImmutable::today());
    }

    /**
     * Efface ces absences. Les autres statuts ne sont jamais touchés.
     */
    public function eraseAbsences(Subscription $subscription, TrainingPack $pack, CarbonInterface|string|null $after = null): int
    {
        $sessionIds = $this->absences($subscription, $pack, $after)->pluck('training_user.training_id');

        return DB::table('training_user')
            ->where('user_id', $subscription->user_id)
            ->whereIn('training_id', $sessionIds)
            ->delete();
    }

    public function lastMarkedSession(Subscription $subscription, TrainingPack $pack): ?CarbonImmutable
    {
        $start = $this->marks($subscription, $pack)
            ->where('training_user.status', '!=', 'absent')
            ->max('trainings.start');

        return $start !== null ? CarbonImmutable::parse($start)->startOfDay() : null;
    }

    /**
     * Séances du pack où le coach a pointé le membre autrement qu'absent.
     */
    public function markedSessionsCount(Subscription $subscription, TrainingPack $pack): int
    {
        return $this->marks($subscription, $pack)->where('training_user.status', '!=', 'absent')->count();
    }

    /**
     * Ce que la sortie produirait, sans rien écrire.
     *
     * `$endsOn` date un départ ; `null` annonce une annulation pour erreur. Les
     * chiffres sortent des mêmes calculs que les actions — le devis de
     * {@see CalculatePriceAction} et la projection de
     * {@see ReduceOutstandingInvoiceAction} — pour qu'un aperçu ne puisse pas
     * promettre autre chose que ce que le clic fera.
     *
     * @return array{line_amount: float, amount_due: float, reduced: float, refund: float, absences: int}
     */
    public function preview(Subscription $subscription, TrainingPack $pack, ?string $endsOn, int $familyMembersCount = 1): array
    {
        $packs = $subscription->trainingPacks()->wherePivotIn('status', ['enrolled', 'left'])->get();

        if ($endsOn === null) {
            $packs = $packs->reject(fn (TrainingPack $held): bool => $held->id === $pack->id)->values();
        } else {
            $packs->firstWhere('id', $pack->id)?->pivot->forceFill(['status' => 'left', 'ends_on' => $endsOn]);
        }

        $quote = new CalculatePriceAction($this->prorata)->quoteFor((bool) $subscription->is_competitive, $packs, $familyMembersCount);

        $amountDue = max(0.0, round($quote['total'] - $subscription->family_credit - $subscription->discountTotal(), 2));

        $projection = (new ReduceOutstandingInvoiceAction)->project($subscription, $amountDue);

        return [
            'line_amount' => $quote['lines'][$pack->id]['amount'] ?? 0.0,
            'amount_due' => $amountDue,
            'reduced' => $projection['reduced'],
            'refund' => round(min($projection['overpaid'], $subscription->netAmountPaid()), 2),
            'absences' => $this->absencesCount($subscription, $pack, $endsOn),
        ];
    }

    private function absences(Subscription $subscription, TrainingPack $pack, CarbonInterface|string|null $after): Builder
    {
        return $this->marks($subscription, $pack)
            ->where('training_user.status', 'absent')
            ->when($after !== null, fn (Builder $query) => $query->where(
                'trainings.start',
                '>',
                CarbonImmutable::parse($after)->endOfDay()->toDateTimeString(),
            ));
    }

    /**
     * Les lignes de pointage du membre sur les séances de ce pack.
     */
    private function marks(Subscription $subscription, TrainingPack $pack): Builder
    {
        return DB::table('training_user')
            ->join('trainings', 'trainings.id', '=', 'training_user.training_id')
            ->where('trainings.training_pack_id', $pack->id)
            ->where('training_user.user_id', $subscription->user_id);
    }
}
