<?php

namespace App\Http\Controllers;

use App\Http\Requests\Platform\StopImpersonationRequest;
use App\Models\Organization;
use App\Models\User;
use App\Support\InertiaRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Stancl\Tenancy\Tenancy;

/**
 * Ends a platform-admin impersonation session.
 *
 * Registered on both the central and tenant route files because an impersonated
 * admin can be sitting on either domain — the session cookie is scoped to
 * `.malimanager.test`, so the `impersonated_by` flag is readable from both and
 * the same handler restores it either way.
 */
class ImpersonationController extends Controller
{
    /**
     * Hand the session back to the admin who started it.
     *
     * The counterpart to `PlatformController::startImpersonating()`, which
     * records who is being impersonated *before* swapping the session user over.
     */
    public function stop(StopImpersonationRequest $request): RedirectResponse|Response
    {
        $impersonatedBy = $request->session()->get('impersonated_by');

        if (! is_array($impersonatedBy) || ! isset($impersonatedBy['user_id'])) {
            // Nothing to restore. Ending the tenancy and going back to the
            // central dashboard is still the right answer — the visitor is on a
            // tenant domain with no way back otherwise.
            $this->endTenancy();

            Inertia::flash('toast', [
                'type' => 'info',
                'message' => 'There was no impersonation session to end.',
            ]);

            return InertiaRedirect::to($this->centralUrl(), $request);
        }

        $admin = User::find($impersonatedBy['user_id']);

        // The admin may have been deleted or demoted while impersonating. If
        // they are no longer an admin, restoring them would hand them a session
        // the `admin` middleware will only refuse — so end the impersonation
        // and tell them plainly instead of silently logging them into nothing.
        if (! $admin || ! $admin->hasRole('admin')) {
            // `session()->invalidate()` alone would leave the guard holding a
            // user id, so the next request would still be "authenticated" as
            // the owner with an empty session. Log out first.
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $this->endTenancy();

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'The admin account that started this impersonation is no longer an admin, so you have been signed out.',
            ]);

            return InertiaRedirect::to($this->centralUrl(), $request);
        }

        $organization = isset($impersonatedBy['organization_id'])
            ? Organization::find($impersonatedBy['organization_id'])
            : null;

        // `loginUsingId()` migrates the session id, which is what makes the
        // restore a genuine re-authentication rather than a cookie edit.
        Auth::guard('web')->loginUsingId($admin->getKey());

        $request->session()->forget('impersonated_by');
        $request->session()->regenerate();

        $this->endTenancy();

        activity()->inLog('auth')
            ->causedBy($admin)
            ->performedOn($admin)
            ->event('impersonation.stopped')
            ->tap(function ($activity) use ($impersonatedBy): void {
                $activity->tenant_id = $impersonatedBy['tenant_id'] ?? null;
            })
            ->log('Stopped impersonating '.($impersonatedBy['organization'] ?? 'an organization'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Back to your admin account.'.($organization ? ' Left '.$organization->name.'.' : ''),
        ]);

        return InertiaRedirect::to($this->centralUrl('/organizations'), $request);
    }

    /**
     * Drop the active tenancy so the redirect that follows is resolved on the
     * central domain rather than inside the tenant that was being viewed.
     */
    private function endTenancy(): void
    {
        if (app()->bound(Tenancy::class)) {
            tenancy()->end();
        }
    }

    private function centralUrl(string $path = ''): string
    {
        return rtrim(config('app.url'), '/').$path;
    }
}
