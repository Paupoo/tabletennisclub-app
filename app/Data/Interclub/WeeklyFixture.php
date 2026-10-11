<?php

declare(strict_types=1);

namespace App\Data\Interclub;

use App\Domains\Shared\Enums\InterclubForfeit;
use Carbon\CarbonInterface;

/**
 * Une tuile de « Sélections de la semaine » : qui joue, dans quelle équipe,
 * contre qui, quand et où.
 *
 * `players` reste vide tant que la compo n'est pas envoyée : un brouillon de
 * capitaine n'est jamais montré.
 */
readonly class WeeklyFixture
{
    /**
     * @param  array<int, array{id: int, last_name: string, first_name: string, ranking: string|null, force_list: int|null, is_walkover: bool}>  $players
     */
    public function __construct(
        public int $id,
        public CarbonInterface $startsAt,
        public string $teamName,
        public string $opponentName,
        public bool $isHome,
        public ?string $town,
        public ?InterclubForfeit $forfeit,
        public bool $isPublished,
        public ?int $shortHandedWith,
        public array $players,
        public ?int $matchDay = null,
    ) {}

    /**
     * Whether the given member is in the published lineup.
     */
    public function involves(int $userId): bool
    {
        return in_array($userId, array_column($this->players, 'id'), true);
    }
}
