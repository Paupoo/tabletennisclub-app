<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Services;

use App\Data\Interclub\InterclubPool;
use App\Data\Interclub\PoolCandidate;
use App\Data\Interclub\WaitingTeam;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\InterclubAvailability;
use App\Livewire\Concerns\ComposesInterclubLineup;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Les joueurs libres d'une journée d'interclubs.
 *
 * Un capitaine à qui il manque un joueur n'avait, jusqu'ici, qu'une recherche par
 * nom réservée aux sélectionneurs du club. Pourtant l'information existait déjà
 * en base : d'autres capitaines de la même catégorie ont sondé leur effectif, et
 * ce qu'ils n'ont pas retenu dort dans `interclub_user`.
 *
 * Trois partis pris portent tout le fichier.
 *
 * **Le pool est une requête, pas une table.** Le point « retirer un joueur du
 * pool dès qu'il est engagé ailleurs » n'est donc pas un mécanisme à écrire et à
 * maintenir : c'est le prédicat lui-même, et il ne peut pas se désynchroniser.
 *
 * **Même semaine, même catégorie.** C'est déjà la règle du double alignement
 * (C.20.1, voir {@see ComposesInterclubLineup}), et c'est
 * la seule qui garde son sens : une dame disponible le vendredi n'a rien dit du
 * samedi des messieurs.
 *
 * **Rien ne sort avant que le capitaine ait confirmé.** Un joueur non retenu
 * dont la composition n'est pas publiée n'est pas libre, il est *en attente* ;
 * le proposer ailleurs reviendrait à le voler à un capitaine qui comptait encore
 * sur lui, et surtout à lui apprendre sa non-sélection par la bande.
 */
class InterclubPoolService
{
    /**
     * Les joueurs libres pour cette rencontre : ceux qui ont répondu oui.
     *
     * @return Collection<int, PoolCandidate>
     */
    public function freePlayersFor(Interclub $fixture): Collection
    {
        return $this->poolFor($fixture)->freePlayers;
    }

    /**
     * Ceux qui ont répondu « peut-être ».
     *
     * Volontairement tenus hors du pool : à J-2 sans personne, un peut-être reste
     * une piste, mais le mélanger aux oui reviendrait à vendre un accord qui n'en
     * est pas un — et ce peut-être répondait de surcroît à une autre rencontre, à
     * une autre heure.
     *
     * @return Collection<int, PoolCandidate>
     */
    public function maybePlayersFor(Interclub $fixture): Collection
    {
        return $this->poolFor($fixture)->maybePlayers;
    }

    /**
     * Tout le pool d'un coup, en un seul balayage des rencontres de la journée.
     *
     * C'est l'entrée que l'écran de composition appelle. Les trois lectures
     * publiques restent disponibles et se suffisent à elles-mêmes, mais chacune
     * refait la requête : appelées toutes les trois à chaque case cochée, elles
     * la referaient trois fois.
     *
     * Pas de mémoïsation sur l'instance, délibérément. Le service serait alors
     * capable de répondre sur un état que le tiroir vient de changer — un cache
     * qui ment est pire qu'une requête de plus.
     */
    public function poolFor(Interclub $fixture, ?EloquentCollection $week = null): InterclubPool
    {
        // L'appelant qui a déjà balayé la journée passe ses rencontres : l'écran
        // de composition en a besoin pour une autre raison au même instant, et
        // les charger deux fois coûte tout un jeu de relations.
        $week ??= $this->sameWeekFixtures($fixture);

        $siblings = $week
            ->reject(fn (Interclub $other): bool => $other->id === $fixture->id)
            ->values();

        $engagedIds = $this->engagedIds($week);

        return new InterclubPool(
            freePlayers: $this->pick($siblings, InterclubAvailability::AVAILABLE, $engagedIds),
            maybePlayers: $this->pick($siblings, InterclubAvailability::MAYBE, $engagedIds),
            waitingTeams: $this->withCaptains($this->waiting($siblings)),
        );
    }

    /**
     * Les équipes sœurs qui n'ont pas publié, et ce qu'elles retiennent.
     *
     * @return Collection<int, WaitingTeam>
     */
    public function waitingTeamsFor(Interclub $fixture): Collection
    {
        return $this->poolFor($fixture)->waitingTeams;
    }

    /**
     * Qui est déjà aligné cette journée dans cette catégorie, la rencontre cible
     * comprise — on ne propose pas un joueur qu'on vient soi-même de cocher.
     *
     * @param  EloquentCollection<int, Interclub>  $fixtures
     * @return array<int, int>
     */
    private function engagedIds(EloquentCollection $fixtures): array
    {
        return $fixtures
            ->flatMap(fn (Interclub $other): array => $other->users
                ->filter(fn (User $player): bool => (bool) $player->registration?->is_selected)
                ->pluck('id')
                ->all())
            ->unique()
            ->values()
            ->all();
    }

