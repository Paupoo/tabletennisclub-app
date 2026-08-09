<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Shared\Enums\Feature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks a route group whose functional domain is switched off.
 *
 * Answers 404 rather than 403: a disabled domain does not exist in this
 * environment, and 403 would confirm the URL is real to anyone probing it.
 */
class EnsureFeatureIsEnabled
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $flag = Feature::tryFrom($feature);

        abort_if($flag === null, 500, "Feature flag inconnu : {$feature}");
        abort_if($flag->disabled(), 404);

        return $next($request);
    }
}
