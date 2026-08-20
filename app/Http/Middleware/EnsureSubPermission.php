<?php

namespace App\Http\Middleware;

use App\Enums\SubPermissionKey;
use App\Support\TenancyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route on an organization-scoped sub-permission for the
 * current tenancy. Owners always pass; other members must hold the
 * permission via their assigned sub-role.
 */
class EnsureSubPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $organization = TenancyContext::organization();
        $user = $request->user();

        if (! $user || ! $organization || ! $user->hasSubPermission($organization, SubPermissionKey::from($permission))) {
            abort(403);
        }

        return $next($request);
    }
}
