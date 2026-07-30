<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Shared\Enums\Permission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CommitteeMemberMiddelware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->can(Permission::UsersView->value)) {
            abort(403);
        }

        return $next($request);
    }
}
