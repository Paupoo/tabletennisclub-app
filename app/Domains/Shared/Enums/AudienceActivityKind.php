<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * What members do, as opposed to what they are: the activity a message can be
 * aimed at — those registered for a tournament, enrolled in a training pack,
 * coming to a meeting, or playing in a team.
 */
enum AudienceActivityKind: string
{
    case Meeting = 'meeting';
    case Team = 'team';
    case Tournament = 'tournament';
    case TrainingPack = 'training_pack';

    /** Whether members can be invited to it, and therefore chased afterwards. */
    public function isInvitable(): bool
    {
        return $this !== self::Team;
    }

    public function label(): string
    {
        return match ($this) {
            self::Meeting => __('Meeting'),
            self::Team => __('Team'),
            self::Tournament => __('Tournament'),
            self::TrainingPack => __('Training pack'),
        };
    }
}
