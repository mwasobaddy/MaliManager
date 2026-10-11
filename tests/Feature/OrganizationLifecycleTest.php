<?php

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Services\TenantService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Stancl\Tenancy\Database\Models\ImpersonationToken;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
});

function orgWithOwner(string $name = 'Acme'): array
{
    $plan = Plan::factory()->create();
    $owner = User::factory()->create(['onboarded_at' => now()]);
    // createOrganization already provisions <slug>.malimanager.test.
    $organization = app(TenantService::class)->createOrganization($owner, $name, $plan);

    return [$organization, $owner];
}

it('suspends an organization through the status endpoint', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    [$organization] = orgWithOwner();

    $this->actingAs($admin)
        ->patch(route('organizations.status', $organization), ['status' => 'suspended'])
        ->assertRedirect();

    expect($organization->refresh()->status)->toBe(OrganizationStatus::Suspended);
});

it('reactivates a suspended organization', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    [$organization] = orgWithOwner();
    $organization->forceFill(['status' => OrganizationStatus::Suspended])->save();

    $this->actingAs($admin)
        ->patch(route('organizations.status', $organization), ['status' => 'active'])
        ->assertRedirect();

    expect($organization->refresh()->status)->toBe(OrganizationStatus::Active);
});

it('rejects an unknown status value', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    [$organization] = orgWithOwner();

    $this->actingAs($admin)
        ->patch(route('organizations.status', $organization), ['status' => 'banished'])
        ->assertSessionHasErrors('status');

    expect($organization->refresh()->status)->toBe(OrganizationStatus::Active);
});

it('forbids users without the edit permission from changing status', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(
        Permission::findByName('organization.manage')
    );
    [$organization] = orgWithOwner();

    $this->actingAs($user)
        ->patch(route('organizations.status', $organization), ['status' => 'suspended'])
        ->assertRedirect();
    assertErrorToast();

    expect($organization->refresh()->status)->toBe(OrganizationStatus::Active);
});

it('drops member sessions when an organization is suspended', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    [$organization, $owner] = orgWithOwner();

    DB::table('sessions')->insert([
        'id' => 'test-session',
        'user_id' => $owner->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'pest',
        'payload' => 'x',
        'last_activity' => now(),
    ]);

    expect(DB::table('sessions')->where('user_id', $owner->id)->count())->toBe(1);

    $this->actingAs($admin)
        ->patch(route('organizations.status', $organization), ['status' => 'suspended'])
        ->assertRedirect();

    expect(DB::table('sessions')->where('user_id', $owner->id)->count())->toBe(0);
});

it('blocks a suspended organization from loading its tenant application', function () {
    // Status is set BEFORE any request: stancl caches the resolved Tenant (and
    // its loaded organization relation) per PHP process, so flipping the flag
    // between two simulated requests in one test would read a stale model.
    // Each real request resolves tenancy fresh, so this is a test artefact.
    $organization = Organization::factory()->create();
    $organization->tenant->domains()->create(['domain' => 'blocked.malimanager.test']);
    $organization->forceFill(['status' => OrganizationStatus::Suspended])->save();

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization->users()->attach($owner->id, ['is_owner' => true, 'status' => 'active']);

    // /land-parcels sits in the ['auth', 'organization.active'] group, so it is
    // not also redirected by the has-property middleware.
    $this->actingAs($owner)
        ->get('http://blocked.malimanager.test/land-parcels')
        ->assertRedirect();
    assertErrorToast();
});

it('does not block an active organization', function () {
    $organization = Organization::factory()->create();
    $organization->tenant->domains()->create(['domain' => 'allowed.malimanager.test']);

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization->users()->attach($owner->id, ['is_owner' => true, 'status' => 'active']);

    $this->actingAs($owner)
        ->get('http://allowed.malimanager.test/land-parcels')
        ->assertOk();
});

it('blocks the tenant dashboard of a suspended organization', function () {
    $organization = Organization::factory()->create();
    $organization->tenant->domains()->create(['domain' => 'dash.malimanager.test']);
    $organization->forceFill(['status' => OrganizationStatus::Suspended])->save();

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization->users()->attach($owner->id, ['is_owner' => true, 'status' => 'active']);

    $this->actingAs($owner)
        ->get('http://dash.malimanager.test/overview')
        ->assertRedirect();
    assertErrorToast();
});

it('refuses to impersonate into a suspended organization', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    [$organization] = orgWithOwner('Frozen');
    $organization->forceFill(['status' => OrganizationStatus::Suspended])->save();

    $this->actingAs($admin)
        ->post(route('organizations.impersonate', $organization))
        ->assertRedirect();
    assertErrorToast();

    expect(app(ImpersonationToken::class)->count())->toBe(0);
});

it('sorts the organizations index by an allowlisted column', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    orgWithOwner('Zebra');
    orgWithOwner('Alpha');

    $this->actingAs($admin)
        ->get(route('organizations.index', ['sort' => 'name', 'direction' => 'asc']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organizations/index')
            ->where('sort.column', 'name')
            ->where('sort.direction', 'asc'));

    $this->actingAs($admin)
        ->get(route('organizations.index', ['sort' => 'bogus_column']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('sort.column', null));
});

it('exposes a human readable status label', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    [$organization] = orgWithOwner();

    $this->actingAs($admin)
        ->get(route('organizations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('organizations.data.0.status', 'active')
            ->where('organizations.data.0.status_label', 'Active'));
});
