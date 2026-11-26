<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\Tenants\UpdateOrganizationSecurity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateOrganizationSecurityRequest;
use App\Support\TenancyContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Organization security policy (staff two-factor requirement).
 * Owner-only: enforced inside the action, not just the route gate,
 * so the policy switch is never one delegation away from capture.
 */
class OrganizationSecurityController extends Controller
{
    public function update(
        UpdateOrganizationSecurityRequest $request,
        UpdateOrganizationSecurity $update,
    ): RedirectResponse {
        $organization = TenancyContext::organization()->refresh();

        $update->handle($request->user(), $organization, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Security policy updated.']);

        return back();
    }
}
