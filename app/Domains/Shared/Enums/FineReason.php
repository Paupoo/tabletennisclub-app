<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * Why the provincial committee fined a player.
 *
 * Only the fines the provincial list charges to a player: most of its codes
 * fine the club itself (forfeits, premises, score sheets), and those are never
 * passed on to a member. The list is published each season by the province
 * (« Liste des amendes » of the CPBBW), so tariffs are not kept here — the
 * treasurer copies the total the committee asks for.
 */
enum FineReason: string
{
    case ANNOUNCED_ABSENCE = 'announced_absence';
    case CLUB_SHIRT = 'club_shirt';
    case INTERCLUB_MATCH_NOT_PLAYED = 'interclub_match_not_played';
    case MISSING_CARD = 'missing_card';
    case OTHER = 'other';
    case PROVINCIAL_TRIBUNAL = 'provincial_tribunal';
    case REFEREEING = 'refereeing';
    case UNANNOUNCED_ABSENCE = 'unannounced_absence';
    case YELLOW_CARD = 'yellow_card';

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public static function getOptions(): array
    {
        return array_map(
            fn (self $case): array => ['id' => $case->value, 'name' => $case->label()],
            self::cases()
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::ANNOUNCED_ABSENCE => __('Absence announced before the series'),
            self::CLUB_SHIRT => __('Club shirt not worn'),
            self::INTERCLUB_MATCH_NOT_PLAYED => __('Interclub match not played'),
            self::MISSING_CARD => __('Membership or identity card not shown'),
            self::OTHER => __('Other'),
            self::PROVINCIAL_TRIBUNAL => __('Provincial disciplinary board'),
            self::REFEREEING => __('Absence from or refusal of refereeing'),
            self::UNANNOUNCED_ABSENCE => __('Unannounced absence after registering'),
            self::YELLOW_CARD => __('Yellow card'),
        };
    }

    /**
     * The code of the provincial fines list, as a suggestion the treasurer may
     * correct: a club tournament uses 68–70 where a provincial championship uses
     * 65–67, and the reason alone cannot tell them apart.
     */
    public function provincialCode(): ?int
    {
        return match ($this) {
            self::ANNOUNCED_ABSENCE => 66,
            self::CLUB_SHIRT => 3,
            self::INTERCLUB_MATCH_NOT_PLAYED => 16,
            self::MISSING_CARD => 6,
            self::REFEREEING => 67,
            self::UNANNOUNCED_ABSENCE => 65,
            self::OTHER, self::PROVINCIAL_TRIBUNAL, self::YELLOW_CARD => null,
        };
    }
}
