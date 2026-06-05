<?php

declare(strict_types=1);

namespace App\Domains\Shared\Events\Interclub;

use App\Domains\Competitions\Interclub\Models\Team;

class TeamCreated
{
    public function __construct(public readonly Team $team) {}
}
