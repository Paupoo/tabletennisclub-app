<?php

declare(strict_types=1);

namespace App\Domains\Trainings\Services;

use App\Domains\Trainings\Models\TrainingPack;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Pro rata d'un pack d'entraînement, en mois calendaires entamés.
 *
 * Base de calcul : un mois entamé compte pour un mois entier. On ne compte
 * pas les séances — un pack peut sauter des semaines, être déplacé, avoir des
 * dates exclues : la facturation ne peut pas dépendre de ça.
 *
 * Dénominateur : la durée du pack (`pack_start_date` → `pack_end_date`), et
 * non celle de la saison. Un pack de 4 mois facturé plein tarif reste plein
 * tarif ; un membre qui le rejoint à mi-parcours paie la moitié de CE pack.
 */
class TrainingPackProrata
{
    /**
     * Montant à facturer pour la période couverte, arrondi au cent.
     *
     * `$netPrice` est le prix déjà remisé : la remise multi-packs s'applique
     * avant le pro rata, sur le prix affiché du pack, pas sur la fraction.
     */
    public function billableAmount(TrainingPack $pack, float $netPrice, CarbonInterface|string|null $startsOn, CarbonInterface|string|null $endsOn): float
    {
        return round($netPrice * $this->ratio($pack, $startsOn, $endsOn), 2);
    }

    /**
     * Date d'entrée à poser sur une nouvelle ligne de pivot.
     *
     * Nulle tant que le pack n'a pas commencé : c'est le cas nominal d'une
     * inscription de début de saison, et une ligne sans dates vaut « tout le
     * pack ». Dès que le pack a démarré, l'arrivée est datée et déclenche le
     * pro rata.
     */
    public function enrolmentStart(TrainingPack $pack, CarbonInterface|string|null $on = null): ?string
    {
        $day = $this->toDate($on) ?? CarbonImmutable::today();
        $packStart = $this->toDate($pack->pack_start_date);

        if ($packStart === null || $day->lte($packStart)) {
            return null;
        }

        return $day->toDateString();
    }

    /**
     * Part du pack réellement due, entre 0 et 1.
     *
     * Une ligne sans dates couvre tout le pack : elle vaut 1, quelles que
     * soient les dates du pack. C'est ce qui garantit qu'aucune inscription
     * existante ne change de prix.
     *
     * Un pack sans `pack_start_date`/`pack_end_date` n'a aucun calendrier de
     * facturation : il n'y a rien à proratiser. On le facture alors en plein
     * tant que le membre le détient, et plus du tout dès qu'il l'a quitté —
     * exactement ce que faisait le `detach()` d'avant. Un pack qui veut le pro
     * rata déclare ses dates ; à défaut, le trésorier force le montant.
     */
    public function ratio(TrainingPack $pack, CarbonInterface|string|null $startsOn, CarbonInterface|string|null $endsOn): float
    {
        $from = $this->toDate($startsOn);
        $to = $this->toDate($endsOn);

        if ($from === null && $to === null) {
            return 1.0;
        }

        $packStart = $this->toDate($pack->pack_start_date);
        $packEnd = $this->toDate($pack->pack_end_date);

        if ($packStart === null || $packEnd === null) {
            return $to === null ? 1.0 : 0.0;
        }

        $total = $this->monthSpan($packStart, $packEnd);

        if ($total <= 0) {
            return 1.0;
        }

        $effectiveStart = $from !== null && $from->gt($packStart) ? $from : $packStart;
        $effectiveEnd = $to !== null && $to->lt($packEnd) ? $to : $packEnd;

        if ($effectiveEnd->lt($effectiveStart)) {
            return 0.0;
        }

        return min($this->monthSpan($effectiveStart, $effectiveEnd), $total) / $total;
    }

    /**
     * Nombre de mois calendaires entamés entre deux dates, bornes comprises.
     *
     * Du 12 janvier au 3 avril : janvier, février, mars, avril → 4.
     */
    private function monthSpan(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return ($to->year * 12 + $to->month) - ($from->year * 12 + $from->month) + 1;
    }

    private function toDate(CarbonInterface|string|null $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse($value)->startOfDay();
    }
}
