<?php

namespace App\Http\Middleware;

use App\Enums\OrganizationStatus;
use App\Support\TenancyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks tenant requests for a suspended organization.
 *
 * Before this existed, `organizations.status` was never read outside the edit
 * form: a suspended organization still resolved on its subdomain and its
 * members kept working. Paired with OrganizationManagementService::setStatus(),
 * which also drops their sessions so an open tab cannot linger.
 *
 * A soft-deleted organization resolves to null here (Eloquent's global scope)
 * and is deliberately left to the existing null-organization handling rather
 * than being treated as suspended.
 */
class EnsureOrganizationIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $organization = TenancyContext::organization();

        if ($organization?->status === OrganizationStatus::Suspended) {
            abort(403, "{$organization->name} is currently suspended. Please contact support to restore access.");
        }

        return $next($request);
    }
}
