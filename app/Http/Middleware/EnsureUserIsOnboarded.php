<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsOnboarded
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isOnboarded() && ! $request->routeIs('onboarding*')) {
            return redirect()->route('onboarding.show');
        }

        return $next($request);
    }
}
