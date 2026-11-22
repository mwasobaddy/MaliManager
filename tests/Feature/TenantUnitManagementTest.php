<?php

use App\Models\Lease;
use App\Models\Occupant;
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

function makeUnitProperty(User $owner, string $slug = 'unit-towers'): Property
{
    $organization = app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Unit Towers',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );

    return $organization->properties()->create([
        'name' => 'Unit Towers Residences',
        'slug' => $slug,
        'status' => 'active',
        'created_by' => $owner->id,
    ]);
}

function makeUnit(Property $property, User $owner, array $overrides = []): Unit
{
    return $property->units()->create(array_merge([
        'name' => 'A1',
        'status' => 'vacant',
        'monthly_rent' => 25000,
        'created_by' => $owner->id,
    ], $overrides));
}

function staffWith(User $owner, Organization $organization, array $keys): User
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

function occupyUnit(Property $property, Unit $unit, User $owner): Lease
{
    $occupant = Occupant::factory()->create(['organization_id' => $property->organization_id]);

    return Lease::factory()->create([
        'organization_id' => $property->organization_id,
        'property_id' => $property->id,
        'unit_id' => $unit->id,
        'person_id' => $occupant->person_id,
        'status' => 'active',
        'starts_at' => now()->subMonthsNoOverflow(2)->toDateString(),
        'ends_at' => now()->addMonthsNoOverflow(4)->toDateString(),
        'rent_amount' => 25000,
        'rent_due_day' => 5,
    ]);
}

test('an owner can update a unit', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = makeUnitProperty($owner);
    $unit = makeUnit($property, $owner);

    $this->actingAs($owner)
        ->put(tenantUrl($property->organization, "/units/{$unit->id}"), [
            'name' => 'A1-Renamed',
            'type' => '2 Bedroom',
            'monthly_rent' => 30000,
        ])
        ->assertRedirect();

    expect($unit->fresh())
        ->name->toBe('A1-Renamed')
        ->type->toBe('2 Bedroom')
        ->monthly_rent->toBe('30000.00');
});

test('unit update validates its fields', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = makeUnitProperty($owner);
    $unit = makeUnit($property, $owner);

    $this->actingAs($owner)
        ->put(tenantUrl($property->organization, "/units/{$unit->id}"), [
            'name' => '',
            'monthly_rent' => -5,
        ])
        ->assertSessionHasErrors(['name', 'monthly_rent']);
});

test('staff with unit.edit can update, others get 403', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = makeUnitProperty($owner);
    $unit = makeUnit($property, $owner);

    $editor = staffWith($owner, $property->organization, ['unit.manage', 'unit.edit']);

    $this->actingAs($editor)
        ->put(tenantUrl($property->organization, "/units/{$unit->id}"), [
            'name' => 'A1-Edited',
        ])
        ->assertRedirect();

    expect($unit->fresh()->name)->toBe('A1-Edited');

    $viewer = staffWith($owner, $property->organization, ['unit.manage']);

    $this->actingAs($viewer)
        ->put(tenantUrl($property->organization, "/units/{$unit->id}"), [
            'name' => 'A1-Hacked',
        ])
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data', fn (array $flash) => str_contains(
            $flash['toast']['message'] ?? '',
            'do not have permission',
        ));

    expect($unit->fresh()->name)->toBe('A1-Edited');
});

test('an owner can delete a vacant unit', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = makeUnitProperty($owner);
    $unit = makeUnit($property, $owner);

    $this->actingAs($owner)
        ->delete(tenantUrl($property->organization, "/units/{$unit->id}"), [
            'password' => 'password',
        ])
        ->assertRedirect();

    expect($unit->fresh()->trashed())->toBeTrue();
});

test('an occupied unit cannot be deleted', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = makeUnitProperty($owner);
    $unit = makeUnit($property, $owner, ['status' => 'occupied']);
    occupyUnit($property, $unit, $owner);

    $this->actingAs($owner)
        ->delete(tenantUrl($property->organization, "/units/{$unit->id}"), [
            'password' => 'password',
        ])
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data', fn (array $flash) => str_contains(
            $flash['toast']['message'] ?? '',
            'occupied unit cannot be deleted',
        ));

    expect($unit->fresh()->trashed())->toBeFalse();
});

test('staff without unit.delete gets 403 on destroy', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = makeUnitProperty($owner);
    $unit = makeUnit($property, $owner);
    $staff = staffWith($owner, $property->organization, ['unit.manage', 'unit.edit']);

    $this->actingAs($staff)
        ->delete(tenantUrl($property->organization, "/units/{$unit->id}"), [
            'password' => 'password',
        ])
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data', fn (array $flash) => str_contains(
            $flash['toast']['message'] ?? '',
            'do not have permission',
        ));

    expect($unit->fresh()->trashed())->toBeFalse();
});

test('a unit from another organization answers 404', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = makeUnitProperty($owner);
    $unit = makeUnit($property, $owner);

    $other = User::factory()->create(['onboarded_at' => now()]);
    $otherProperty = makeUnitProperty($other, 'other-towers');

    $this->actingAs($other)
        ->put(tenantUrl($otherProperty->organization, "/units/{$unit->id}"), [
            'name' => 'A1-Hacked',
        ])
        ->assertNotFound();
});
