<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Domains\ClubAdmin\Users\Models\User;

class RestoreUserAction
{
    public static function handle(User $user): void
    {
        $user->restore();
    }
}
