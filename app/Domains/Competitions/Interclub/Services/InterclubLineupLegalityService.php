<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Services;

use App\Data\Interclub\LineupVerdict;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Shared\Enums\LeagueCategory;
use App\Domains\Shared\Enums\LineupLegality;
use App\Domains\Shared\Enums\LineupLegalityReason;

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

            $core = $this->indices($team['core'] ?? []);

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
