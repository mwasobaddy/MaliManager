<?php

use App\Models\Organization;
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

function createTestOrganization(User $user, string $name = 'Acme Estates'): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $user,
        name: $name,
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
}

function tenantUrl(Organization $organization, string $path = ''): string
{
    $domain = $organization->tenant->domains()->value('domain');

    return "http://{$domain}{$path}";
}

test('new organization owner is redirected to add their first property', function () {
    $user = User::factory()->create();
    $organization = createTestOrganization($user);

    $this->actingAs($user)
        ->get(tenantUrl($organization, '/properties'))
        ->assertRedirect(tenantUrl($organization, '/properties/create'));
});

test('organization owner can create a property with units', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $organization = createTestOrganization($user);

    $this->actingAs($user)
        ->post(tenantUrl($organization, '/properties'), [
            'name' => 'Sunset Heights',
            'city' => 'Nairobi',
            'address' => '12 Riverside Drive',
            'units' => [
                ['name' => 'A1', 'type' => '1 Bedroom', 'monthly_rent' => 25000],
                ['name' => 'A2', 'type' => '2 Bedroom', 'monthly_rent' => 45000],
            ],
        ])->assertRedirect(tenantUrl($organization, '/sunset-heights/dashboard'));

    $property = Property::where('slug', 'sunset-heights')->first();
    expect($property)->not->toBeNull()
        ->and($property->organization_id)->toBe($organization->id)
        ->and($property->units)->toHaveCount(2)
        ->and($property->units->first()->monthly_rent)->toBe('25000.00');
});

test('property index lists properties owned by the organization', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $organization = createTestOrganization($user);
    $property = $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $user->id,
    ]);
    $property->units()->create([
        'name' => 'A1',
        'status' => 'vacant',
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(tenantUrl($organization, '/properties'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant/properties/index')
            ->where('properties.0.name', 'Sunset Heights')
            ->where('properties.0.units_count', 1));
});

test('property page shows units and lets the owner add more', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $organization = createTestOrganization($user);
    $property = $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->post(tenantUrl($organization, "/{$property->slug}/units"), [
            'name' => 'B1',
            'type' => 'Studio',
            'monthly_rent' => 15000,
        ])->assertRedirect(tenantUrl($organization, "/{$property->slug}/dashboard"))
        ->assertSessionHas('status');

    expect($property->units()->where('name', 'B1')->exists())->toBeTrue();
});

test('free plan property limit prevents a second property', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $organization = createTestOrganization($user);
    $organization->properties()->create([
        'name' => 'First Property',
        'slug' => 'first-property',
        'status' => 'active',
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->post(tenantUrl($organization, '/properties'), [
            'name' => 'Second Property',
        ])->assertRedirect();

    assertErrorToast();

    expect(Property::where('slug', 'second-property')->exists())->toBeFalse();
});

test('free plan unit limit prevents units beyond the plan allowance', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $organization = createTestOrganization($user);
    $property = $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $user->id,
    ]);

    for ($i = 0; $i < 10; $i++) {
        $property->units()->create([
            'name' => "U{$i}",
            'status' => 'vacant',
            'created_by' => $user->id,
        ]);
    }

    $this->actingAs($user)
        ->post(tenantUrl($organization, "/{$property->slug}/units"), [
            'name' => 'U11',
        ])->assertRedirect();

    assertErrorToast();

    expect($property->units()->where('name', 'U11')->exists())->toBeFalse();
});

test('property dashboard is scoped to the current organization', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $other = User::factory()->create(['onboarded_at' => now()]);

    $organization = createTestOrganization($user);
    $otherOrganization = createTestOrganization($other, 'Other Estates');
    $otherOrganization->properties()->create([
        'name' => 'Other Property',
        'slug' => 'other-property',
        'status' => 'active',
        'created_by' => $other->id,
    ]);

    $this->actingAs($user)
        ->get(tenantUrl($organization, '/other-property/dashboard'))
        ->assertNotFound();
});

test('property dashboard shares the current property and organization to the sidebar', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $organization = createTestOrganization($user);
    $property = $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(tenantUrl($organization, '/sunset-heights/dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant/properties/dashboard')
            ->where('tenant.organization.slug', $organization->slug)
            ->where('tenant.property.slug', $property->slug)
            ->where('tenant.property.name', 'Sunset Heights'));
});

test('unauthenticated guests are redirected from tenant property pages', function () {
    $organization = Organization::factory()->create();
    $organization->tenant->domains()->create(['domain' => 'acme.malimanager.test']);

    $this->get(tenantUrl($organization, '/properties'))
        ->assertRedirect(route('login'));
});
