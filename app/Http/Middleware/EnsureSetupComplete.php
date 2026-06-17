<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Shared\Models\AppSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('setup') || $request->is('_debugbar*') || $request->is('livewire*')) {
            return $next($request);
        }

        try {
            if (AppSetting::get('setup_completed') !== '1') {
                return redirect()->route('setup');
            }
        } catch (\Exception) {
            return redirect()->route('setup');
        }

        return $next($request);
    }
}
