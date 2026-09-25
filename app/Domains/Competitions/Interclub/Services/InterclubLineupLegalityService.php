<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Services;

use App\Data\Interclub\LineupConstraint;
use App\Data\Interclub\LineupVerdict;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\LeagueCategory;
use App\Domains\Shared\Enums\LineupLegality;
use App\Domains\Shared\Enums\LineupLegalityReason;
use Illuminate\Support\Collection;

/**
 * L'article C.22 du règlement fédéral, en arithmétique pure.
 *
 * La règle : le premier joueur d'une équipe ne peut avoir un indice de référence
 * plus petit — donc être plus fort — que le troisième joueur ayant effectivement
 * participé à la rencontre de l'équipe supérieure (C.22.1.3). Chez les dames et
 * les catégories d'âge, c'est le deuxième joueur, et de n'importe laquelle des
 * équipes supérieures (C.22.2.3). La sanction tombe sur l'équipe inférieure, qui
 * perd par le score maximum de défaite (C.22.1.4).
 *
 * Deux choses à garder en tête en lisant ce fichier :
 *
 * 1. **Un indice plus petit désigne un joueur plus fort.** `force_list = 1` est
 *    le meilleur joueur du club. Toutes les comparaisons s'en trouvent inversées
 *    par rapport à l'intuition.
 * 2. **On prédit, on ne vérifie pas.** Le règlement parle du troisième joueur
 *    *ayant effectivement joué un point* — inconnaissable avant la rencontre. On
 *    lit donc le seuil sur la composition prévue, et quand elle n'existe pas
 *    encore, sur les bornes que le noyau permet. D'où l'incertitude assumée.
 */
class InterclubLineupLegalityService
{
    /**
     * Ce que les équipes supérieures imposent à la composition d'une rencontre.
     *
     * C'est la moitié « base de données » de la règle : elle rassemble les
     * équipes qui dominent celle qu'on compose, lit leur composition de la
     * journée quand elle existe et leur noyau sinon, puis passe le tout à
     * l'arithmétique du dessus. Les deux vivent dans le même fichier parce que
     * la règle est une : la séparer ferait deux endroits à corriger le jour où
     * la fédération change le numéro de la place qui porte le seuil.
     *
     * Le rang vient du nom de l'équipe ({@see Team::rankOf()}), faute de quoi la
     * règle se tait.
     */
    public function constraintFor(Interclub $fixture): LineupConstraint
    {
        $fixture->loadMissing(['league', 'visitedTeam.club', 'visitingTeam.club']);

        $category = LeagueCategory::fromName($fixture->league?->category);
        $team = $this->ownTeamOf($fixture);

        if (! $team instanceof Team) {
            return new LineupConstraint(null, null, true, []);
        }

        $peers = $this->clubTeamsOfCategory($fixture);

        // Un seul nom illisible suffit : l'ordre de toute la catégorie devient
        // une supposition, et une supposition n'a pas à faire disparaître des
        // joueurs de l'écran d'un capitaine.
        if ($peers->contains(fn (Team $peer): bool => Team::rankOf($peer->name) === null)) {
            return new LineupConstraint(null, null, false, []);
        }

        $ownRank = Team::rankOf($team->name);

        if ($ownRank === null) {
            return new LineupConstraint(null, null, false, []);
        }

        $superior = $this->superiorTeams($peers, $ownRank, $category);

        if ($superior->isEmpty()) {
            return new LineupConstraint(null, null, true, []);
        }

        // Les noyaux ne sont chargés qu'ici : la plupart des rencontres sont
        // composées par l'équipe de tête, qui n'a personne au-dessus d'elle et
        // n'a donc besoin du noyau de personne.
        // `superiorTeams()` rend parfois une collection de base (le cas messieurs
        // n'en garde qu'une) : seule la collection Eloquent charge une relation.
        new \Illuminate\Database\Eloquent\Collection($superior->all())->loadMissing('users');

        $lineups = $this->lineupsOfWeek($fixture, $superior);

        $bounds = $this->thresholdBounds(
            $superior->map(fn (Team $peer): array => [
                'lineup' => $lineups[$peer->id] ?? null,
                'core' => $this->coreIndices($peer, $category),
            ])->values()->all(),
            $category,
        );

        return new LineupConstraint(
            strongest: $bounds['strongest'],
            weakest: $bounds['weakest'],
            rankIsReadable: true,
            superiorTeamNames: $superior->pluck('name')->values()->all(),
        );
    }

