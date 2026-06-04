<?php

declare(strict_types=1);

namespace App\Domains\Shared\States\Tournament;

use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Domains\Shared\States\Tournament\Contracts\TournamentStateInterface;
use App\Domains\Shared\States\Tournament\States\ClosedState;
use App\Domains\Shared\States\Tournament\States\DraftState;
use App\Domains\Shared\States\Tournament\States\LockedState;
use App\Domains\Shared\States\Tournament\States\PendingState;
use App\Domains\Shared\States\Tournament\States\PublishedState;
use App\Domains\Shared\States\Tournament\States\SetUpState;

final class TournamentStateFactory
{
    public static function create(TournamentStatusEnum $status): TournamentStateInterface
    {
        return match ($status) {
            TournamentStatusEnum::DRAFT => new DraftState,
            TournamentStatusEnum::PUBLISHED => new PublishedState,
            TournamentStatusEnum::SETUP => new SetUpState,
            TournamentStatusEnum::PENDING => new PendingState,
            TournamentStatusEnum::CLOSED => new ClosedState,
            TournamentStatusEnum::LOCKED => new LockedState,
            TournamentStatusEnum::CANCELLED => new LockedState,
        };
    }
}
