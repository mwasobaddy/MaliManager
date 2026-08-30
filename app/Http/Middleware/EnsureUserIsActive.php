<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces a logout + redirect for any authenticated user whose account is
 * not `active` (i.e. `suspended` or `inactive`). This closes the gap where
 * suspension only blocked new logins but left live sessions usable.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && Auth::user()->status !== 'active') {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson() && ! $request->inertia()) {
                return response()->json(['message' => 'Your account has been suspended.'], 403);
            }

            return redirect()->route('suspended');
        }

        return $next($request);
    }
}
