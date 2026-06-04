<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Actions;

use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Validators\TeamValidator;

class CreateTeamAction
{
    public function __construct(
        private TeamValidator $validator,
    ) {}

    public function handle(array $data): Team
    {
        // 1. Validate
        $validated = $this->validator->validateForCreation($data);

        // 2. Create
        $team = Team::create($validated);

        // 3. Dispatch event (for future use)
        // event(new TeamCreated($team));

        return $team;
    }
}
