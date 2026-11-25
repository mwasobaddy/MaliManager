<?php

use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Plan;
use App\Models\SubPermission;
use App\Models\SubRole;
use App\Models\User;
use App\Models\UtilityCharge;
use App\Services\StaffService;
use App\Services\TenantService;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

function createStaffOrganization(User $owner, string $name = 'Acme Estates'): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $owner,
        name: $name,
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
}

function staffTenantUrl(Organization $organization, string $path = ''): string
{
    $domain = $organization->tenant->domains()->value('domain');

    return "http://{$domain}{$path}";
}

function addStaffMember(Organization $organization, User $actor, array $attributes = [], array $propertyIds = []): User
{
    return app(StaffService::class)->create($organization, $actor, array_merge([
        'name' => 'Jane Staff',
        'email' => 'staff@acme.test',
        'sub_role_id' => $organization->subRoles()->where('slug', 'caretaker')->first()->id,
    ], $attributes, [
        'property_ids' => $propertyIds,
    ]));
}

test('staff index requires the staff.manage sub-permission', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createStaffOrganization($owner);

    $agent = User::factory()->create(['onboarded_at' => now()]);
    $organization->users()->attach($agent->id, [
        'sub_role_id' => $organization->subRoles()->where('slug', 'agent')->first()->id,
        'status' => 'active',
    ]);

    $this->actingAs($agent)
        ->get(staffTenantUrl($organization, '/staff'))
        ->assertRedirect();
    assertErrorToast();
});

test('staff with the staff.manage sub-permission can view the index', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createStaffOrganization($owner);

    $caretaker = User::factory()->create(['onboarded_at' => now()]);
    $organization->users()->attach($caretaker->id, [
        'sub_role_id' => $organization->subRoles()->where('slug', 'caretaker')->first()->id,
        'status' => 'active',
    ]);

    addStaffMember($organization, $owner, ['name' => 'Sam Staff', 'email' => 'sam@acme.test']);

    $this->actingAs($caretaker)
        ->get(staffTenantUrl($organization, '/staff'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/staff/index')
            ->has('staff', 2)
            ->where('staff.1.name', 'Sam Staff'));
});

test('owners bypass sub-permission checks on the staff index', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createStaffOrganization($owner);

    $this->actingAs($owner)
        ->get(staffTenantUrl($organization, '/staff'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('organisation/staff/index'));
});

test('creating staff requires the staff.create sub-permission', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createStaffOrganization($owner);

    $agent = User::factory()->create(['onboarded_at' => now()]);
    $organization->users()->attach($agent->id, [
        'sub_role_id' => $organization->subRoles()->where('slug', 'agent')->first()->id,
        'status' => 'active',
    ]);

    $this->actingAs($agent)
        ->post(staffTenantUrl($organization, '/staff'), [
            'name' => 'New Staff',
            'email' => 'new@acme.test',
            'sub_role_id' => $organization->subRoles()->where('slug', 'caretaker')->first()->id,
        ])->assertRedirect();
    assertErrorToast();
});

test('owner can add a new staff member with assigned properties', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createStaffOrganization($owner);

    $property = $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $subRoleId = $organization->subRoles()->where('slug', 'caretaker')->first()->id;

    $this->actingAs($owner)
        ->post(staffTenantUrl($organization, '/staff'), [
            'name' => 'Jane Staff',
            'email' => 'jane@acme.test',
            'phone' => '+254700000000',
            'sub_role_id' => $subRoleId,
            'property_ids' => [$property->id],
        ])->assertRedirect(staffTenantUrl($organization, '/staff'));

    $staff = User::where('email', 'jane@acme.test')->first();
    expect($staff)->not->toBeNull()
        ->and($staff->onboarded_at)->not->toBeNull();

    $membership = OrganizationUser::where('organization_id', $organization->id)
        ->where('user_id', $staff->id)
        ->first();
    expect($membership)->not->toBeNull()
        ->and($membership->sub_role_id)->toBe($subRoleId)
        ->and($membership->is_owner)->toBeFalse()
        ->and($membership->status)->toBe('active')
        ->and(app(StaffService::class)->delegatedPropertyIds($membership))->toContain($property->id);
});

test('adding a staff member who already has an account reuses the user', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createStaffOrganization($owner);

    $existing = User::factory()->create(['email' => 'exists@acme.test']);

    $this->actingAs($owner)
        ->post(staffTenantUrl($organization, '/staff'), [
            'name' => 'Renamed Staff',
            'email' => 'exists@acme.test',
            'sub_role_id' => $organization->subRoles()->where('slug', 'caretaker')->first()->id,
        ])->assertRedirect(staffTenantUrl($organization, '/staff'));

    expect(User::where('email', 'exists@acme.test')->count())->toBe(1)
        ->and($existing->refresh()->name)->toBe('Renamed Staff')
        ->and($existing->organizations()->where('organizations.id', $organization->id)->exists())->toBeTrue();
});

