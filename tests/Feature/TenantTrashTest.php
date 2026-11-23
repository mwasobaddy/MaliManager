<?php

use App\Models\Expense;
use App\Models\Inspection;
use App\Models\LandParcel;
use App\Models\LandParcelSection;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Occupant;
use App\Models\Organization;
use App\Models\Payment;
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

function trashProperty(User $owner, string $slug = 'trash-towers'): Property
{
    $organization = app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Trash Towers',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );

    return $organization->properties()->create([
        'name' => 'Trash Towers Residences',
        'slug' => $slug,
        'status' => 'active',
        'created_by' => $owner->id,
    ]);
}

function trashUnit(Property $property, User $creator, array $overrides = []): Unit
{
    return $property->units()->create(array_merge([
        'name' => 'T1',
        'status' => 'vacant',
        'rent_amount' => 25000,
        'rent_frequency' => 'monthly',
        'created_by' => $creator->id,
    ], $overrides));
}

function trashStaff(User $owner, Organization $organization, array $keys): User
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

function trashUrl(Organization $organization, string $path = ''): string
{
    $domain = $organization->tenant->domains()->value('domain');

    return "http://{$domain}{$path}";
}

test('trash index renders with module counts', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = trashProperty($owner);
    $unit = trashUnit($property, $owner);
    $unit->delete();

    $this->actingAs($owner)
        ->get(trashUrl($property->organization, '/trash'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/trash/index')
            ->where('activeModule', 'units')
            ->has('items.data', 1),
        );
});

test('an owner can restore a trashed unit', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = trashProperty($owner);
    $unit = trashUnit($property, $owner);
    $unit->delete();

    $this->actingAs($owner)
        ->post(trashUrl($property->organization, '/trash/restore'), [
            'module' => 'units',
            'ids' => [$unit->id],
            'password' => 'password',
        ])
        ->assertRedirect();

    expect($unit->fresh()->trashed())->toBeFalse();
});

test('restore refuses a wrong password', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = trashProperty($owner);
    $unit = trashUnit($property, $owner);
    $unit->delete();

    $this->actingAs($owner)
        ->post(trashUrl($property->organization, '/trash/restore'), [
            'module' => 'units',
            'ids' => [$unit->id],
            'password' => 'wrong-password',
        ])
        ->assertSessionHasErrors('password');

    expect($unit->fresh()->trashed())->toBeTrue();
});

test('an owner can permanently delete from trash', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = trashProperty($owner);
    $unit = trashUnit($property, $owner);
    $unit->delete();

    $this->actingAs($owner)
        ->delete(trashUrl($property->organization, '/trash/force'), [
            'module' => 'units',
            'ids' => [$unit->id],
            'password' => 'password',
        ])
        ->assertRedirect();

    expect(Unit::withTrashed()->find($unit->id))->toBeNull();
});

test('staff only see rows they created', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = trashProperty($owner);
    $organization = $property->organization;

    $ownersUnit = trashUnit($property, $owner, ['name' => 'O1']);
    $ownersUnit->delete();

    $staff = trashStaff($owner, $organization, ['unit.manage', 'unit.delete']);
    $staffUnit = trashUnit($property, $staff, ['name' => 'S1']);
    $staffUnit->delete();

    $response = $this->actingAs($staff)
        ->get(trashUrl($organization, '/trash'))
        ->assertOk();

    $items = $response->original->getData()['page']['props']['items']['data'] ?? null;

    // Staff trash lists only their own row, never the owner's.
    expect(collect($items)->pluck('id')->all())->toBe([$staffUnit->id]);
});

test('staff without the delete gate cannot restore', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = trashProperty($owner);
    $unit = trashUnit($property, $owner);
    $unit->delete();

    $staff = trashStaff($owner, $property->organization, ['unit.manage']);

    $this->actingAs($staff)
        ->post(trashUrl($property->organization, '/trash/restore'), [
            'module' => 'units',
            'ids' => [$unit->id],
            'password' => 'password',
        ])
        ->assertRedirect();

    expect($unit->fresh()->trashed())->toBeTrue();
});

