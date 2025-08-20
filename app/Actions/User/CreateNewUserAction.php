<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Http\Requests\StoreUserRequest;
use App\Mail\InviteNewUserMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class CreateNewUserAction
{
    public static function handle(StoreUserRequest $request)
    {
        $user = User::create($request->validated());

        // Mail::to($user->email)->send(new InviteNewUserMail($user, $request->password));

        return redirect()
            ->back()
            ->with([
                'success' => __('Account created and invitation sent'),
            ]);
    }
}
