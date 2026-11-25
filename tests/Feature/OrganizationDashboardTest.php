<?php

use App\Models\Delegation;
use App\Models\Lease;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Plan;
use App\Models\Property;
use App\Models\SubRole;
use App\Models\Unit;
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
        ['name' => 'A1', 'status' => 'occupied', 'rent_amount' => 25000, 'rent_frequency' => 'monthly', 'created_by' => $user->id],
        ['name' => 'A2', 'status' => 'vacant', 'rent_amount' => 45000, 'rent_frequency' => 'monthly', 'created_by' => $user->id],
        ['name' => 'A3', 'status' => 'maintenance', 'rent_amount' => 30000, 'rent_frequency' => 'monthly', 'created_by' => $user->id],
    ]);

    $this->actingAs($user)
        ->get(tenantOverviewUrl($organization, '/overview'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/dashboard')
            ->where('organization.name', 'Acme Estates')
            ->where('organization.currency', 'KES')
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

    $first->units()->create(['name' => 'A1', 'status' => 'occupied', 'rent_amount' => 20000, 'rent_frequency' => 'monthly', 'created_by' => $user->id]);
    $second->units()->createMany([
        ['name' => 'B1', 'status' => 'occupied', 'rent_amount' => 30000, 'rent_frequency' => 'monthly', 'created_by' => $user->id],
        ['name' => 'B2', 'status' => 'vacant', 'rent_amount' => 35000, 'rent_frequency' => 'monthly', 'created_by' => $user->id],
    ]);

    $this->actingAs($user)
        ->get(tenantOverviewUrl($organization, '/overview'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/dashboard')
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

    $delegated->units()->create(['name' => 'A1', 'status' => 'occupied', 'rent_amount' => 20000, 'rent_frequency' => 'monthly', 'created_by' => $owner->id]);
    $other->units()->create(['name' => 'B1', 'status' => 'vacant', 'rent_amount' => 30000, 'rent_frequency' => 'monthly', 'created_by' => $owner->id]);

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
            ->component('organisation/dashboard')
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
            ->component('organisation/dashboard')
            ->where('operations_monthly', fn ($rows) => count($rows) === 28)
            ->where('operations_monthly.0.label', '1')
            ->where('operations_monthly.27.label', '28'));
});

test('the organization overview exposes the organization currency from settings', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOverviewOrganization($user);
    $organization->update(['settings' => array_merge($organization->settings ?? [], ['currency' => 'UGX'])]);
    createOverviewProperty($organization, $user, 'Kampala Courts', 'kampala-courts');

    $this->actingAs($user)
        ->get(tenantOverviewUrl($organization, '/overview'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('organization.currency', 'UGX'));
});

function overviewLease(Organization $organization, User $owner, Property $property, Unit $unit, Person $person, array $overrides = []): Lease
{
    return Lease::create(array_merge([
        'person_id' => $person->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'unit_id' => $unit->id,
        'starts_at' => '2026-08-01',
        'rent_amount' => 10000,
        'rent_frequency' => 'monthly',
        'rent_due_day' => 5,
        'currency' => 'KES',
        'status' => 'active',
    ], $overrides));
}

function overviewPerson(string $firstName): Person
{
    return Person::factory()->create(['first_name' => $firstName, 'last_name' => 'Tenant']);
}

test('the organization overview lists overdue leases with amounts and totals', function () {
    $this->travelTo('2026-11-10');

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOverviewOrganization($owner);
    $property = createOverviewProperty($organization, $owner, 'Sunset Heights', 'sunset-heights');

    $unitA = $property->units()->create(['name' => 'A1', 'status' => 'occupied', 'rent_amount' => 10000, 'rent_frequency' => 'monthly', 'created_by' => $owner->id]);
    $unitB = $property->units()->create(['name' => 'B2', 'status' => 'occupied', 'rent_amount' => 20000, 'rent_frequency' => 'monthly', 'created_by' => $owner->id]);

    // Due on the 5th: Aug, Sep, Oct, Nov = 4 dues each.
    overviewLease($organization, $owner, $property, $unitA, overviewPerson('Jane'));
    overviewLease($organization, $owner, $property, $unitB, overviewPerson('John'), ['rent_amount' => 20000]);

    $this->actingAs($owner)
        ->get(tenantOverviewUrl($organization, '/overview'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/dashboard')
            ->where('overdue_count', 2)
            ->where('overdue_total', '120000.00')
            ->has('overdue_leases', 2)
            // Worst first: John's 80000 ahead of Jane's 40000.
            ->where('overdue_leases.0.occupant', 'John Tenant')
            ->where('overdue_leases.0.outstanding', '80000.00')
            ->where('overdue_leases.0.unit', 'B2')
            ->where('overdue_leases.1.occupant', 'Jane Tenant')
            ->where('overdue_leases.1.outstanding', '40000.00'));
});

test('the organization overview excludes paid-up and ended leases from overdue', function () {
    $this->travelTo('2026-11-10');

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOverviewOrganization($owner);
    $property = createOverviewProperty($organization, $owner, 'Sunset Heights', 'sunset-heights');

    $unitA = $property->units()->create(['name' => 'A1', 'status' => 'occupied', 'rent_amount' => 10000, 'rent_frequency' => 'monthly', 'created_by' => $owner->id]);
    $paidUp = overviewLease($organization, $owner, $property, $unitA, overviewPerson('Paid'));
    $paidUp->payments()->create([
        'organization_id' => $organization->id,
        'amount' => 40000, 'currency' => 'KES', 'status' => 'received', 'paid_on' => '2026-09-01',
    ]);

    $unitB = $property->units()->create(['name' => 'B2', 'status' => 'vacant', 'rent_amount' => 10000, 'rent_frequency' => 'monthly', 'created_by' => $owner->id]);
    overviewLease($organization, $owner, $property, $unitB, overviewPerson('Ended'), [
        'status' => 'ended', 'ends_at' => '2026-10-01',
    ]);

    $this->actingAs($owner)
        ->get(tenantOverviewUrl($organization, '/overview'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/dashboard')
            ->where('overdue_count', 0)
            ->where('overdue_total', '0.00')
            ->has('overdue_leases', 0));
});

test('the organization overview only shows delegated properties overdue leases to staff', function () {
    $this->travelTo('2026-11-10');

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOverviewOrganization($owner);

    $delegated = createOverviewProperty($organization, $owner, 'Delegated Estate', 'delegated-estate');
    $other = createOverviewProperty($organization, $owner, 'Other Estate', 'other-estate');

    $unitA = $delegated->units()->create(['name' => 'A1', 'status' => 'occupied', 'rent_amount' => 10000, 'rent_frequency' => 'monthly', 'created_by' => $owner->id]);
    $unitB = $other->units()->create(['name' => 'B1', 'status' => 'occupied', 'rent_amount' => 20000, 'rent_frequency' => 'monthly', 'created_by' => $owner->id]);
    overviewLease($organization, $owner, $delegated, $unitA, overviewPerson('Jane'));
    overviewLease($organization, $owner, $other, $unitB, overviewPerson('John'));

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
            ->component('organisation/dashboard')
            ->where('overdue_count', 1)
            ->where('overdue_total', '40000.00')
            ->has('overdue_leases', 1)
            ->where('overdue_leases.0.occupant', 'Jane Tenant'));
});

test('a single-property organization pre-selects it for the nav context', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOverviewOrganization($owner);
    $property = createOverviewProperty($organization, $owner, 'Sunset Heights', 'sunset-heights');

    // No property visited yet: the only building is selected so the
    // property-scoped nav (occupants, leases) renders on first paint.
    $this->actingAs($owner)
        ->get(tenantOverviewUrl($organization, '/overview'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('context.property.id', $property->id)
            ->where('context.property.slug', 'sunset-heights'));
});

test('a multi-property organization selects nothing for the nav context', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOverviewOrganization($owner);
    createOverviewProperty($organization, $owner, 'Sunset Heights', 'sunset-heights');
    createOverviewProperty($organization, $owner, 'Umoja Blocks', 'umoja-blocks');

    // Guessing between buildings would mislead: the landlord picks.
    $this->actingAs($owner)
        ->get(tenantOverviewUrl($organization, '/overview'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('context.property', null));
});

test('a staffer with one delegated property gets it as nav context', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOverviewOrganization($owner);
    $property = createOverviewProperty($organization, $owner, 'Sunset Heights', 'sunset-heights');
    createOverviewProperty($organization, $owner, 'Umoja Blocks', 'umoja-blocks');

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
        'delegatable_id' => $property->id,
    ]);

    $this->actingAs($staff)
        ->get(tenantOverviewUrl($organization, '/overview'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('context.property.id', $property->id));
});