test('editing staff requires the staff.edit sub-permission', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createStaffOrganization($owner);

    $staff = addStaffMember($organization, $owner);

    $agent = User::factory()->create(['onboarded_at' => now()]);
    $organization->users()->attach($agent->id, [
        'sub_role_id' => $organization->subRoles()->where('slug', 'agent')->first()->id,
        'status' => 'active',
    ]);

    $this->actingAs($agent)
        ->get(staffTenantUrl($organization, "/staff/{$staff->id}/edit"))
        ->assertRedirect();
    assertErrorToast();
});

test('owner can update staff role and property assignments', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createStaffOrganization($owner);

    $staff = addStaffMember($organization, $owner);

    $property = $organization->properties()->create([
        'name' => 'Ocean View',
        'slug' => 'ocean-view',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $subRoleId = $organization->subRoles()->where('slug', 'agent')->first()->id;

    $this->actingAs($owner)
        ->put(staffTenantUrl($organization, "/staff/{$staff->id}"), [
            'name' => 'Updated Staff',
            'email' => $staff->email,
            'sub_role_id' => $subRoleId,
            'property_ids' => [$property->id],
        ])->assertRedirect(staffTenantUrl($organization, '/staff'));

    $membership = OrganizationUser::where('organization_id', $organization->id)
        ->where('user_id', $staff->id)
        ->first();
    expect($membership->sub_role_id)->toBe($subRoleId)
        ->and($staff->refresh()->name)->toBe('Updated Staff')
        ->and(app(StaffService::class)->delegatedPropertyIds($membership))->toContain($property->id);
});

test('removing staff requires the staff.delete sub-permission', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createStaffOrganization($owner);

    $staff = addStaffMember($organization, $owner);

    $caretaker = User::factory()->create(['onboarded_at' => now(), 'password' => 'secret-pass']);
    $organization->users()->attach($caretaker->id, [
        'sub_role_id' => $organization->subRoles()->where('slug', 'caretaker')->first()->id,
        'status' => 'active',
    ]);

    $this->actingAs($caretaker)
        ->delete(staffTenantUrl($organization, "/staff/{$staff->id}"), [
            'password' => 'secret-pass',
        ])->assertRedirect();
    assertErrorToast();
});

test('owner can remove a staff member with their current password', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createStaffOrganization($owner);

    $staff = addStaffMember($organization, $owner);

    $this->actingAs($owner)
        ->delete(staffTenantUrl($organization, "/staff/{$staff->id}"), [
            'password' => 'password',
        ])->assertRedirect(staffTenantUrl($organization, '/staff'));

    expect(OrganizationUser::where('organization_id', $organization->id)
        ->where('user_id', $staff->id)->exists())->toBeFalse()
        ->and($staff->refresh()->trashed())->toBeTrue();
});

test('removing staff with the wrong password is rejected', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createStaffOrganization($owner);

    $staff = addStaffMember($organization, $owner);

    $this->actingAs($owner)
        ->delete(staffTenantUrl($organization, "/staff/{$staff->id}"), [
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('password')
        ->assertRedirect();

    expect(OrganizationUser::where('organization_id', $organization->id)
        ->where('user_id', $staff->id)->exists())->toBeTrue()
        ->and($staff->refresh()->trashed())->toBeFalse();
});

test('staff with no assigned properties lands on the picker with a toast', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createStaffOrganization($owner);
    $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $staff = addStaffMember($organization, $owner);

    $this->actingAs($staff)
        ->get(route('onboarding.show'))
        ->assertRedirect(route('guest.home'));
});

test('staff on the central domain lands on the guest home', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createStaffOrganization($owner);

    $property = $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $staff = addStaffMember($organization, $owner, ['email' => 'single@acme.test'], [$property->id]);

    $this->actingAs($staff)
        ->get(route('onboarding.show'))
        ->assertRedirect(route('guest.home'));
});

test('staff with several assigned properties lands on the guest home from the central domain', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createStaffOrganization($owner);

    $propertyIds = [];
    foreach (['sunset-heights', 'ocean-view'] as $slug) {
        $propertyIds[] = $organization->properties()->create([
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'status' => 'active',
            'created_by' => $owner->id,
        ])->id;
    }

    $staff = addStaffMember($organization, $owner, ['email' => 'multi@acme.test'], $propertyIds);

    $this->actingAs($staff)
        ->get(route('onboarding.show'))
        ->assertRedirect(route('guest.home'));
});

