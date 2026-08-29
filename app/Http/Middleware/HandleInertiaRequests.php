<?php

namespace App\Http\Middleware;

use App\Enums\AiFeature;
use App\Enums\PlatformPermissionKey;
use App\Enums\SubPermissionKey;
use App\Models\Lease;
use App\Models\Property;
use App\Services\PropertyAccessService;
use App\Support\Ai\AiGateway;
use App\Support\TenancyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
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
            'centralUrl' => rtrim(config('app.url'), '/'),
            'auth' => [
                'user' => $user,
                'canRaiseMaintenance' => $user && $user->person_id
                    && Lease::query()->where('person_id', $user->person_id)
                        ->where('status', 'active')
                        ->whereNull('ends_at')
                        ->exists(),
                // AI availability for the ask-your-data feature (R0 gate;
                // other features resolve per-feature when used).
                'ai_enabled' => $user !== null
                    && app(AiGateway::class)->canUse($user, AiFeature::AskData, $organization),
                'permissions' => $this->centralPermissions($user),
                'organizations' => $user
                    ? App::make(PropertyAccessService::class)->organizationsWithProperties($user)
                    : [],
            ],
            'context' => [
                'organization' => $organization?->only('id', 'name', 'slug'),
                'is_owner' => $user !== null && $organization !== null && $user->isOwnerOf($organization),
                'property' => $property instanceof Property
                    ? $property->only('id', 'name', 'slug')
                    : null,
                'permissions' => $this->permissions($user, $organization),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'assistant' => $this->assistant($user, $organization),
        ];
    }

    /**
     * Whether the current user may use the floating AI assistant, and the
     * scope + copy to drive it. Gated by an `ai.use` permission on top of
     * the credential/R0 availability check (auth.ai_enabled). The frontend
     * resolves the actual ask URL per scope via Wayfinder.
     *
     * @return array{enabled: bool, scope?: string, title?: string, description?: string, prompts?: list<string>}|null
     */
    private function assistant(?object $user, ?object $organization): ?array
    {
        if (! $user) {
            return null;
        }

        $aiEnabled = app(AiGateway::class)->canUse($user, AiFeature::AskData, $organization);

        if ($organization) {
            $permitted = $user->hasSubPermission($organization, SubPermissionKey::AiUse);
            $scope = 'tenant';
            $copy = [
                'title' => 'Organization assistant',
                'description' => 'Ask questions about your properties, units, leases, and operations.',
                'prompts' => [
                    'How many units are vacant right now?',
                    'Summarize open maintenance by priority',
                    'Which leases expire in the next 60 days?',
                ],
            ];
        } elseif ($user->can(PlatformPermissionKey::AccessAdminDashboard->value)) {
            $permitted = $user->can(PlatformPermissionKey::AiUse->value);
            $scope = 'admin';
            $aiEnabled = $aiEnabled
                || app(AiGateway::class)->resolvePlatformOrFirstOrg($user, AiFeature::AskData) !== null;
            $copy = [
                'title' => 'Platform assistant',
                'description' => 'Ask questions across every organization on the platform.',
                'prompts' => [
                    'Which organizations have the most open maintenance?',
                    'Show revenue by plan this month',
                    'List organizations created in the last 30 days',
                ],
            ];
        } else {
            $permitted = $user->can(PlatformPermissionKey::AiUse->value);
            $scope = $user->person_id ? 'searcher' : null;
            $copy = [
                'title' => 'Your assistant',
                'description' => 'Ask questions about your own rental history.',
                'prompts' => [
                    'What was my last rent amount?',
                    'When did my most recent lease end?',
                    'Show my maintenance requests and their status',
                ],
            ];
        }

        if (! $aiEnabled || ! $permitted || ! $scope) {
            return ['enabled' => false];
        }

        return [
            'enabled' => true,
            'scope' => $scope,
            ...$copy,
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

    /**
     * The central (platform-wide) permission keys the current user holds,
     * available on every page regardless of the active tenancy. Exposed to the
     * frontend as an array so views can check membership without relying on a
     * server-side `can()` call.
     *
     * @return array<int, string>
     */
    private function centralPermissions(?object $user): array
    {
        if (! $user) {
            return [];
        }

        return array_values(array_filter(
            array_map(
                fn (PlatformPermissionKey $key): ?string => $user->can($key->value) ? $key->value : null,
                PlatformPermissionKey::cases(),
            ),
        ));
    }
}
