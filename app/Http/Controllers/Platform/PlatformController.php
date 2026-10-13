<?php

namespace App\Http\Controllers\Platform;

use App\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\ImpersonateOrganizationRequest;
use App\Models\Organization;
use App\Models\User;
use App\Support\InertiaRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformController extends Controller
{
    public function index(): Response
    {
        $organizations = Organization::query()
            ->with([
                'plan:id,slug,name',
                'tenant',
                'users' => fn ($query) => $query->select('users.id', 'users.name', 'users.email'),
            ])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (Organization $organization) => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'status' => $organization->status,
                'plan' => $organization->plan?->name,
                'domain' => $organization->tenant?->domains()->value('domain'),
                'users' => $organization->users->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ]),
                'created_at' => $organization->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('platform/dashboard', [
            'organizations' => $organizations,
        ]);
    }

    public function impersonate(Request $request, Organization $organization, User $user): RedirectResponse|\Illuminate\Http\Response
    {
        if (! $organization->users()->whereKey($user->id)->exists()) {
            abort(403, 'User is not a member of this organization.');
        }

        return $this->startImpersonating($request, $organization, $user);
    }

    /**
     * Impersonate the organization's owner.
     *
     * Used by the organizations list, where only the organization is known and
     * the target member has to be resolved server-side from the is_owner pivot.
     */
    public function impersonateOwner(ImpersonateOrganizationRequest $request, Organization $organization): RedirectResponse|\Illuminate\Http\Response
    {
        $owner = $organization->users()->wherePivot('is_owner', true)->first();

        if (! $owner) {
            abort(422, 'Organization has no owner to impersonate.');
        }

        return $this->startImpersonating($request, $organization, $owner);
    }

    private function startImpersonating(Request $request, Organization $organization, User $user): RedirectResponse|\Illuminate\Http\Response
    {
        if ($user->hasRole('admin')) {
            abort(403, 'Admins cannot be impersonated.');
        }

        // Impersonating a suspended organization would land the admin behind
        // the `organization.active` middleware, so refuse up front instead.
        if ($organization->status === OrganizationStatus::Suspended) {
            abort(403, 'Cannot impersonate a suspended organization.');
        }

        $domain = $organization->tenant?->domains()->first();

        if (! $domain) {
            abort(422, 'Organization has no domain configured yet.');
        }

        // Stancl's impersonation is fire-and-forget: redeeming the token calls
        // Auth::loginUsingId() and overwrites the session user, keeping the
        // previous identity nowhere. Without this the admin is unrecoverable
        // short of a full logout and a fresh login. The session cookie is
        // scoped to `.malimanager.test`, so the flag written here is visible on
        // the tenant domain too, and SessionGuard::login()'s migrate(true)
        // keeps the payload across the token redemption.
        $actor = $request->user();

        $request->session()->put('impersonated_by', [
            'user_id' => $actor->getKey(),
            'name' => $actor->name,
            'email' => $actor->email,
            'organization_id' => $organization->getKey(),
            'organization' => $organization->name,
            'tenant_id' => $organization->tenant_id,
            'started_at' => now()->toIso8601String(),
        ]);

        $token = tenancy()->impersonate(
            $organization->tenant,
            $user->getKey(),
            '/properties',
            'web',
        );

        activity()->inLog('auth')
            ->causedBy($actor)
            ->performedOn($user)
            ->event('impersonation.started')
            ->tap(function ($activity) use ($organization) {
                $activity->tenant_id = $organization->tenant_id;
            })
            ->log('Started impersonating '.$user->email);

        $scheme = $request->secure() ? 'https' : 'http';

        return InertiaRedirect::to("{$scheme}://{$domain->domain}/impersonate/{$token->token}", $request);
    }
}
