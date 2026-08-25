<?php

use App\Enums\PlatformRole;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\SubPermission;
use App\Models\User;
use App\Services\TenantService;
use App\Services\UserService;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function platformAdmin(): User
{
    $admin = User::factory()->create(['onboarded_at' => now()]);
    $admin->assignRole(PlatformRole::Admin->value);

    return $admin;
}

function seededAdmin(): User
{
    (new RolesAndPermissionsSeeder)->run();

    return platformAdmin();
}

it('grants all central user and organization permissions to the admin role', function () {
    $admin = seededAdmin();

    foreach (['user.manage', 'user.create', 'user.edit', 'user.delete', 'user.export', 'organization.manage', 'organization.create', 'organization.edit'] as $permission) {
        expect($admin->can($permission))->toBeTrue();
    }
});

it('grants the access admin dashboard permission to admins only', function () {
    (new RolesAndPermissionsSeeder)->run();

    $admin = platformAdmin();
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = Organization::factory()->create();
    $owner->organizations()->attach($organization->id, ['is_owner' => true, 'status' => 'active']);
    $owner->assignRole(PlatformRole::OrganizationOwner->value);

    expect($admin->can('accessAdminDashboard'))->toBeTrue()
        ->and($admin->can('access admin dashboard'))->toBeTrue()
        ->and($owner->can('accessAdminDashboard'))->toBeFalse()
        ->and($owner->can('access admin dashboard'))->toBeFalse();
});

it('forbids users without the manage permission from listing users', function () {
    (new RolesAndPermissionsSeeder)->run();

    $user = User::factory()->create(['onboarded_at' => now()]);

    // The app converts 403s into a redirect with an error toast.
    $this->actingAs($user)->get(route('users.index'))->assertRedirect();
    $this->actingAs($user)->get(route('organizations.index'))->assertRedirect();
});

it('creates a person and user account together, deduping by email', function () {
    $admin = seededAdmin();
    $service = app(UserService::class);

    $user = $service->create([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.test',
        'phone' => '0700000001',
        'status' => 'active',
        'roles' => [PlatformRole::Searcher->value],
    ], $admin);

    expect($user->person_id)->not->toBeNull()
        ->and($user->person->first_name)->toBe('Jane')
        ->and($user->hasRole(PlatformRole::Searcher->value))->toBeTrue();

    // Creating again with the same email links to the same person.
    $second = $service->create([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.test',
        'status' => 'active',
        'roles' => [],
    ], $admin);

    expect($second->id)->toBe($user->id)
        ->and($second->person_id)->toBe($user->person_id);
});

it('prevents admins from deleting their own account with a toast', function () {
    $admin = seededAdmin();

    $this->actingAs($admin)
        ->delete(route('users.destroy', $admin), ['password' => 'password'])
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data', fn (array $flash) => ($flash['toast']['message'] ?? null) === 'You cannot delete your own account.');

    expect($admin->refresh()->trashed())->toBeFalse();
});

it('requires the current password to delete a user', function () {
    $admin = seededAdmin();
    $other = User::factory()->create(['onboarded_at' => now(), 'status' => 'active']);

    $this->actingAs($admin)
        ->delete(route('users.destroy', $other), ['password' => 'wrong-password'])
        ->assertSessionHasErrors('password');

    expect($other->refresh()->trashed())->toBeFalse();
});

it('allows a permitted admin to delete another user with their password', function () {
    $admin = seededAdmin();
    $other = User::factory()->create(['onboarded_at' => now(), 'status' => 'active']);

    $this->actingAs($admin)
        ->delete(route('users.destroy', $other), ['password' => 'password'])
        ->assertRedirect();

    expect($other->refresh()->trashed())->toBeTrue();
});

it('requires the current password to delete an organization', function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
    $admin = platformAdmin();
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $plan = Plan::where('slug', 'free')->firstOrFail();
    $organization = app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Doomed Estates',
        plan: $plan,
    );

    $this->actingAs($admin)
        ->delete(route('organizations.destroy', $organization), ['password' => 'wrong-password'])
        ->assertSessionHasErrors('password');

    expect($organization->refresh()->trashed())->toBeFalse();

    $this->actingAs($admin)
        ->delete(route('organizations.destroy', $organization), ['password' => 'password'])
        ->assertRedirect(route('organizations.index'));

    expect($organization->refresh()->trashed())->toBeTrue();
});

