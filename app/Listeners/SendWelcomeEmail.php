<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Mail\MemberWelcomeMail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Mail;

class SendWelcomeEmail
{
    public function handle(Registered $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $dashboardUrl = route('dashboard');

        Mail::to($user->email)->queue(new MemberWelcomeMail($user, $dashboardUrl));
    }
}
