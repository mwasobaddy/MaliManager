<?php

namespace App\Providers;

use App\Foundation\TenantAwareVite;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passkeys\Passkeys;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Use the tenant-aware Vite resolver so dev (hot) asset URLs are
        // emitted on the current request host, keeping Vite module imports
        // same-origin on tenant subdomains (avoids cross-origin fetch
        // failures / white screens when switching assets).
        $this->app->singleton(Vite::class, TenantAwareVite::class);

        // Passkeys disabled until the feature is wanted again. The package
        // registers its routes unconditionally, so commenting out the Fortify
        // feature flag alone would leave /auth/passkeys/* live. register() runs
        // before every provider boots, so this lands in time.
        Passkeys::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Emit Vite asset URLs as root-relative paths so the shared build is
        // served from the current host. This keeps assets on the tenant
        // subdomain's scheme (https) instead of an absolute APP_URL, which
        // would otherwise trigger mixed-content blocks on the tenant pages.
        // Combined with `asset_helper_tenancy => false`, the frontend never
        // routes through the tenant asset controller (which only serves
        // storage/app/public uploads), so the /build assets resolve correctly.
        app(Vite::class)->createAssetPathsUsing(fn (string $path): string => '/'.ltrim($path, '/'));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
