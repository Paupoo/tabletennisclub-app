<?php

declare(strict_types=1);

namespace App\Data\Interclub;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\InterclubAvailability;

/**
 * Un joueur qui a dit oui ou peut-être pour une journée, vu par le sélectionneur.
 *
 * `lineupTeamName` est l'équipe où il est coché cette journée, qui n'est pas
 * forcément la sienne ; `null` s'il est libre. `lineupPublished` distingue une
 * composition envoyée d'un brouillon.
 *
 * `canHelp` liste, pour un joueur libre, les équipes en manque où l'article C.22
 * ne l'interdit pas : nom de l'équipe => `true` si le verdict reste incertain.
 */
readonly class DayAvailabilityRow
{
    public function __construct(
        public User $user,
        public ?int $forceIndex,
        public string $teamName,
        public InterclubAvailability $availability,
        public ?string $lineupTeamName,
        public bool $lineupPublished,
        /** @var array<string, bool> */
        public array $canHelp,
    ) {}
}
