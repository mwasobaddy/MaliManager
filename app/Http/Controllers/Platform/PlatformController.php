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

        $token = tenancy()->impersonate(
            $organization->tenant,
            $user->getKey(),
            '/properties',
            'web',
        );

        activity()->inLog('auth')
            ->causedBy($request->user())
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
