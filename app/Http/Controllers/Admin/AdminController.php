<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Support\InertiaRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
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

        return Inertia::render('admin/dashboard', [
            'organizations' => $organizations,
        ]);
    }

    public function impersonate(Request $request, Organization $organization, User $user): RedirectResponse|\Illuminate\Http\Response
    {
        if (! $organization->users()->whereKey($user->id)->exists()) {
            abort(403, 'User is not a member of this organization.');
        }

        if ($user->hasRole('admin')) {
            abort(403, 'Admins cannot be impersonated.');
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
