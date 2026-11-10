<?php

use App\Http\Middleware\EnsureOrganizationHasProperty;
use App\Http\Middleware\EnsureOrganizationIsActive;
use App\Http\Middleware\EnsureSubPermission;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsOnboarded;
use App\Http\Middleware\EnsureUserIsPlatformAdmin;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Safaricom posts STK callbacks without a session; the callback
        // correlates purely by payload and answers its own envelope.
        $middleware->validateCsrfTokens(except: ['billing/mpesa/callback']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            EnsureUserIsActive::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'onboarded' => EnsureUserIsOnboarded::class,
            'admin' => EnsureUserIsPlatformAdmin::class,
            'has-property' => EnsureOrganizationHasProperty::class,
            'sub-permission' => EnsureSubPermission::class,
            'organization.active' => EnsureOrganizationIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->respond(function (Response $response, Throwable $e, Request $request): Response {
            if ($request->expectsJson() || $request->is('api/*')) {
                return $response;
            }

            $toastBack = function (string $message) use ($request): Response {
                Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

                return $request->headers->get('referer')
                    ? redirect()->back()
                    : redirect()->route('dashboard');
            };

            // Address-bar navigation to a missing URL gets a branded 404 with
            // the real status code. Branded 403/429/500 for full-page loads
            // live alongside their branches below; Inertia visits always keep
            // the toast-and-back behaviour so live app state is never swapped
            // for an error page.
            if (! $request->header('X-Inertia') && $e instanceof HttpException && $e->getStatusCode() === 404) {
                return Inertia::render('errors/404')
                    ->toResponse($request)
                    ->setStatusCode(404);
            }

            // Domain-rule failures thrown by services surface as a toast.
            if ($e instanceof DomainException) {
                return $toastBack($e->getMessage());
            }

            if ($e instanceof HttpException && $e->getStatusCode() === 403) {
                // A 403 during an impersonation session is almost never about
                // the impersonated user's own permissions — the person has none,
                // because they are an admin wearing an owner's session. Saying
                // only "you do not have permission" sends them hunting for a
                // permission they never had, when the real answer is that they
                // are in someone else's session and can hand it back.
                $impersonatedBy = $request->session()->get('impersonated_by');

                if (is_array($impersonatedBy) && isset($impersonatedBy['organization'])) {
                    Inertia::flash('toast', [
                        'type' => 'warning',
                        'message' => 'You are signed in as an owner of '
                            .$impersonatedBy['organization']
                            .' because an admin started an impersonation session. '
                            .'Use "Return to admin" to get your own account back.',
                    ]);

                    return $request->headers->get('referer')
                        ? redirect()->back()
                        : redirect()->route('dashboard');
                }

                // Full-page loads outside debug (real users, not tests and not
                // Inertia visits) get the branded page with its actions
                // instead of a disorienting bounce to the dashboard.
                if (! $request->header('X-Inertia') && ! config('app.debug')) {
                    return Inertia::render('errors/403')
                        ->toResponse($request)
                        ->setStatusCode(403);
                }

                return $toastBack(
                    $e->getMessage() !== ''
                        ? $e->getMessage()
                        : 'You do not have permission to perform this action.',
                );
            }

            // Custom aborts carrying a user-facing message (e.g. self-delete
            // guards) become toasts as well. ValidationException is not an
            // HttpException and keeps rendering inline form errors.
            if ($e instanceof HttpException && $e->getStatusCode() === 422 && $e->getMessage() !== '') {
                return $toastBack($e->getMessage());
            }

            // Throttled full-page loads outside debug get the branded page,
            // keeping the server's Retry-After so clients still back off.
            if ($e instanceof HttpException && $e->getStatusCode() === 429
                && ! $request->header('X-Inertia') && ! config('app.debug')) {
                return Inertia::render('errors/429')
                    ->toResponse($request)
                    ->setStatusCode(429)
                    ->header('Retry-After', (string) ($e->getHeaders()['Retry-After'] ?? '60'));
            }

            if ($response->getStatusCode() === 500 && ! config('app.debug')) {
                // Full-page loads get the branded page; Inertia visits keep
                // the toast so live app state is never swapped for an error.
                if (! $request->header('X-Inertia')) {
                    return Inertia::render('errors/500')
                        ->toResponse($request)
                        ->setStatusCode(500);
                }

                Inertia::flash('toast', [
                    'type' => 'error',
                    'message' => 'Something went wrong. Please try again.',
                ]);

                return redirect()->back();
            }

            return $response;
        });
    })->create();
