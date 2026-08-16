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
    expect(Role::count())->toBe(3)
        ->and(Role::pluck('name'))->toContain('admin', 'organization-owner', 'tenant');
});

test('sub-permission catalog is seeded', function () {
    expect(SubPermission::count())->toBe(count(SubPermissionKey::cases()));
});

test('default sub-roles bundle their expected permissions', function () {
    $caretaker = $this->organization->subRoles()->where('slug', 'caretaker')->first();

    expect($caretaker->subPermissions()->count())->toBe(10)
        ->and($caretaker->hasSubPermissionFor(SubPermissionKey::UnitManage))->toBeTrue()
        ->and($caretaker->hasSubPermissionFor(SubPermissionKey::UserManage))->toBeFalse();
});

test('owners bypass sub-permission checks', function () {
    $owner = User::factory()->create();
    $this->organization->users()->attach($owner->id, ['is_owner' => true]);

    expect($owner->hasSubPermission($this->organization, SubPermissionKey::UserManage))->toBeTrue();
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
