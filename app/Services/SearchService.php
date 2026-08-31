<?php

namespace App\Services;

use App\Enums\PlatformPermissionKey;
use App\Models\LandParcel;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Occupant;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Database\Models\Domain;

/**
 * Global, type-aware search used by the top-nav command palette.
 *
 * Scope rules:
 *  - Tenant context (on a tenant domain): only records for the active org.
 *  - Central context, platform admin: every organization (cross-tenant).
 *  - Central context, regular searcher: only the searcher's own organizations.
 */
class SearchService
{
    /**
     * @var array<string, array{model: class-string<Model>, columns: string[], label: string, subtitle: string}>
     */
    private const SEARCHABLE = [
        'users' => ['model' => User::class, 'columns' => ['name', 'email', 'phone'], 'label' => 'name', 'subtitle' => 'email'],
        'organizations' => ['model' => Organization::class, 'columns' => ['name', 'slug'], 'label' => 'name', 'subtitle' => 'slug'],
        'properties' => ['model' => Property::class, 'columns' => ['name', 'slug', 'address_line_1', 'city'], 'label' => 'name', 'subtitle' => 'slug'],
        'land_parcels' => ['model' => LandParcel::class, 'columns' => ['name', 'slug', 'parcel_number', 'code'], 'label' => 'name', 'subtitle' => 'slug'],
        'units' => ['model' => Unit::class, 'columns' => ['name', 'unit_number'], 'label' => 'name', 'subtitle' => 'unit_number'],
        'occupants' => ['model' => Occupant::class, 'columns' => ['name', 'email', 'phone'], 'label' => 'name', 'subtitle' => 'email'],
        'leases' => ['model' => Lease::class, 'columns' => ['reference_number', 'status', 'notes'], 'label' => 'reference_number', 'subtitle' => 'status'],
        'maintenance' => ['model' => MaintenanceRequest::class, 'columns' => ['title', 'description', 'reference'], 'label' => 'title', 'subtitle' => 'reference'],
    ];

    /**
     * @return array<string, list<array{id: int|string, type: string, title: string, subtitle: ?string, url: string}>>
     */
    public function search(Request $request, string $query): array
    {
        $organization = $this->resolveOrganizationFromRequest($request);
        $isCentral = $organization === null;
        $user = $request->user();
        $isAdmin = $user instanceof User && $user->can(PlatformPermissionKey::AccessAdminDashboard->value);

        $orgIds = $this->resolveOrganizationIds($isCentral, $isAdmin, $user, $organization);

        $term = '%'.trim($query).'%';
        $results = [];

        foreach (self::SEARCHABLE as $key => $config) {
            // User records expose PII (name/email/phone); only surface them to
            // users authorized to manage users.
            if ($key === 'users' && ! $user?->can('manageUsers')) {
                continue;
            }

            $items = $this->queryType($key, $config, $term, $orgIds)->take(5)->get();

            if ($items->isEmpty()) {
                continue;
            }

            $this->eagerLoad($key, $items);

            $results[$key] = $items->map(
                fn (Model $model) => $this->toResult($key, $model, $isCentral),
            )->all();
        }

        return $results;
    }

    /**
     * @param  int[]|null  $orgIds  Null means "no organization scoping" (admin cross-tenant).
     */
    private function resolveOrganizationIds(bool $isCentral, bool $isAdmin, ?User $user, ?Organization $organization): ?array
    {
        if (! $isCentral) {
            return $organization ? [$organization->id] : [];
        }

        if ($isAdmin) {
            return null;
        }

        if (! $user) {
            return [];
        }

        return $user->organizations()->pluck('organizations.id')->all();
    }

    /**
     * Resolve the active organization from the request hostname so the search
     * stays tenant-aware without rebooting the app via the tenancy middleware
     * (which would discard the authenticated user). Null on central domains.
     */
    private function resolveOrganizationFromRequest(Request $request): ?Organization
    {
        $host = $request->getHost();

        if (in_array($host, config('tenancy.central_domains', []), true)) {
            return null;
        }

        $domain = Domain::where('domain', $host)->first();

        if (! $domain || ! $domain->tenant_id) {
            return null;
        }

        return Organization::where('tenant_id', $domain->tenant_id)->first();
    }

