<?php

use App\Models\Organization;
use App\Models\Plan;
use App\Models\Property;
use App\Models\SubPermission;
use App\Models\SubRole;
use App\Models\Unit;
use App\Models\User;
use App\Services\TenantService;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

function tierProperty(User $owner, string $slug = 'tier-towers'): Property
{
    $organization = app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Tier Towers',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );

    return $organization->properties()->create([
        'name' => 'Tier Towers Residences',
        'slug' => $slug,
        'status' => 'active',
        'created_by' => $owner->id,
    ]);
}

function tierStaff(User $owner, Organization $organization, array $keys): User
{
    $staff = User::factory()->create(['onboarded_at' => now()]);
    $role = SubRole::factory()->create([
        'organization_id' => $organization->id,
        'created_by' => $owner->id,
    ]);
    $role->subPermissions()->attach(
        SubPermission::whereIn('key', $keys)->pluck('id')->all(),
    );
    $organization->users()->attach($staff->id, ['sub_role_id' => $role->id, 'status' => 'active']);

    return $staff;
}

function tierUrl(Organization $organization, string $path): string
{
    return 'http://'.$organization->tenant->domains()->value('domain').$path;
}

test('a staffer cannot single-delete a unit they did not create', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = tierProperty($owner);
    $unit = $property->units()->create([
        'name' => 'T1',
        'status' => 'vacant',
        'rent_amount' => 25000,
        'rent_frequency' => 'monthly',
        'created_by' => $owner->id,
    ]);

    $staff = tierStaff($owner, $property->organization, ['unit.manage', 'unit.delete']);

    $this->actingAs($staff)
        ->delete(tierUrl($property->organization, "/units/{$unit->id}"), ['password' => 'password'])
        ->assertRedirect();

    expect($unit->fresh()->trashed())->toBeFalse();
});

test('a staffer can single-delete a unit they created', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = tierProperty($owner);
    $staff = tierStaff($owner, $property->organization, ['unit.manage', 'unit.create', 'unit.delete']);

    $unit = $property->units()->create([
        'name' => 'S1',
        'status' => 'vacant',
        'rent_amount' => 25000,
        'rent_frequency' => 'monthly',
        'created_by' => $staff->id,
    ]);

    $this->actingAs($staff)
        ->delete(tierUrl($property->organization, "/units/{$unit->id}"), ['password' => 'password'])
        ->assertRedirect();

    expect($unit->fresh()->trashed())->toBeTrue();
});

test('a non-creator without platform admin cannot delete a plan', function () {
    $creator = User::factory()->create(['email_verified_at' => now()]);
    $creator->givePermissionTo('plan.manage');
    $plan = Plan::factory()->create(['slug' => 'tier-plan', 'created_by' => $creator->id]);

    $other = User::factory()->create(['email_verified_at' => now()]);
    $other->givePermissionTo('plan.manage', 'plan.delete');

    $this->actingAs($other)
        ->delete(route('platform.plans.destroy', $plan), ['password' => 'password'])
        ->assertRedirect();

    expect($plan->fresh()->trashed())->toBeFalse();
});

test('a platform admin can delete a plan they did not create', function () {
    $plan = Plan::factory()->create(['slug' => 'admin-plan', 'created_by' => User::factory()->create()->id]);

    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->delete(route('platform.plans.destroy', $plan), ['password' => 'password'])
        ->assertRedirect(route('platform.plans.index'));

    expect($plan->fresh()->trashed())->toBeTrue();
});

test('staff cannot remove a user who is not a member of the organization', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = tierProperty($owner);
    $organization = $property->organization;

    $staff = tierStaff($owner, $organization, ['staff.manage', 'staff.delete']);
    $outsider = User::factory()->create(['onboarded_at' => now()]);

    $this->actingAs($staff)
        ->delete(tierUrl($organization, "/staff/{$outsider->id}"), ['password' => 'password'])
        ->assertRedirect();

    expect($outsider->fresh()->trashed())->toBeFalse();
});
