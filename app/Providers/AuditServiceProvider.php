<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Audit;
use App\Support\TenancyContext;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\ServiceProvider;

class AuditServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Fill tenant context and request metadata on every audit record,
        // whether it comes from a model event or a manual log() call.
        Audit::creating(function (Audit $activity): void {
            if (blank($activity->tenant_id)) {
                $activity->tenant_id = $this->resolveTenantId();
            }

            if (blank($activity->ip_address) && request()) {
                $activity->ip_address = request()->ip();
                $activity->user_agent = request()->userAgent();
            }
        });

        Event::listen(Login::class, function (Login $event): void {
            activity()->inLog('auth')
                ->causedBy($event->user)
                ->performedOn($event->user)
                ->event('login')
                ->log('Signed in');
        });

        Event::listen(Logout::class, function (Logout $event): void {
            activity()->inLog('auth')
                ->causedBy($event->user)
                ->performedOn($event->user)
                ->event('logout')
                ->log('Signed out');
        });
    }

    /**
     * Resolve the tenant id for an audit record: the active tenancy when
     * initialized, otherwise the authenticated user's primary organization.
     */
    protected function resolveTenantId(): ?string
    {
        if (tenancy()->initialized) {
            return TenancyContext::tenantId();
        }

        $user = Auth::user();

        if (! $user) {
            return null;
        }

        return optional($user->organizations()->first())?->tenant_id;
    }
}
