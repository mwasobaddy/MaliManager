<?php

namespace App\Http\Middleware;

use App\Support\TenancyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks organization owners from the rest of the tenant app until they
 * have created at least one property (mandatory first-property flow).
 */
class EnsureOrganizationHasProperty
{
    public function handle(Request $request, Closure $next): Response
    {
        $organization = TenancyContext::organization();

        if (! $organization || $organization->properties()->exists()) {
            return $next($request);
        }

        if ($request->routeIs('tenant.properties.create', 'tenant.properties.store')) {
            return $next($request);
        }

        return redirect()->route('tenant.properties.create');
    }
}
