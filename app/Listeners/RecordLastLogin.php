<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Support\AccountProxy;
use Illuminate\Auth\Events\Login;

/**
 * Note when a member signed in, for the members list.
 *
 * A guardian taking or giving back a ward's seat goes through `Auth::login()`
 * too, and fires the same event: neither the ward nor the guardian signed in
 * then, so {@see AccountProxy} says when it is the one switching.
 *
 * Written past Eloquent on purpose: a sign-in is not an edit of the member's
 * file, so it must neither move `updated_at` nor leave a line in the audit log.
 */
class RecordLastLogin
{
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User || AccountProxy::isSwitching()) {
            return;
        }

        User::withTrashed()->whereKey($event->user->getKey())->toBase()->update(['last_login_at' => now()]);
    }
}