test('staff property picker only lists their assigned properties', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createStaffOrganization($owner);

    $assigned = $organization->properties()->create([
        'name' => 'Assigned Heights',
        'slug' => 'assigned-heights',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);
    $organization->properties()->create([
        'name' => 'Hidden Estate',
        'slug' => 'hidden-estate',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $staff = addStaffMember($organization, $owner, ['email' => 'picker@acme.test'], [$assigned->id]);

    $this->actingAs($staff)
        ->get(staffTenantUrl($organization, '/properties'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/properties/index')
            ->has('properties', 1)
            ->where('properties.0.slug', 'assigned-heights'));
});

test('staff cannot open a property dashboard they are not delegated to', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createStaffOrganization($owner);

    $organization->properties()->create([
        'name' => 'Restricted',
        'slug' => 'restricted',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $staff = addStaffMember($organization, $owner);

    $this->actingAs($staff)
        ->get(staffTenantUrl($organization, '/restricted/dashboard'))
        ->assertRedirect();
    assertErrorToast();
});

test('staff create and edit are overlays addressed by url', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createStaffOrganization($owner);
    $staff = addStaffMember($organization, $owner);

    $this->actingAs($owner)
        ->get(staffTenantUrl($organization, '/staff/create'))
        ->assertRedirect(staffTenantUrl($organization, '/staff?create=1'));

    $this->actingAs($owner)
        ->get(staffTenantUrl($organization, "/staff/{$staff->id}/edit"))
        ->assertRedirect(staffTenantUrl($organization, "/staff?mode=edit&staff={$staff->id}"));

    $this->actingAs($owner)
        ->get(staffTenantUrl($organization, '/staff?create=1'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('organisation/staff/index'));

    $this->actingAs($owner)
        ->get(
            staffTenantUrl($organization, "/staff?mode=edit&staff={$staff->id}"),
            [
                'X-Inertia-Partial-Component' => 'organisation/staff/index',
                'X-Inertia-Partial-Data' => 'sub_roles,properties,focusedStaff',
            ],
        )
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('sub_roles')
            ->has('properties')
            ->where('focusedStaff.id', $staff->id));
});

test('owner can create a sub-role with permissions', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createStaffOrganization($owner);

    $this->actingAs($owner)
        ->post(staffTenantUrl($organization, '/roles'), [
            'name' => 'Plumber',
            'description' => 'Fixes leaks, nothing else.',
            'permissions' => ['maintenance.manage', 'maintenance.create'],
        ])
        ->assertRedirect();

    $role = SubRole::where('organization_id', $organization->id)->where('slug', 'plumber')->firstOrFail();

    expect($role->description)->toBe('Fixes leaks, nothing else.')
        ->and($role->subPermissions()->pluck('key')->sort()->values()->all())
        ->toBe(['maintenance.create', 'maintenance.manage']);
});

test('built-in bundle names are reserved for sub-roles', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createStaffOrganization($owner);

    $this->actingAs($owner)
        ->from(staffTenantUrl($organization, '/roles'))
        ->post(staffTenantUrl($organization, '/roles'), [
            'name' => 'Caretaker!',
            'permissions' => [],
        ])
        ->assertRedirect();
    assertErrorToast();

    expect(SubRole::where('organization_id', $organization->id)->where('slug', 'caretaker!')->exists())->toBeFalse()
        ->and(SubRole::where('organization_id', $organization->id)->count())->toBe(3);
});

test('the landlord bundle is locked against edits', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createStaffOrganization($owner);
    $landlord = $organization->subRoles()->where('slug', 'landlord')->firstOrFail();

    $this->actingAs($owner)
        ->from(staffTenantUrl($organization, '/roles'))
        ->put(staffTenantUrl($organization, "/roles/{$landlord->id}"), [
            'name' => 'Landlord',
            'permissions' => [],
        ])
        ->assertRedirect();
    assertErrorToast();

    expect($landlord->refresh()->name)->toBe('Landlord');
});

test('deleting a sub-role with holders is rejected', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createStaffOrganization($owner);
    $caretaker = $organization->subRoles()->where('slug', 'caretaker')->firstOrFail();

    addStaffMember($organization, $owner);

    $this->actingAs($owner)
        ->from(staffTenantUrl($organization, '/roles'))
        ->delete(staffTenantUrl($organization, "/roles/{$caretaker->id}"), [
            'password' => 'password',
        ])
        ->assertRedirect();
    assertErrorToast();

    expect(SubRole::find($caretaker->id))->not->toBeNull()
        ->and(SubRole::withTrashed()->find($caretaker->id))->not->toBeNull();
});

test('staff.manage without role.manage cannot manage sub-roles', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createStaffOrganization($owner);

    $role = SubRole::create([
        'organization_id' => $organization->id,
        'name' => 'HR',
        'slug' => 'hr',
        'created_by' => $owner->id,
    ]);
    $role->subPermissions()->attach(
        SubPermission::whereIn('key', ['staff.manage', 'staff.create', 'staff.edit'])->pluck('id')
    );

    $staff = User::factory()->create(['onboarded_at' => now()]);
    $organization->users()->attach($staff->id, ['sub_role_id' => $role->id, 'status' => 'active']);

    $this->actingAs($staff)
        ->get(staffTenantUrl($organization, '/roles'))
        ->assertRedirect();
    assertErrorToast();

    expect(SubRole::where('organization_id', $organization->id)->count())->toBe(4);
});