test('bulk soft-delete skips occupied units and deletes the rest', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = trashProperty($owner);
    $vacant = trashUnit($property, $owner, ['name' => 'V1']);

    $occupiedUnit = trashUnit($property, $owner, ['name' => 'O1', 'status' => 'occupied']);
    $occupant = Occupant::factory()->create(['organization_id' => $property->organization_id]);
    Lease::factory()->create([
        'organization_id' => $property->organization_id,
        'property_id' => $property->id,
        'unit_id' => $occupiedUnit->id,
        'person_id' => $occupant->person_id,
        'status' => 'active',
        'starts_at' => now()->subMonth()->toDateString(),
        'ends_at' => now()->addMonths(4)->toDateString(),
        'rent_amount' => 25000,
        'rent_due_day' => 5,
    ]);

    $this->actingAs($owner)
        ->delete(trashUrl($property->organization, '/trash/bulk'), [
            'module' => 'units',
            'ids' => [$vacant->id, $occupiedUnit->id],
            'password' => 'password',
        ])
        ->assertRedirect();

    expect($vacant->fresh()->trashed())->toBeTrue()
        ->and($occupiedUnit->fresh()->trashed())->toBeFalse();
});

test('bulk soft-delete covers every trash module', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = trashProperty($owner);
    $organization = $property->organization;

    $payment = Payment::factory()->create(['organization_id' => $organization->id]);
    $expense = Expense::create([
        'organization_id' => $organization->id,
        'expenseable_type' => Property::class,
        'expenseable_id' => $property->id,
        'category' => 'maintenance',
        'amount' => 1000,
        'spent_on' => '2026-08-01',
        'created_by' => $owner->id,
    ]);
    $inspectionUnit = trashUnit($property, $owner, ['name' => 'IU1']);
    $inspection = Inspection::create([
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'unit_id' => $inspectionUnit->id,
        'title' => 'Bulk walkthrough',
        'inspection_date' => '2026-08-26',
        'created_by' => $owner->id,
    ]);
    $maintenance = MaintenanceRequest::create([
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'raised_by' => $owner->id,
        'title' => 'Bulk gate',
        'description' => 'Gate motor is dead.',
        'status' => 'opened',
        'priority' => 'high',
    ]);
    $occupant = Occupant::factory()->create(['organization_id' => $organization->id]);
    $parcel = LandParcel::factory()->create([
        'organization_id' => $organization->id,
        'created_by' => $owner->id,
    ]);
    $section = LandParcelSection::factory()->create([
        'land_parcel_id' => $parcel->id,
        'created_by' => $owner->id,
    ]);

    $cases = [
        'payments' => [$payment->id],
        'expenses' => [$expense->id],
        'inspections' => [$inspection->id],
        'maintenance' => [$maintenance->id],
        'occupants' => [$occupant->id],
        'land-parcels' => [$parcel->id],
        'land-parcel-sections' => [$section->id],
    ];

    foreach ($cases as $module => $ids) {
        $this->actingAs($owner)
            ->delete(trashUrl($organization, '/trash/bulk'), [
                'module' => $module,
                'ids' => $ids,
                'password' => 'password',
            ])
            ->assertRedirect();
    }

    expect($payment->fresh()->trashed())->toBeTrue()
        ->and($expense->fresh()->trashed())->toBeTrue()
        ->and($inspection->fresh()->trashed())->toBeTrue()
        ->and($maintenance->fresh()->trashed())->toBeTrue()
        ->and($occupant->fresh()->trashed())->toBeTrue()
        ->and($parcel->fresh()->trashed())->toBeTrue()
        ->and($section->fresh()->trashed())->toBeTrue();
});

test('bulk soft-delete refuses rows outside the organization', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = trashProperty($owner);

    $other = User::factory()->create(['onboarded_at' => now()]);
    $otherProperty = trashProperty($other, 'other-towers');
    $foreign = trashUnit($otherProperty, $other, ['name' => 'F1']);

    $this->actingAs($owner)
        ->delete(trashUrl($property->organization, '/trash/bulk'), [
            'module' => 'units',
            'ids' => [$foreign->id],
            'password' => 'password',
        ])
        ->assertRedirect();

    expect($foreign->fresh()->trashed())->toBeFalse();
});

test('empty trash removes every trashed row in the module', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = trashProperty($owner);
    $first = trashUnit($property, $owner, ['name' => 'E1']);
    $second = trashUnit($property, $owner, ['name' => 'E2']);
    $first->delete();
    $second->delete();

    $this->actingAs($owner)
        ->delete(trashUrl($property->organization, '/trash/empty'), [
            'module' => 'units',
            'password' => 'password',
        ])
        ->assertRedirect();

    expect(Unit::onlyTrashed()->whereIn('id', [$first->id, $second->id])->count())->toBe(0);
});
