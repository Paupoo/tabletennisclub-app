<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks the back office for members whose profile misses required fields
 * (see User::hasCompleteProfile) and sends them to the onboarding wizard.
 *
 * Runs in the web group but only enforces on the admin/coach areas, so the
 * public site, auth/logout, setup and signed routes (invitations, meeting
 * RSVPs, tournament links) stay reachable by design.
 */
class EnsureProfileIsComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user === null
            || $user->hasCompleteProfile()
            || ! ($request->is('admin', 'admin/*', 'coach', 'coach/*'))
            || $request->routeIs('admin.user.onboarding')
        ) {
            return $next($request);
        }

        return redirect()->route('admin.user.onboarding');
    }
}
