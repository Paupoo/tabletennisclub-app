<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Feedback\Services;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use Illuminate\Database\Eloquent\Collection;

/**
 * Who is told, as it arrives, that a member wrote or offered to help.
 */
final class FeedbackReaders
{
    /**
     * The holders of the suggestions délégation; the whole committee while
     * nobody holds it, so that no message ever lands unread.
     *
     * @return Collection<int, User>
     */
    public function toNotify(): Collection
    {
        $delegates = User::role(Role::FEEDBACK->value)->get();

        return $delegates->isNotEmpty() ? $delegates : User::role(Role::COMMITTEE->value)->get();
    }
}
