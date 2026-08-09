<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Subscriptions;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Services\TrainingPackProrata;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final readonly class CalculatePriceAction
{
    private const float COMPETITIVE_PRICE = 125.0;

    private const float DISCOUNT_AMOUNT = 10.0; // €

    private const float RECREATIVE_PRICE = 60.0;

    public function __construct(private TrainingPackProrata $prorata = new TrainingPackProrata) {}

    public function __invoke(Subscription $subscription, int $familyMembersCount = 1): Subscription
    {
        $quote = $this->quote($subscription, $familyMembersCount);

        $subscription->subscription_price = $quote['subscription_price'];
        $subscription->trainings_count = $quote['enrolled_count'];
        $subscription->training_unit_price = $quote['enrolled_count'] === 0
            ? 0.0
            : round($quote['enrolled_total'] / $quote['enrolled_count'], 2);

        // amount_due accessor expects euros, stores as cents internally.
        //
        // Le crédit famille est retranché ici, à chaque recalcul : c'est la
        // remise que les affiliations précédentes de la famille n'ont jamais
        // reçue et que celle-ci absorbe. Le retrancher une seule fois, au
        // moment de l'affiliation, l'aurait fait disparaître au premier départ
        // de pack ou à la première réconciliation.
        $subscription->amount_due = max(0.0, round($quote['total'] - $subscription->family_credit, 2));

        $subscription->save();

        return $subscription;
    }

    /**
     * Entraînements réellement consommés si le membre quittait tout aujourd'hui.
     *
     * Sert à proposer un remboursement au trésorier quand une cotisation entière
     * est annulée : sans ça il rendrait aussi les mois déjà suivis. C'est une
     * proposition, pas une décision — le montant reste éditable.
     */
    public function consumedTrainingTotal(Subscription $subscription, int $familyMembersCount = 1): float
    {
        $today = Carbon::today()->toDateString();

        // Plus aucun pack détenu après le départ : seule la remise famille peut
        // encore jouer, exactement comme le ferait un départ pack par pack.
        $applyDiscount = $familyMembersCount > 1;

        $total = 0.0;

        foreach ($subscription->trainingPacks()->wherePivotIn('status', ['enrolled', 'left'])->get() as $pack) {
            $pivot = $pack->pivot;

            if ($pivot->override_amount !== null) {
                $total += round(((int) $pivot->override_amount) / 100, 2);

                continue;
            }

            $netPrice = $applyDiscount && $pack->allow_discount
                ? max(0.0, (float) $pack->price - self::DISCOUNT_AMOUNT)
                : (float) $pack->price;

            $total += $this->prorata->billableAmount(
                $pack,
                $netPrice,
                $pivot->starts_on,
                $pivot->status === 'left' ? $pivot->ends_on : $today,
            );
        }

        return round($total, 2);
    }

    /**
     * Détail chiffré de la cotisation, sans rien enregistrer.
     *
     * Les lignes `left` restent facturées : le membre a consommé des mois, il
     * les doit. Elles ne comptent en revanche pas dans les effectifs
     * (`trainings_count`) ni dans l'éligibilité à la remise — un pack qu'on a
     * quitté n'est plus détenu, et c'est précisément ce qui fait qu'un départ
     * peut renchérir les packs restants.
     *
     * @return array{
     *   subscription_price: float,
     *   lines: array<int, array{name: string, status: string, amount: float, overridden: bool, ratio: float}>,
     *   enrolled_total: float,
     *   enrolled_count: int,
     *   training_total: float,
     *   total: float,
     * }
     */
    public function quote(Subscription $subscription, int $familyMembersCount = 1): array
    {
        /** @var Collection<int, TrainingPack> $billablePacks */
        $billablePacks = $subscription->trainingPacks()
            ->wherePivotIn('status', ['enrolled', 'left'])
            ->get();

        return $this->quoteFor((bool) $subscription->is_competitive, $billablePacks, $familyMembersCount);
    }

    /**
     * Même devis, pour une affiliation qui n'existe pas encore.
     *
     * Le guichet doit annoncer un prix avant d'enregistrer quoi que ce soit, et
     * ce prix ne peut pas être recalculé ailleurs sous peine de diverger de la
     * facture. Un pack sans ligne de pivot est une ligne encore à créer : elle
     * vaudra ce que l'inscription y posera {@see self::line()}.
     *
     * @param  Collection<int, TrainingPack>  $billablePacks
     * @return array{
     *   subscription_price: float,
     *   lines: array<int, array{name: string, status: string, amount: float, overridden: bool, ratio: float}>,
     *   enrolled_total: float,
     *   enrolled_count: int,
     *   training_total: float,
     *   total: float,
     * }
     */
    public function quoteFor(bool $isCompetitive, Collection $billablePacks, int $familyMembersCount = 1): array
    {
        $subscriptionPrice = $isCompetitive
            ? self::COMPETITIVE_PRICE
            : self::RECREATIVE_PRICE;

        $enrolledPacks = $billablePacks->filter(fn (TrainingPack $p): bool => $this->status($p) === 'enrolled');

        $applyDiscount = $enrolledPacks->filter(fn (TrainingPack $p): bool => $p->allow_discount)->count() > 1
            || $familyMembersCount > 1;

        $lines = [];
        $enrolledTotal = 0.0;
        $trainingTotal = 0.0;

        foreach ($billablePacks as $pack) {
            $line = $this->line($pack, $applyDiscount);

            $lines[$pack->id] = $line;
            $trainingTotal += $line['amount'];

            if ($line['status'] === 'enrolled') {
                $enrolledTotal += $line['amount'];
            }
        }

        $trainingTotal = round($trainingTotal, 2);

        return [
            'subscription_price' => $subscriptionPrice,
            'lines' => $lines,
            'enrolled_total' => round($enrolledTotal, 2),
            'enrolled_count' => $enrolledPacks->count(),
            'training_total' => $trainingTotal,
            'total' => round($subscriptionPrice + $trainingTotal, 2),
        ];
    }

    /**
     * @return array{name: string, status: string, amount: float, overridden: bool, ratio: float}
     */
    private function line(TrainingPack $pack, bool $applyDiscount): array
    {
        $pivot = $pack->pivot;

        // Sans pivot, la ligne reste à créer : elle démarrera là où
        // EnrollInTrainingPackAction la posera, et rien ne l'a encore forcée.
        $startsOn = $pivot !== null ? $pivot->starts_on : $this->prorata->enrolmentStart($pack);
        $ratio = $this->prorata->ratio($pack, $startsOn, $pivot?->ends_on);

        // Un montant forcé court-circuite tout : c'est le dernier mot du
        // trésorier sur une ligne que le calcul n'arrive pas à décrire.
        if ($pivot?->override_amount !== null) {
            return [
                'name' => $pack->name,
                'status' => $this->status($pack),
                'amount' => round(((int) $pivot->override_amount) / 100, 2),
                'overridden' => true,
                'ratio' => $ratio,
            ];
        }

        // Remise d'abord, pro rata ensuite : le membre arrivé en cours de route
        // paie une fraction du prix qu'il aurait payé s'il avait été là depuis
        // le début, remise comprise.
        $netPrice = $applyDiscount && $pack->allow_discount
            ? max(0.0, (float) $pack->price - self::DISCOUNT_AMOUNT)
            : (float) $pack->price;

        return [
            'name' => $pack->name,
            'status' => $this->status($pack),
            'amount' => round($netPrice * $ratio, 2),
            'overridden' => false,
            'ratio' => $ratio,
        ];
    }

    /**
     * État de la ligne de pivot, `enrolled` pour celle qui reste à créer.
     */
    private function status(TrainingPack $pack): string
    {
        return (string) ($pack->pivot?->status ?? 'enrolled');
    }
}
