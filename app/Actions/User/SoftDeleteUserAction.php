<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Domains\ClubAdmin\Users\Models\User;

class SoftDeleteUserAction
{
    public static function handle(User $user): void
    {
        $user->delete();
    }
}
