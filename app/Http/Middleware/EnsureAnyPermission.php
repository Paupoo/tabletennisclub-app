<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Opens a route to whoever holds any one of several permissions.
 *
 * The framework's `can` middleware asks for exactly one ability, which stops
 * being enough as soon as a screen serves two duties that never overlap: the
 * member's file answers both to whoever keeps their data up to date and to
 * whoever hands out their rights, and neither of them holds the other's
 * permission. The screen then decides what each of them may see.
 */
class EnsureAnyPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        foreach ($permissions as $permission) {
            if ($user?->can($permission)) {
                return $next($request);
            }
        }

        abort(403);
    }
}