    /**
     * Les deux valeurs que le seuil de l'équipe supérieure peut prendre.
     *
     * `strongest` est le seuil si elle aligne ses meilleurs (indice petit, donc
     * contrainte faible pour nous), `weakest` celui si elle aligne ses plus
     * faibles (indice grand, contrainte forte). Quand elle a déjà composé, les
     * deux se confondent : il n'y a plus rien à prédire.
     *
     * Plusieurs équipes supérieures : on retient la plus contraignante des deux
     * côtés, soit le maximum — puisqu'enfreindre une seule d'entre elles suffit
     * à perdre la rencontre.
     *
     * @param  list<array{lineup: list<int|null>|null, core: list<int|null>}>  $superiorTeams
     * @return array{strongest: int|null, weakest: int|null}
     */
    public function thresholdBounds(array $superiorTeams, ?LeagueCategory $category): array
    {
        $position = $this->thresholdPosition($category);

        $strongest = null;
        $weakest = null;

        foreach ($superiorTeams as $team) {
            $lineup = $this->indices($team['lineup'] ?? []);

            // Une composition ne fait autorité que si elle porte la place du
            // seuil. Deux joueurs alignés sur quatre, c'est un brouillon : s'en
            // contenter rendrait « aucune contrainte », soit la réponse la plus
            // dangereuse des trois. On retombe alors sur les bornes du noyau.
            if (count($lineup) >= $position) {
                $threshold = $lineup[$position - 1];

                $strongest = $this->moreConstraining($strongest, $threshold);
                $weakest = $this->moreConstraining($weakest, $threshold);

                continue;
            }

            $core = $this->indices($team['core']);

            $strongest = $this->moreConstraining($strongest, $core[$position - 1] ?? null);
            $weakest = $this->moreConstraining($weakest, $this->weakestThreshold($core, $category, $position));
        }

        return ['strongest' => $strongest, 'weakest' => $weakest];
    }

    /**
     * Le verdict C.22 pour un joueur qu'on envisage d'ajouter à une composition.
     *
     * Jamais sur le candidat seul : la règle parle du *premier* joueur de
     * l'équipe, donc du plus fort de la composition une fois le candidat dedans.
     * Un renfort faible ajouté à côté d'un titulaire fort ne change rien — et
     * c'est le titulaire fort qui, lui, peut tout faire basculer.
     *
     * @param  list<int|null>  $lineupIndices  la composition en cours, candidat exclu
     * @param  array{strongest: int|null, weakest: int|null}  $bounds
     */
    public function verdictFor(?int $candidateIndex, array $lineupIndices, array $bounds): LineupVerdict
    {
        if ($bounds['strongest'] === null && $bounds['weakest'] === null) {
            return new LineupVerdict(LineupLegality::NOT_APPLICABLE, LineupLegalityReason::NO_SUPERIOR_TEAM);
        }

        $all = [...$lineupIndices, $candidateIndex];

        // Un seul indice manquant suffit à rendre le plus fort de la composition
        // inconnaissable. On ne devine pas : un `null` traité comme un zéro se
        // lirait comme le meilleur joueur du club.
        if (in_array(null, $all, true)) {
            return new LineupVerdict(LineupLegality::UNCERTAIN, LineupLegalityReason::MISSING_REFERENCE_INDEX);
        }

        $strongestOfLineup = min($all);

        // Au moins aussi faible que le seuil le plus exigeant : légal quoi que
        // fasse l'équipe supérieure.
        if ($bounds['weakest'] !== null && $strongestOfLineup >= $bounds['weakest']) {
            return new LineupVerdict(LineupLegality::ALLOWED, LineupLegalityReason::WEAKER_THAN_THRESHOLD);
        }

        // Plus fort que le seuil le plus clément : illégal dans tous les cas.
        if ($bounds['strongest'] !== null && $strongestOfLineup < $bounds['strongest']) {
            return new LineupVerdict(LineupLegality::FORBIDDEN, LineupLegalityReason::STRONGER_THAN_THRESHOLD);
        }

        return new LineupVerdict(LineupLegality::UNCERTAIN, LineupLegalityReason::SUPERIOR_LINEUP_UNKNOWN);
    }

    /**
     * Les équipes du club engagées dans la même catégorie et la même saison.
     *
     * @return Collection<int, Team>
     */
    private function clubTeamsOfCategory(Interclub $fixture): Collection
    {
        $category = $fixture->league?->category;

        return Team::query()
            ->where('teams.season_id', $fixture->season_id)
            ->whereHas('club', fn ($query) => $query->where('is_own_club', true))
            ->whereHas('league', fn ($query) => $category === null
                ? $query->whereNull('category')
                : $query->where('category', $category))
            ->orderBy('teams.id')
            ->get();
    }

    /**
     * Les indices de référence du noyau, dans la sous-liste de la catégorie.
     *
     * @return list<int|null>
     */
    private function coreIndices(Team $team, ?LeagueCategory $category): array
    {
        return $team->users
            ->map(fn (User $player): ?int => $player->forceListFor($category))
            ->values()
            ->all();
    }

    /**
     * Les indices exploitables d'une composition ou d'un noyau, du plus fort au
     * plus faible. Les indices manquants sont écartés ici ; l'incertitude qu'ils
     * créent se traite ailleurs, avec son propre motif.
     *
     * @param  list<int|null>  $indices
     * @return list<int>
     */
    private function indices(array $indices): array
    {
        $known = array_values(array_filter($indices, fn (?int $index): bool => $index !== null));

        sort($known);

        return $known;
    }

