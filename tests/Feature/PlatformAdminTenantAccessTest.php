<?php

use App\Models\Plan;
use App\Models\Property;
use App\Models\User;
use App\Services\TenantService;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

function adminVisibleProperty(User $owner): Property
{
    $organization = app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Admin View Towers',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );

    return $organization->properties()->create([
        'name' => 'Admin View Residences',
        'slug' => 'admin-view',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);
}

test('a platform admin without membership sees tenant action UI', function () {
    // Route gates (EnsureSubPermission via hasSubPermission) already let
    // platform admins through; the shared context.permissions must match
    // or the admin gets a page with no action buttons at all.
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = adminVisibleProperty($owner);

    $admin = User::factory()->create(['onboarded_at' => now(), 'email_verified_at' => now()]);
    $admin->assignRole('admin');

    $domain = $property->organization->tenant->domains()->value('domain');

    $this->actingAs($admin)
        ->get("http://{$domain}/units")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/unit/index')
            ->where('context.permissions', fn ($permissions) => collect($permissions)->contains('unit.delete')),
        );
});

test('a non-member without admin rights sees no tenant action UI', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = adminVisibleProperty($owner);

    $outsider = User::factory()->create(['onboarded_at' => now()]);

    $domain = $property->organization->tenant->domains()->value('domain');

    $this->actingAs($outsider)
        ->get("http://{$domain}/units")
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data', fn ($flash) => ($flash['toast']['type'] ?? null) === 'error');
});
