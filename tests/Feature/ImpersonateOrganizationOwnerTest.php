<?php

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Database\Models\ImpersonationToken;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
});

test('guests cannot impersonate an organization owner', function () {
    $organization = Organization::factory()->create();

    $this->post(route('organizations.impersonate', $organization))
        ->assertRedirect(route('login'));
});

test('plain users are blocked from impersonating an organization owner', function () {
    $user = User::factory()->create();

    $organization = Organization::factory()->create();
    $organization->users()->attach($user->id, ['is_owner' => true, 'status' => 'active']);

    $this->actingAs($user)
        ->post(route('organizations.impersonate', $organization))
        ->assertRedirect();
    assertErrorToast();
});

test('the impersonate permission is enforced and not just the admin role', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    // Revoke the fine-grained permission from the admin *role* (revoking it from
    // the user would not help — they would still inherit it via the role). The
    // route must then refuse even though the role check alone would pass.
    $admin->roles()->where('name', 'admin')->first()
        ->revokePermissionTo(Permission::findByName('organization.impersonate'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $organization = Organization::factory()->create();
    $organization->tenant->domains()->create(['domain' => 'acme.malimanager.test']);
    $owner = User::factory()->create();
    $organization->users()->attach($owner->id, ['is_owner' => true, 'status' => 'active']);

    $this->actingAs($admin)
        ->post(route('organizations.impersonate', $organization))
        ->assertRedirect();

    // The important part: no impersonation may have been started.
    expect(app(ImpersonationToken::class)->count())->toBe(0);
});

test('admins can impersonate the organization owner without knowing the member id', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $organization = Organization::factory()->create();
    $organization->tenant->domains()->create(['domain' => 'acme.malimanager.test']);
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $staff = User::factory()->create(['onboarded_at' => now()]);
    $organization->users()->attach($owner->id, ['is_owner' => true, 'status' => 'active']);
    $organization->users()->attach($staff->id, ['is_owner' => false, 'status' => 'active']);

    $response = $this->actingAs($admin)
        ->post(route('organizations.impersonate', $organization));

    $token = app(ImpersonationToken::class)->latest('created_at')->firstOrFail();

    $response->assertRedirect('http://acme.malimanager.test/impersonate/'.$token->token);
    expect($token->user_id)->toBe((string) $owner->id);
});

test('admins cannot impersonate an organization that has no owner', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $organization = Organization::factory()->create();
    $organization->tenant->domains()->create(['domain' => 'acme.malimanager.test']);
    $staff = User::factory()->create();
    $organization->users()->attach($staff->id, ['is_owner' => false, 'status' => 'active']);

    $this->actingAs($admin)
        ->post(route('organizations.impersonate', $organization))
        ->assertRedirect();
    assertErrorToast();
});

test('admins cannot impersonate an organization without a configured domain', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $organization->users()->attach($owner->id, ['is_owner' => true, 'status' => 'active']);

    $this->actingAs($admin)
        ->post(route('organizations.impersonate', $organization))
        ->assertRedirect();
    assertErrorToast();
});

test('the organizations page shares the impersonate permission to the frontend', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('organizations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organizations/index')
            ->where('auth.permissions', fn (Collection $permissions) => $permissions->contains('organization.impersonate')));
});