test('an empty custom sub-role can be deleted', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createStaffOrganization($owner);

    $role = SubRole::create([
        'organization_id' => $organization->id,
        'name' => 'Plumber',
        'slug' => 'plumber',
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->delete(staffTenantUrl($organization, "/roles/{$role->id}"), [
            'password' => 'password',
        ])
        ->assertRedirect();

    expect(SubRole::find($role->id))->toBeNull()
        ->and(SubRole::withTrashed()->find($role->id))->not->toBeNull();
});

test('staff without staff.manage cannot open the roles page', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createStaffOrganization($owner);

    $agent = User::factory()->create(['onboarded_at' => now()]);
    $organization->users()->attach($agent->id, [
        'sub_role_id' => $organization->subRoles()->where('slug', 'agent')->first()->id,
        'status' => 'active',
    ]);

    $this->actingAs($agent)
        ->get(staffTenantUrl($organization, '/roles'))
        ->assertRedirect();
    assertErrorToast();
});

test('granting a permission to a bundle takes effect on next request', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createStaffOrganization($owner);
    $property = $organization->properties()->create([
        'name' => 'Sunset Heights', 'slug' => 'sunset-heights', 'status' => 'active', 'created_by' => $owner->id,
    ]);
    $unit = $property->units()->create([
        'name' => 'A1', 'status' => 'vacant', 'created_by' => $owner->id,
    ]);

    $caretaker = User::factory()->create(['onboarded_at' => now()]);
    $organization->users()->attach($caretaker->id, [
        'sub_role_id' => $organization->subRoles()->where('slug', 'caretaker')->first()->id,
        'status' => 'active',
    ]);

    // Before the grant: the caretaker records readings but cannot price.
    $this->actingAs($caretaker)
        ->post(staffTenantUrl($organization, '/utilities/charges'), [
            'property_id' => $property->id,
            'name' => 'Water',
            'kind' => 'metered',
            'utility' => 'water',
            'rate' => '50',
            'unit_ids' => [$unit->id],
        ])
        ->assertRedirect();
    assertErrorToast();
    expect(UtilityCharge::count())->toBe(0);

    // The landlord grants utility.manage to the caretaker bundle.
    $bundle = $organization->subRoles()->where('slug', 'caretaker')->firstOrFail();

    $this->actingAs($owner)
        ->put(staffTenantUrl($organization, "/roles/{$bundle->id}"), [
            'name' => 'Caretaker',
            'permissions' => ['unit.manage', 'utility.manage'],
        ])
        ->assertRedirect();

    // After the grant: the same caretaker prices the charge.
    $this->actingAs($caretaker)
        ->post(staffTenantUrl($organization, '/utilities/charges'), [
            'property_id' => $property->id,
            'name' => 'Water',
            'kind' => 'metered',
            'utility' => 'water',
            'rate' => '50',
            'unit_ids' => [$unit->id],
        ])
        ->assertRedirect();

    expect(UtilityCharge::count())->toBe(1);
});

test('staff without staff.manage cannot manage sub-roles', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createStaffOrganization($owner);
    $agent = User::factory()->create(['onboarded_at' => now()]);
    $organization->users()->attach($agent->id, [
        'sub_role_id' => $organization->subRoles()->where('slug', 'agent')->first()->id,
        'status' => 'active',
    ]);

    $this->actingAs($agent)
        ->post(staffTenantUrl($organization, '/roles'), [
            'name' => 'Sneaky',
            'permissions' => [],
        ])
        ->assertRedirect();
    assertErrorToast();

    expect(SubRole::where('organization_id', $organization->id)->count())->toBe(3);
});

test('the roles index searches, filters and paginates', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createStaffOrganization($owner);

    $this->actingAs($owner)
        ->get(staffTenantUrl($organization, '/roles?search=caretaker'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/staff/roles')
            ->where('roles.total', 1)
            ->where('roles.data.0.slug', 'caretaker'));

    $this->actingAs($owner)
        ->get(staffTenantUrl($organization, '/roles?type=custom'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('roles.total', 0));

    $this->actingAs($owner)
        ->post(staffTenantUrl($organization, '/roles'), [
            'name' => 'Plumber',
            'permissions' => ['maintenance.manage'],
        ])
        ->assertRedirect();

    $this->actingAs($owner)
        ->get(staffTenantUrl($organization, '/roles?type=custom'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('roles.total', 1)
            ->where('roles.data.0.slug', 'plumber'));
});
