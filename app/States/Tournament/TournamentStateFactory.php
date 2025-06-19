<?php

declare(strict_types=1);

namespace App\States\Tournament;

use App\Enums\TournamentStatusEnum;
use App\States\Tournament\Contracts\TournamentStateInterface;
use App\States\Tournament\States\CancelledState;
use App\States\Tournament\States\ClosedState;
use App\States\Tournament\States\DraftState;
use App\States\Tournament\States\PendingState;
use App\States\Tournament\States\PublishedState;
use App\States\Tournament\States\SetUpState;

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
            TournamentStatusEnum::CANCELLED => new CancelledState,
        };
    }
}