    /**
     * @param  array{model: class-string<Model>, columns: string[], label: string, subtitle: string}  $config
     * @param  int[]|null  $orgIds
     */
    private function queryType(string $key, array $config, string $term, ?array $orgIds)
    {
        /** @var Model $model */
        $model = new ($config['model'])();
        $table = $model->getTable();

        $columns = array_values(array_intersect(
            Schema::getColumnListing($table),
            $config['columns'],
        ));

        $query = $model->newQuery()->select("{$table}.*");

        if ($key === 'users') {
            $query->when($orgIds !== null, fn ($q) => $q->whereHas(
                'organizations',
                fn ($q) => $q->whereIn('organization_user.organization_id', $orgIds),
            ));
        } elseif (Schema::hasColumn($table, 'organization_id')) {
            $query->when($orgIds !== null, fn ($q) => $q->whereIn("{$table}.organization_id", $orgIds));
        }

        return $query->where(function ($q) use ($columns, $term, $table) {
            foreach ($columns as $column) {
                $q->orWhere("{$table}.{$column}", 'like', $term);
            }
        });
    }

    /**
     * @param  Collection<int, Model>  $items
     */
    private function eagerLoad(string $key, $items): void
    {
        match ($key) {
            'land_parcels', 'units', 'leases', 'maintenance' => $items->load('property'),
            'occupants' => $items->load('units.property'),
            default => null,
        };
    }

    private function toResult(string $key, Model $model, bool $isCentral): array
    {
        $config = self::SEARCHABLE[$key];

        return [
            'id' => $model->getKey(),
            'type' => $key,
            'title' => (string) ($model->{$config['label']} ?? $model->getKey()),
            'subtitle' => $model->{$config['subtitle']} ? (string) $model->{$config['subtitle']} : null,
            'url' => $this->urlFor($key, $model, $isCentral),
        ];
    }

    private function urlFor(string $key, Model $model, bool $isCentral): string
    {
        $orgId = method_exists($model, 'getAttribute') ? ($model->organization_id ?? null) : null;

        $tenant = function (string $name, array $params) use ($orgId, $isCentral): string {
            if (in_array(null, $params, true)) {
                return route('dashboard');
            }

            if (! $isCentral || $orgId === null) {
                return Route::has($name) ? route($name, $params) : '#';
            }

            $domain = $this->domainForOrganization((int) $orgId);

            return $domain ? 'https://'.$domain.route($name, $params, false) : route('organizations.index');
        };

        return match ($key) {
            'users' => route('users.edit', $model),
            'organizations' => route('organizations.edit', $model),
            'properties' => $tenant('tenant.properties.dashboard', ['property' => $model->slug]),
            'land_parcels' => $tenant('tenant.land-parcels.show', ['property' => $model->property?->slug, 'land_parcel' => $model->slug]),
            'units' => $tenant('tenant.properties.dashboard', ['property' => $model->property?->slug]),
            'occupants' => $tenant('tenant.properties.dashboard', ['property' => $model->units->first()?->property?->slug]),
            'leases' => $tenant('tenant.properties.dashboard', ['property' => $model->property?->slug]),
            'maintenance' => $tenant('tenant.maintenance.index', []),
            default => '#',
        };
    }

    /**
     * @var array<int, string>
     */
    private array $domainCache = [];

    private function domainForOrganization(int $organizationId): ?string
    {
        if (array_key_exists($organizationId, $this->domainCache)) {
            return $this->domainCache[$organizationId];
        }

        $organization = Organization::find($organizationId);

        $domain = $organization?->tenant_id
            ? Domain::where('tenant_id', $organization->tenant_id)->first()?->domain
            : null;

        return $this->domainCache[$organizationId] = $domain;
    }
}
