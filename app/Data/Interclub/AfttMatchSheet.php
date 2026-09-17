<?php

declare(strict_types=1);

namespace App\Data\Interclub;

/**
 * One tie's match sheet, as the clubs encoded it.
 *
 * A sheet exists for every fixture in a division, but it is an empty shell until
 * the clubs fill it in — hence `detailsCreated`, which is the only honest test
 * for "has this been played and reported". A fixture played last night and not
 * yet encoded comes back with the flag false and nothing else, and the import
 * must leave our own row alone rather than write emptiness over it.
 *
 * `score` is the team score, home first, exactly as `interclub_results` stores
 * it. It is the federation's official figure and it includes points a player
 * list cannot explain: the double, and every individual match forfeited by a
 * team that came one player short.
 *
 * @param  array<int, AfttSheetPlayer>  $homePlayers
 * @param  array<int, AfttSheetPlayer>  $awayPlayers
 * @param  array<int, AfttSheetResult>  $results
 */
readonly class AfttMatchSheet
{
    public function __construct(
        public string $matchId,
        public bool $detailsCreated,
        public ?string $score,
        public ?int $matchSystem,
        public bool $isHomeForfeited,
        public bool $isAwayForfeited,
        public array $homePlayers = [],
        public array $awayPlayers = [],
        public array $results = [],
    ) {}

    /** @return array<string, AfttSheetPlayer> keyed by licence index */
    public function playersByIndex(): array
    {
        $byIndex = [];

        foreach ([...$this->homePlayers, ...$this->awayPlayers] as $player) {
            $byIndex[$player->uniqueIndex] = $player;
        }

        return $byIndex;
    }
}
