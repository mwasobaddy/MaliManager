<?php

use App\Http\Middleware\EnsureOrganizationHasProperty;
use App\Http\Middleware\EnsureSubPermission;
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
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'onboarded' => EnsureUserIsOnboarded::class,
            'admin' => EnsureUserIsPlatformAdmin::class,
            'has-property' => EnsureOrganizationHasProperty::class,
            'sub-permission' => EnsureSubPermission::class,
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

            if ($e instanceof HttpException && $e->getStatusCode() === 403) {
                $message = $e->getMessage() !== ''
                    ? $e->getMessage()
                    : 'You do not have permission to perform this action.';

                $redirect = $request->headers->get('referer')
                    ? redirect()->back()
                    : redirect()->route('dashboard');

                Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

                return $redirect;
            }

            if ($response->getStatusCode() === 500 && ! config('app.debug')) {
                Inertia::flash('toast', [
                    'type' => 'error',
                    'message' => 'Something went wrong. Please try again.',
                ]);

                return redirect()->back();
            }

            return $response;
        });
    })->create();
