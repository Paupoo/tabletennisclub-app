<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Subscriptions\Services;

use App\Actions\ClubAdmin\Subscriptions\CalculatePriceAction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Qui appartient à la même famille qu'un membre, et ce que ça vaut.
 *
 * La famille n'est pas déclarative — `subscriptions.has_other_family_members`
 * n'a jamais été renseigné nulle part. Elle se lit sur le lien tuteur, le seul
 * fait que le club enregistre vraiment : deux membres sont de la même famille
 * quand ils partagent un tuteur, ou quand l'un est le tuteur de l'autre (la
 * mère inscrite avec ses enfants a un `Guardian` portant son `user_id`).
 */
final class FamilyDiscount
{
    /**
     * Affiliations de la saison des *autres* membres de la famille.
     *
     * @return Collection<int, Subscription>
     */
    public function affiliatedRelatives(User $member, Season $season): Collection
    {
        $relativeIds = $this->relativeIds($member);

        if ($relativeIds === []) {
            return collect();
        }

        return Subscription::query()
            ->whereIn('user_id', $relativeIds)
            ->where('season_id', $season->id)
            ->affiliated()
            ->get();
    }

    /**
     * Manque à gagner de remise que la famille a accumulé, à absorber par
     * l'affiliation en cours.
     *
     * Les factures déjà émises ne sont jamais rouvertes : pour chaque membre
     * déjà affilié, on chiffre l'écart entre ce qu'il doit aujourd'hui et ce
     * qu'il devrait si la remise famille lui avait été appliquée. Un membre qui
     * tenait déjà deux packs remisables touchait déjà la remise : son terme
     * vaut naturellement zéro, et « 10 € × membres » serait faux.
     *
     * Le total est borné à zéro : un membre parti en cours de saison laisse
     * derrière lui des termes négatifs qui feraient payer le nouveau *plus*
     * que son propre devis.
     */
    public function catchUpCredit(User $member, Season $season, int $familyMembersCount): float
    {
        $calculatePrice = new CalculatePriceAction;

        $credit = $this->affiliatedRelatives($member, $season)->sum(
            fn (Subscription $subscription): float => (float) $subscription->amount_due
                - $calculatePrice->quote($subscription, $familyMembersCount)['total']
        );

        return round(max(0.0, (float) $credit), 2);
    }

    /**
     * Taille de la famille affiliée cette saison, le membre compris.
     *
     * Le membre compte qu'il ait déjà une inscription ou non : la question se
     * pose à l'identique avant et après la création de son affiliation.
     */
    public function membersCount(User $member, Season $season): int
    {
        return $this->affiliatedRelatives($member, $season)->count() + 1;
    }

    /**
     * Identifiants des autres membres de la famille, dans les deux sens du lien.
     *
     * @return array<int, int>
     */
    private function relativeIds(User $member): array
    {
        $guardianIds = DB::table('guardian_user')
            ->where('user_id', $member->id)
            ->pluck('guardian_id')
            ->merge(Guardian::where('user_id', $member->id)->pluck('id'))
            ->unique();

        if ($guardianIds->isEmpty()) {
            return [];
        }

        return DB::table('guardian_user')
            ->whereIn('guardian_id', $guardianIds)
            ->pluck('user_id')
            ->merge(
                Guardian::whereIn('id', $guardianIds)->whereNotNull('user_id')->pluck('user_id')
            )
            ->map(fn (int|string $id): int => (int) $id)
            ->reject(fn (int $id): bool => $id === $member->id)
            ->unique()
            ->values()
            ->all();
    }
}
