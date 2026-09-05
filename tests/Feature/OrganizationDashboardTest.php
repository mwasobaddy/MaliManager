<?php

use App\Models\Delegation;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Property;
use App\Models\SubRole;
use App\Models\User;
use App\Services\TenantService;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

function createOverviewOrganization(User $user, string $name = 'Acme Estates'): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $user,
        name: $name,
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
}

function tenantOverviewUrl(Organization $organization, string $path = ''): string
{
    $domain = $organization->tenant->domains()->value('domain');

    return "http://{$domain}{$path}";
}

function createOverviewProperty(Organization $organization, User $user, string $name, string $slug): Property
{
    return $organization->properties()->create([
        'name' => $name,
        'slug' => $slug,
        'status' => 'active',
        'created_by' => $user->id,
    ]);
}

test('the organization overview renders portfolio stats for an owner', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOverviewOrganization($user);
    $property = createOverviewProperty($organization, $user, 'Sunset Heights', 'sunset-heights');

    $property->units()->createMany([
        ['name' => 'A1', 'status' => 'occupied', 'monthly_rent' => 25000, 'created_by' => $user->id],
        ['name' => 'A2', 'status' => 'vacant', 'monthly_rent' => 45000, 'created_by' => $user->id],
        ['name' => 'A3', 'status' => 'maintenance', 'monthly_rent' => 30000, 'created_by' => $user->id],
    ]);

    $this->actingAs($user)
        ->get(tenantOverviewUrl($organization, '/overview'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant/dashboard')
            ->where('organization.name', 'Acme Estates')
            ->where('properties_count', 1)
            ->where('total_units', 3)
            ->where('occupied', 1)
            ->where('vacant', 1)
            ->where('in_maintenance', 1)
            ->where('occupancy_rate', 33)
            ->where('properties.0.name', 'Sunset Heights')
            ->where('properties.0.total_units', 3)
            ->where('properties.0.occupancy_rate', 33)
            ->where('rent_potential', fn ($value) => $value == 100000)
            ->where('operations_monthly', fn ($rows) => count($rows) === 12));
});

test('the organization overview compares every accessible property for an owner', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOverviewOrganization($user);

    $first = createOverviewProperty($organization, $user, 'First Estate', 'first-estate');
    $second = createOverviewProperty($organization, $user, 'Second Estate', 'second-estate');

    $first->units()->create(['name' => 'A1', 'status' => 'occupied', 'monthly_rent' => 20000, 'created_by' => $user->id]);
    $second->units()->createMany([
        ['name' => 'B1', 'status' => 'occupied', 'monthly_rent' => 30000, 'created_by' => $user->id],
        ['name' => 'B2', 'status' => 'vacant', 'monthly_rent' => 35000, 'created_by' => $user->id],
    ]);

    $this->actingAs($user)
        ->get(tenantOverviewUrl($organization, '/overview'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant/dashboard')
            ->where('properties_count', 2)
            ->where('total_units', 3)
            ->where('occupied', 2)
            ->where('vacant', 1)
            ->where('properties.0.occupied', 1)
            ->where('properties.1.occupied', 1)
            ->where('properties.1.vacant', 1));
});

test('the organization overview only shows properties delegated to staff', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOverviewOrganization($owner);

    $delegated = createOverviewProperty($organization, $owner, 'Delegated Estate', 'delegated-estate');
    $other = createOverviewProperty($organization, $owner, 'Other Estate', 'other-estate');

    $delegated->units()->create(['name' => 'A1', 'status' => 'occupied', 'monthly_rent' => 20000, 'created_by' => $owner->id]);
    $other->units()->create(['name' => 'B1', 'status' => 'vacant', 'monthly_rent' => 30000, 'created_by' => $owner->id]);

    $staff = User::factory()->create(['onboarded_at' => now()]);
    $subRole = SubRole::factory()->create();

    $staff->organizations()->attach($organization->id, [
        'is_owner' => false,
        'sub_role_id' => $subRole->id,
        'status' => 'active',
    ]);

    $membership = $staff->membershipFor($organization);
    Delegation::create([
        'organization_user_id' => $membership->id,
        'organization_id' => $organization->id,
        'delegatable_type' => Property::class,
        'delegatable_id' => $delegated->id,
    ]);

    $this->actingAs($staff)
        ->get(tenantOverviewUrl($organization, '/overview'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant/dashboard')
            ->where('properties_count', 1)
            ->where('total_units', 1)
            ->where('occupied', 1)
            ->where('properties.0.name', 'Delegated Estate'));
});

test('the organization overview switches to a daily axis when a month is selected', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOverviewOrganization($user);
    createOverviewProperty($organization, $user, 'Sunset Heights', 'sunset-heights');

    $this->actingAs($user)
        ->get(tenantOverviewUrl($organization, '/overview?year=2026&month=2'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant/dashboard')
            ->where('operations_monthly', fn ($rows) => count($rows) === 28)
            ->where('operations_monthly.0.label', '1')
            ->where('operations_monthly.27.label', '28'));
});
