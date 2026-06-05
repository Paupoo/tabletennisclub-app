<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Shared\Models\AppSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupNotComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        if (AppSetting::get('setup_completed') === '1') {
            return redirect('/');
        }

        return $next($request);
    }
}
