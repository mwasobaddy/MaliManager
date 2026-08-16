<?php

namespace App\Http\Middleware;

use App\Enums\PlatformRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsPlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasRole(PlatformRole::Admin->value)) {
            abort(403);
        }

        return $next($request);
    }
}
