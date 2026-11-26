<?php

use App\Models\Branch;
use App\Models\LandParcel;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Property;
use App\Models\User;
use App\Services\StaffService;
use App\Services\TenantService;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

function branchOrganization(User $owner, string $name = 'Acme Estates', string $planSlug = 'free'): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $owner,
        name: $name,
        plan: Plan::where('slug', $planSlug)->firstOrFail(),
    );
}

function branchTenantUrl(Organization $organization, string $path = ''): string
{
    $domain = $organization->tenant->domains()->value('domain');

    return "http://{$domain}{$path}";
}

test('organization creation provisions a main branch', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = branchOrganization($owner);

    expect($organization->branches()->count())->toBe(1)
        ->and($organization->defaultBranch()->name)->toBe('Main')
        ->and($organization->defaultBranch()->slug)->toBe('main');
});

test('new properties land in the main branch unless told otherwise', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = branchOrganization($owner, 'Acme Estates', 'growth');

    $property = $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    // Direct creates stay branchless; the store path assigns the default.
    expect($property->branch_id)->toBeNull();

    $this->actingAs($owner)
        ->post(branchTenantUrl($organization, '/properties'), [
            'name' => 'Riverside Court',
            'city' => 'Nairobi',
        ])->assertRedirect();

    $created = Property::where('organization_id', $organization->id)
        ->where('slug', 'riverside-court')
        ->firstOrFail();

    expect($created->branch_id)->toBe($organization->defaultBranch()->id);
});

test('properties can be created into a chosen branch and filtered by it', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = branchOrganization($owner);
    $westlands = $organization->branches()->create(['name' => 'Westlands', 'slug' => 'westlands']);

    $this->actingAs($owner)
        ->post(branchTenantUrl($organization, '/properties'), [
            'name' => 'Westlands Tower',
            'branch_id' => $westlands->id,
        ])->assertRedirect();

    expect(Property::where('organization_id', $organization->id)->where('slug', 'westlands-tower')->firstOrFail()->branch_id)
        ->toBe($westlands->id);

    $this->actingAs($owner)
        ->get(branchTenantUrl($organization, "/properties?branch={$westlands->id}"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/properties/index')
            ->where('properties.0.name', 'Westlands Tower')
            ->where('filters.branch', $westlands->id));
});

test('branch ids from another organization are rejected', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = branchOrganization($owner, 'Org A');
    $other = branchOrganization(User::factory()->create(['onboarded_at' => now()]), 'Org B');
    $foreign = $other->branches()->create(['name' => 'Foreign', 'slug' => 'foreign']);

    $this->actingAs($owner)
        ->post(branchTenantUrl($organization, '/properties'), [
            'name' => 'Sneaky Property',
            'branch_id' => $foreign->id,
        ])->assertSessionHasErrors('branch_id');
});

test('a branch delegation covers every current and future property', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = branchOrganization($owner);
    $branch = $organization->branches()->create(['name' => 'Kileleshwa', 'slug' => 'kileleshwa']);

    $staff = User::factory()->create(['onboarded_at' => now()]);
    $staff->organizations()->attach($organization->id, [
        'is_owner' => false,
        'sub_role_id' => $organization->subRoles()->where('slug', 'caretaker')->firstOrFail()->id,
        'status' => 'active',
    ]);

    $this->actingAs($owner)
        ->put(branchTenantUrl($organization, "/staff/{$staff->id}"), [
            'name' => $staff->name,
            'email' => $staff->email,
            'sub_role_id' => $staff->membershipFor($organization)->sub_role_id,
            'property_ids' => [],
            'branch_ids' => [$branch->id],
        ])->assertRedirect();

    $membership = $staff->membershipFor($organization);
    expect(app(StaffService::class)->delegatedPropertyIds($membership))->toBeEmpty();

    $first = $organization->properties()->create([
        'name' => 'First House',
        'slug' => 'first-house',
        'status' => 'active',
        'branch_id' => $branch->id,
        'created_by' => $owner->id,
    ]);
    $second = $organization->properties()->create([
        'name' => 'Second House',
        'slug' => 'second-house',
        'status' => 'active',
        'branch_id' => $branch->id,
        'created_by' => $owner->id,
    ]);

    $ids = app(StaffService::class)->delegatedPropertyIds($membership);
    expect($ids)->toContain($first->id, $second->id);
});

test('removing a branch delegation revokes the inherited access', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = branchOrganization($owner);
    $branch = $organization->branches()->create(['name' => 'Lavington', 'slug' => 'lavington']);

    $staff = User::factory()->create(['onboarded_at' => now()]);
    $staff->organizations()->attach($organization->id, [
        'is_owner' => false,
        'sub_role_id' => $organization->subRoles()->where('slug', 'caretaker')->firstOrFail()->id,
        'status' => 'active',
    ]);

    $property = $organization->properties()->create([
        'name' => 'Lavington House',
        'slug' => 'lavington-house',
        'status' => 'active',
        'branch_id' => $branch->id,
        'created_by' => $owner->id,
    ]);

    $edit = ['name' => $staff->name, 'email' => $staff->email, 'sub_role_id' => $staff->membershipFor($organization)->sub_role_id];

    $this->actingAs($owner)
        ->put(branchTenantUrl($organization, "/staff/{$staff->id}"), [...$edit, 'branch_ids' => [$branch->id]])
        ->assertRedirect();

    expect(app(StaffService::class)->delegatedPropertyIds($staff->membershipFor($organization)))
        ->toContain($property->id);

    $this->actingAs($owner)
        ->put(branchTenantUrl($organization, "/staff/{$staff->id}"), [...$edit, 'branch_ids' => []])
        ->assertRedirect();

    expect(app(StaffService::class)->delegatedPropertyIds($staff->membershipFor($organization)))->toBeEmpty();
});

test('owners can create branches from the properties page', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = branchOrganization($owner);

    $this->actingAs($owner)
        ->post(branchTenantUrl($organization, '/branches'), ['name' => 'Westlands'])
        ->assertRedirect();

    $branch = Branch::where('organization_id', $organization->id)->where('slug', 'westlands')->firstOrFail();
    expect($branch->name)->toBe('Westlands');
});

test('branch names are unique per organization', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = branchOrganization($owner);

    $this->actingAs($owner)
        ->post(branchTenantUrl($organization, '/branches'), ['name' => 'Main'])
        ->assertSessionHasErrors('name');
});

test('staff cannot create branches', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = branchOrganization($owner);

    $staff = User::factory()->create(['onboarded_at' => now()]);
    $staff->organizations()->attach($organization->id, [
        'is_owner' => false,
        'sub_role_id' => $organization->subRoles()->where('slug', 'landlord')->firstOrFail()->id,
        'status' => 'active',
    ]);

    $this->actingAs($staff)
        ->post(branchTenantUrl($organization, '/branches'), ['name' => 'Sneaky'])
        ->assertRedirect();

    assertErrorToast();
    expect(Branch::where('organization_id', $organization->id)->count())->toBe(1);
});

test('land parcels default to the main branch and accept a chosen one', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = branchOrganization($owner);
    $branch = $organization->branches()->create(['name' => 'Ruiru', 'slug' => 'ruiru']);

    $this->actingAs($owner)
        ->post(branchTenantUrl($organization, '/land-parcels'), [
            'name' => 'Ruiru Plot',
            'zoning' => 'residential',
            'status' => 'active',
            'branch_id' => $branch->id,
        ])->assertRedirect();

    expect(LandParcel::where('organization_id', $organization->id)->firstOrFail()->branch_id)
        ->toBe($branch->id);
});
