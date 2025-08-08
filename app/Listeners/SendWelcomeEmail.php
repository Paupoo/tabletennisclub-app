<?php

namespace App\Listeners;

use App\Mail\InviteNewUserMail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Mail;

class SendWelcomeEmail
{


    /**
     * Handle the event.
     */
    public function handle(Registered $event): void
    {
        Mail::to($event->user->email)
                ->send(new InviteNewUserMail($event->user, 'daefaefaefa')); 

    }
}
