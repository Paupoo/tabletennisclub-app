<?php

declare(strict_types=1);

namespace App\Domains\Shared\Exceptions;

use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e): void {
            //
        });

        $this->renderable(function (InvalidSignatureException $e, Request $request): ?Response {
            if (! $request->routeIs('invitation.accept', 'invitation.store')) {
                return null;
            }

            $routeUser = $request->route('user');
            $user = $routeUser instanceof User ? $routeUser : User::find($routeUser);

            return response()->view('clubAdmin.users.auth.invitation-expired', [
                'resendUser' => $user !== null && $user->email_verified_at === null ? $user : null,
            ], 403);
        });
    }
}