it('exports users as csv', function () {
    $admin = seededAdmin();

    $response = $this->actingAs($admin)->get(route('users.export'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
});

it('creates an organization with full tenancy provisioning', function () {
    $admin = seededAdmin();
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $plan = Plan::factory()->create();

    $this->actingAs($admin)->post(route('organizations.store'), [
        'name' => 'Acme Estates Central',
        'owner_mode' => 'existing',
        'owner_user_id' => $owner->id,
        'plan_id' => $plan->id,
    ])->assertRedirect();

    $organization = Organization::where('name', 'Acme Estates Central')->firstOrFail();

    expect($organization->plan_id)->toBe($plan->id)
        ->and($organization->tenant()->exists())->toBeTrue()
        ->and($organization->tenant->domains()->exists())->toBeTrue()
        ->and($organization->users()->whereKey($owner->id)->exists())->toBeTrue()
        ->and($owner->fresh()->hasRole(PlatformRole::OrganizationOwner->value))->toBeTrue();
});

it('creates a brand new owner when requested', function () {
    $admin = seededAdmin();
    $plan = Plan::factory()->create();

    $this->actingAs($admin)->post(route('organizations.store'), [
        'name' => 'Fresh Owner Org',
        'owner_mode' => 'new',
        'owner_first_name' => 'Olivia',
        'owner_email' => 'olivia@example.test',
        'plan_id' => $plan->id,
    ])->assertRedirect();

    $owner = User::where('email', 'olivia@example.test')->firstOrFail();

    expect($owner->person_id)->not->toBeNull()
        ->and($owner->hasRole(PlatformRole::OrganizationOwner->value))->toBeTrue()
        ->and(Organization::where('name', 'Fresh Owner Org')->exists())->toBeTrue();
});

it('suspends an organization through edit permission', function () {
    $admin = seededAdmin();
    $plan = Plan::factory()->create();
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = app(TenantService::class)->createOrganization($owner, 'Suspend Me', $plan);

    $this->actingAs($admin)
        ->patch(route('organizations.update', $organization), [
            'name' => $organization->name,
            'status' => 'suspended',
            'plan_id' => $plan->id,
        ])
        ->assertRedirect();

    expect($organization->refresh()->status)->toBe('suspended');
});

it('retires stale sub-permissions from the catalog on seeding', function () {
    (new RolesAndPermissionsSeeder)->run();

    SubPermission::create(['key' => 'user.manage', 'module' => 'user', 'name' => 'Manage users', 'sort_order' => 99]);

    (new RolesAndPermissionsSeeder)->run();

    expect(SubPermission::where('key', 'user.manage')->exists())->toBeFalse()
        ->and(SubPermission::where('key', 'staff.manage')->exists())->toBeTrue();
});

it('filters the users index by a partial search term', function () {
    $admin = seededAdmin();
    User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane.doe@example.test', 'onboarded_at' => now()]);
    User::factory()->create(['name' => 'John Smith', 'email' => 'john.smith@example.test', 'onboarded_at' => now()]);

    $this->actingAs($admin)
        ->get(route('users.index', ['search' => 'jane.doe']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('users/index')
            ->where('users.data', fn ($rows) => count($rows) === 1 && str_contains($rows[0]['email'], 'jane.doe')));
});

it('filters the organizations index by a partial search term', function () {
    (new PlansSeeder)->run();
    $admin = seededAdmin();
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $plan = Plan::where('slug', 'free')->firstOrFail();
    app(TenantService::class)->createOrganization(owner: $owner, name: 'Zebra Holdings', plan: $plan);
    app(TenantService::class)->createOrganization(owner: $admin, name: 'Aardvark Group', plan: $plan);

    $this->actingAs($admin)
        ->get(route('organizations.index', ['search' => 'zebra']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organizations/index')
            ->where('organizations.data', fn ($rows) => count($rows) === 1));
});
