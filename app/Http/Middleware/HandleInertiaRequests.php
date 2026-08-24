<?php

namespace App\Http\Middleware;

use App\Enums\SubPermissionKey;
use App\Models\Property;
use App\Support\TenancyContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $organization = TenancyContext::organization();
        $property = $request->route('property');
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            'context' => [
                'organization' => $organization?->only('id', 'name', 'slug'),
                'property' => $property instanceof Property
                    ? $property->only('id', 'name', 'slug')
                    : null,
                'permissions' => $this->permissions($user, $organization),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * The sub-permission keys the current user holds in the active
     * tenancy. Owners implicitly hold every permission.
     *
     * @return array<int, string>
     */
    private function permissions(?object $user, ?object $organization): array
    {
        if (! $user || ! $organization) {
            return [];
        }

        if ($user->isOwnerOf($organization)) {
            return array_map(fn (SubPermissionKey $key) => $key->value, SubPermissionKey::cases());
        }

        $membership = $user->membershipFor($organization);

        if (! $membership?->sub_role_id) {
            return [];
        }

        return $membership->subRole
            ->subPermissions()
            ->pluck('key')
            ->all();
    }
}