    /**
     * Le nombre de joueurs qu'une équipe aligne dans cette catégorie.
     *
     * Les mêmes chiffres que {@see Interclub::setTotalPlayersPerTeam()},
     * qui les tient depuis plus longtemps — les deux doivent rester d'accord.
     */
    private function lineupSize(?LeagueCategory $category): int
    {
        return $category === LeagueCategory::MEN ? 4 : 3;
    }

    /**
     * Les compositions de la journée, par équipe supérieure.
     *
     * @param  Collection<int, Team>  $superior
     * @return array<int, list<int|null>>
     */
    private function lineupsOfWeek(Interclub $fixture, Collection $superior): array
    {
        $category = LeagueCategory::fromName($fixture->league?->category);
        $teamIds = $superior->pluck('id')->all();

        $fixtures = Interclub::query()
            ->with(['users'])
            ->where('season_id', $fixture->season_id)
            ->where('week_number', $fixture->week_number)
            ->where('id', '!=', $fixture->id)
            ->where(fn ($query) => $query
                ->whereIn('visited_team_id', $teamIds)
                ->orWhereIn('visiting_team_id', $teamIds))
            ->orderBy('interclubs.id')
            ->get();

        $lineups = [];

        foreach ($fixtures as $sibling) {
            $selected = $sibling->users
                // Le WO est sur la feuille mais ne joue aucun point : C.22.1.3 ne
                // lit que les joueurs effectifs. Le compter ne pourrait que
                // baisser le seuil, donc autoriser à tort.
                ->filter(fn (User $player): bool => (bool) $player->registration?->is_selected
                    && ! $player->registration?->is_walkover)
                ->map(fn (User $player): ?int => $player->forceListFor($category))
                ->values()
                ->all();

            if ($selected === []) {
                continue;
            }

            foreach ([$sibling->visited_team_id, $sibling->visiting_team_id] as $teamId) {
                if ($teamId !== null && in_array($teamId, $teamIds, true)) {
                    $lineups[$teamId] = $selected;
                }
            }
        }

        return $lineups;
    }

    /**
     * Du seuil déjà retenu et du nouveau, celui qui contraint le plus.
     *
     * Le premier joueur de l'équipe inférieure doit avoir un indice *au moins
     * égal* au seuil : plus le seuil est grand, moins il nous laisse de joueurs.
     */
    private function moreConstraining(?int $current, ?int $candidate): ?int
    {
        if ($candidate === null) {
            return $current;
        }

        return $current === null ? $candidate : max($current, $candidate);
    }

    /** Le camp du club dans cette rencontre, et `null` s'il n'y en a pas. */
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
     * Les équipes qui contraignent celle-ci.
     *
     * Chez les messieurs, l'équipe immédiatement supérieure et elle seule : la
     * sanction de C.22.1.4 vise « l'équipe immédiatement inférieure ». Chez les
     * dames et les catégories d'âge, C.22.2.4 parle de « une des équipes
     * supérieures » — donc toutes.
     *
     * « Immédiatement supérieure » se lit comme la plus proche au-dessus, et non
     * comme le rang moins un : une catégorie peut n'avoir ni B ni D.
     *
     * @param  Collection<int, Team>  $peers
     * @return Collection<int, Team>
     */
    private function superiorTeams(Collection $peers, int $ownRank, ?LeagueCategory $category): Collection
    {
        $above = $peers
            ->filter(fn (Team $peer): bool => (Team::rankOf($peer->name) ?? PHP_INT_MAX) < $ownRank)
            ->sortBy(fn (Team $peer): int => Team::rankOf($peer->name) ?? PHP_INT_MAX)
            ->values();

        if ($category !== LeagueCategory::MEN) {
            return $above;
        }

        $nearest = $above->last();

        return $nearest instanceof Team ? collect([$nearest]) : collect();
    }

    /**
     * La place qui porte le seuil chez l'équipe supérieure : le troisième joueur
     * chez les messieurs (C.22.1.3), le deuxième chez les dames et les
     * catégories d'âge (C.22.2.3).
     */
    private function thresholdPosition(?LeagueCategory $category): int
    {
        return $category === LeagueCategory::MEN ? 3 : 2;
    }

    /**
     * Le seuil si l'équipe supérieure alignait ses joueurs les plus faibles.
     *
     * Elle en aligne `lineupSize()`, pris par le bas du noyau ; le seuil est le
     * `position`-ième d'entre eux. Un noyau trop court pour remplir une équipe
     * ne laisse aucun choix : la borne rejoint alors celle du haut.
     *
     * @param  list<int>  $core
     */
    private function weakestThreshold(array $core, ?LeagueCategory $category, int $position): ?int
    {
        $offset = count($core) - $this->lineupSize($category) + $position - 1;

        return $core[max($offset, $position - 1)] ?? null;
    }
}
