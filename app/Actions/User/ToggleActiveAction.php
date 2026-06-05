<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Domains\ClubAdmin\Users\Models\User;

class ToggleActiveAction
{
    public static function handle(User $user, bool $isActive, User $actor): void
    {
        $user->update([
            'is_active' => $isActive,
            'updated_by' => $actor->id,
        ]);
    }
}
