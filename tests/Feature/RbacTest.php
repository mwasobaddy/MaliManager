<?php

use App\Enums\SubPermissionKey;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\SubPermission;
use App\Models\User;
use App\Support\DefaultSubRoles;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seeder = new RolesAndPermissionsSeeder;
    $this->seeder->run();

    $this->organization = Organization::factory()->create();
    DefaultSubRoles::createFor($this->organization);
});

test('platform roles are created by the seeder', function () {
    expect(Role::count())->toBe(4)
        ->and(Role::pluck('name'))->toContain('admin', 'organization-owner', 'searcher', 'occupant');
});

test('sub-permission catalog is seeded', function () {
    expect(SubPermission::count())->toBe(count(SubPermissionKey::cases()));
});

test('default sub-roles bundle their expected permissions', function () {
    $caretaker = $this->organization->subRoles()->where('slug', 'caretaker')->first();

    expect($caretaker->subPermissions()->count())->toBe(19)
        // Gates only GET /properties (the picker), which is where the staff
        // landing flow sends a caretaker. Without it they are redirected away.
        ->and($caretaker->hasSubPermissionFor(SubPermissionKey::PropertyManage))->toBeTrue()
        // The property-type catalog, read-only: enough to load the picker on
        // the property form, not enough to change the catalog itself.
        ->and($caretaker->hasSubPermissionFor(SubPermissionKey::PropertyTypeManage))->toBeTrue()
        ->and($caretaker->hasSubPermissionFor(SubPermissionKey::PropertyTypeCreate))->toBeFalse()
        ->and($caretaker->hasSubPermissionFor(SubPermissionKey::PropertyTypeEdit))->toBeFalse()
        ->and($caretaker->hasSubPermissionFor(SubPermissionKey::PropertyTypeDelete))->toBeFalse()
        // Still no property write access: create/edit/delete stay off.
        ->and($caretaker->hasSubPermissionFor(SubPermissionKey::PropertyCreate))->toBeFalse()
        ->and($caretaker->hasSubPermissionFor(SubPermissionKey::PropertyEdit))->toBeFalse()
        ->and($caretaker->hasSubPermissionFor(SubPermissionKey::PropertyDelete))->toBeFalse()
        ->and($caretaker->hasSubPermissionFor(SubPermissionKey::UnitManage))->toBeTrue()
        ->and($caretaker->hasSubPermissionFor(SubPermissionKey::LandParcelManage))->toBeTrue()
        ->and($caretaker->hasSubPermissionFor(SubPermissionKey::LandParcelCreate))->toBeTrue()
        ->and($caretaker->hasSubPermissionFor(SubPermissionKey::LandParcelDelete))->toBeFalse()
        ->and($caretaker->hasSubPermissionFor(SubPermissionKey::StaffManage))->toBeTrue()
        ->and($caretaker->hasSubPermissionFor(SubPermissionKey::StaffDelete))->toBeFalse()
        ->and($caretaker->hasSubPermissionFor(SubPermissionKey::OccupantDelete))->toBeFalse()
        ->and($caretaker->hasSubPermissionFor(SubPermissionKey::AiUse))->toBeTrue();
});

test('syncBundlesFor adds missing bundle permissions without removing customisations', function () {
    $caretaker = $this->organization->subRoles()->where('slug', 'caretaker')->first();

    // Simulate an organization created before a bundle gained a permission.
    $propertyManage = SubPermission::where('key', SubPermissionKey::PropertyManage->value)->first();
    $caretaker->subPermissions()->detach($propertyManage);

    expect(DefaultSubRoles::syncBundlesFor($this->organization))->toBe(1);

    // The new bundle permission is restored.
    expect($caretaker->fresh()->hasSubPermissionFor(SubPermissionKey::PropertyManage))->toBeTrue();

    // Additive only: a permission that is NOT in the bundle and that the owner
    // granted by hand is never detached.
    $custom = SubPermission::where('key', SubPermissionKey::PropertyDelete->value)->first();
    $caretaker->subPermissions()->syncWithoutDetaching([$custom->id]);

    DefaultSubRoles::syncBundlesFor($this->organization);

    expect($caretaker->fresh()->hasSubPermissionFor(SubPermissionKey::PropertyDelete))->toBeTrue();
});

test('syncBundlesFor is idempotent', function () {
    expect(DefaultSubRoles::syncBundlesFor($this->organization))->toBe(0);
});

test('owners bypass sub-permission checks', function () {
    $owner = User::factory()->create();
    $this->organization->users()->attach($owner->id, ['is_owner' => true]);

    expect($owner->hasSubPermission($this->organization, SubPermissionKey::OccupantManage))->toBeTrue();
});

test('members without a sub-role have no access', function () {
    $member = User::factory()->create();
    $this->organization->users()->attach($member->id);

    expect($member->hasSubPermission($this->organization, SubPermissionKey::UnitManage))->toBeFalse();
});

test('members not in the organization have no access', function () {
    $outsider = User::factory()->create();

    expect($outsider->hasSubPermission($this->organization, SubPermissionKey::UnitManage))->toBeFalse();
});

test('delegations are recorded per organization user and asset', function () {
    $staff = User::factory()->create();
    $this->organization->users()->attach($staff->id, [
        'sub_role_id' => $this->organization->subRoles()->where('slug', 'caretaker')->first()->id,
    ]);

    $membership = OrganizationUser::where('organization_id', $this->organization->id)->first();

    DB::table('delegations')->insert([
        'organization_id' => $this->organization->id,
        'organization_user_id' => $membership->id,
        'delegatable_type' => 'App\Models\Property',
        'delegatable_id' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('delegations')->count())->toBe(1);
});
