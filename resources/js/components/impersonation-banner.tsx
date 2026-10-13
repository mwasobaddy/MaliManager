import { usePage } from '@inertiajs/react';
import { Eye, LogOut } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { stop as stopImpersonation } from '@/routes/impersonation';

/**
 * A persistent strip shown for as long as a platform admin is signed in as an
 * organization owner.
 *
 * This exists because the impersonated session is otherwise invisible. Stancl's
 * `UserImpersonation` overwrites the session user and records nothing, so the
 * admin's browser looks exactly like a normal one — the sidebar shrinks to the
 * owner's links and the platform section simply vanishes, with no indication
 * that this is a temporary borrowed session rather than the account they think
 * they are in. Worse, the browser's Back button can restore a *cached* render of
 * the admin page, so the app can look like the admin is still in control for a
 * moment after they are not. A strip that is present on every page, on both
 * domains, contradicts that stale render immediately.
 *
 * It is a real HTML form posting to the central domain rather than an Inertia
 * `<Form>`: ending an impersonation moves the visitor to a *different host*, and
 * an Inertia POST is an XHR, so it would fail CORS before the redirect could
 * happen. A plain form does a full page load, which is the right semantic here
 * anyway — the answer is "switch domain", not "swap a prop".
 */
export function ImpersonationBanner() {
    const impersonation = usePage().props.impersonation;

    if (!impersonation) {
        return null;
    }

    const started = impersonation.started_at
        ? new Date(impersonation.started_at)
        : null;

    return (
        <div
            role="status"
            data-testid="impersonation-banner"
            className="bg-status-warning/12 text-status-warning border-status-warning/30 flex flex-wrap items-center justify-between gap-3 border-b px-4 py-2 text-sm"
        >
            <div className="flex min-w-0 items-center gap-2">
                <Eye className="size-4 shrink-0" aria-hidden />
                <span className="min-w-0">
                    <span className="font-medium">
                        Viewing as an owner of {impersonation.organization}
                    </span>
                    <span className="text-muted-foreground">
                        {' '}
                        — signed in by {impersonation.admin.name}
                        {started
                            ? ` at ${started.toLocaleTimeString()}`
                            : ''}
                        . This is not your own account.
                    </span>
                </span>
            </div>

            {impersonation.can_return ? (
                <form method="post" action={stopImpersonation().url}>
                    <input
                        type="hidden"
                        name="_token"
                        value={csrfToken()}
                    />
                    <Button type="submit" size="sm" variant="outline">
                        <LogOut className="size-4" />
                        Return to admin
                    </Button>
                </form>
            ) : (
                <span className="text-xs">
                    The admin who started this session can no longer be restored.
                    Sign out to continue.
                </span>
            )}
        </div>
    );
}

/**
 * The CSRF token for the banner's plain form.
 *
 * Read from the `XSRF-TOKEN` cookie rather than a prop so this component needs
 * nothing extra from the server. Returns '' when the cookie is absent, which
 * makes the form fall back to Laravel's own error rather than silently posting
 * an empty token.
 */
function csrfToken(): string {
    if (typeof document === 'undefined') {
        return '';
    }

    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}