    /**
     * La composition de cette rencontre a-t-elle été publiée ?
     *
     * Même question que {@see Interclub::isLineupPublished()}, lue sur les lignes
     * de pivot déjà chargées plutôt qu'en interrogeant la base. La méthode du
     * modèle fait un `exists()` par rencontre : appelée dans la boucle, elle
     * rendait en N+1 le balayage qu'on venait de faire tenir en une requête.
     */
    private function hasPublishedLineup(Interclub $fixture): bool
    {
        return $fixture->users->contains(
            fn (User $player): bool => $player->registration?->selection_confirmed_at !== null,
        );
    }

    /**
     * Le camp du club dans cette rencontre, et `null` s'il n'y en a pas.
     *
     * Le calendrier contient aussi des rencontres entre deux clubs adverses —
     * l'import fédéral en ramène. Retomber sur l'équipe visiteuse « par défaut »,
     * comme le font les écrans qui savent déjà que le club joue, offrirait ici
     * l'effectif d'un autre club au capitaine qui compose.
     */
    private function ownTeamOf(Interclub $fixture): ?Team
    {
        foreach ([$fixture->visitedTeam, $fixture->visitingTeam] as $team) {
            if ($team?->club?->is_own_club) {
                return $team;
            }
        }

        return null;
    }

    /**
     * Les candidats d'une réponse donnée, parmi des rencontres déjà chargées.
     *
     * @param  EloquentCollection<int, Interclub>  $siblings
     * @param  array<int, int>  $engagedIds
     * @return Collection<int, PoolCandidate>
     */
    private function pick(EloquentCollection $siblings, InterclubAvailability $availability, array $engagedIds): Collection
    {
        return $siblings
            ->flatMap(function (Interclub $sibling) use ($engagedIds, $availability): array {
                if (! $this->hasPublishedLineup($sibling)) {
                    return [];
                }

                $team = $this->ownTeamOf($sibling);

                if (! $team instanceof Team) {
                    return [];
                }

                return $sibling->users
                    ->filter(fn (User $player): bool => $player->registration?->availability === $availability->value
                        && ! $player->registration?->is_selected
                        && ! in_array($player->id, $engagedIds, true))
                    ->map(fn (User $player): PoolCandidate => new PoolCandidate(
                        user: $player,
                        originTeam: $team,
                        availability: $availability,
                        availabilityNote: $player->registration?->availability_note,
                    ))
                    ->values()
                    ->all();
            })
            ->values();
    }

    /**
     * Toutes les rencontres de la même journée et de la même catégorie, celle-ci
     * comprise.
     *
     * @return EloquentCollection<int, Interclub>
     */
    private function sameWeekFixtures(Interclub $fixture): EloquentCollection
    {
        $fixture->loadMissing('league');
        $category = $fixture->league?->category;

        return Interclub::query()
            ->where('season_id', $fixture->season_id)
            ->where('week_number', $fixture->week_number)
            ->whereHas('league', fn ($query) => $category === null
                ? $query->whereNull('category')
                : $query->where('category', $category))
            ->with(['visitedTeam.club', 'visitingTeam.club', 'users'])
            ->orderBy('interclubs.id')
            ->get();
    }

    /**
     * Les équipes sœurs qui n'ont pas publié, et ce qu'elles retiennent.
     *
     * @param  EloquentCollection<int, Interclub>  $siblings
     * @return Collection<int, WaitingTeam>
     */
    private function waiting(EloquentCollection $siblings): Collection
    {
        return $siblings
            ->reject(fn (Interclub $sibling): bool => $this->hasPublishedLineup($sibling))
            ->map(function (Interclub $sibling): ?WaitingTeam {
                $team = $this->ownTeamOf($sibling);

                if (! $team instanceof Team) {
                    return null;
                }

                $available = $sibling->users
                    ->filter(fn (User $player): bool => $player->registration?->availability === InterclubAvailability::AVAILABLE->value)
                    ->count();

                return $available > 0 ? new WaitingTeam($team, $available) : null;
            })
            ->filter()
            ->values();
    }

    /**
     * Les capitaines des équipes en attente, et d'elles seules.
     *
     * Chargés après coup plutôt qu'en `with()` sur les deux camps de chaque
     * rencontre de la journée : le bloc d'attente est vide la plupart du temps,
     * et l'écran de composition se recharge à chaque case cochée.
     *
     * @param  Collection<int, WaitingTeam>  $waiting
     * @return Collection<int, WaitingTeam>
     */
    private function withCaptains(Collection $waiting): Collection
    {
        if ($waiting->isEmpty()) {
            return $waiting;
        }

        // `pluck()` rend une collection de base : c'est la collection Eloquent
        // qui sait charger une relation.
        new EloquentCollection($waiting->pluck('team')->all())->loadMissing('captain');

        return $waiting;
    }
}
