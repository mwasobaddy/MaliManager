<?php

use App\Models\Lease;
use App\Models\Occupant;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\SubPermission;
use App\Models\SubRole;
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
                ['name' => 'A1', 'type' => '1 Bedroom', 'rent_amount' => 25000, 'rent_frequency' => 'monthly'],
                ['name' => 'A2', 'type' => '2 Bedroom', 'rent_amount' => 45000, 'rent_frequency' => 'monthly'],
            ],
        ])->assertRedirect(tenantUrl($organization, '/sunset-heights/dashboard'));

    $property = Property::where('slug', 'sunset-heights')->first();
    expect($property)->not->toBeNull()
        ->and($property->organization_id)->toBe($organization->id)
        ->and($property->units)->toHaveCount(2)
        ->and($property->units->first()->rent_amount)->toBe('25000.00');
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
            ->component('organisation/properties/index')
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
            'rent_amount' => 15000, 'rent_frequency' => 'monthly',
        ])->assertRedirect(tenantUrl($organization, "/{$property->slug}/dashboard"))
        ->assertSessionHas('status');

    expect($property->units()->where('name', 'B1')->exists())->toBeTrue();
});

test('property dashboard shows real stats and charts', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $organization = createTestOrganization($user);
    $property = $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $user->id,
    ]);

    $property->units()->createMany([
        ['name' => 'A1', 'status' => 'occupied', 'rent_amount' => 25000, 'rent_frequency' => 'monthly', 'created_by' => $user->id],
        ['name' => 'A2', 'status' => 'vacant', 'rent_amount' => 45000, 'rent_frequency' => 'monthly', 'created_by' => $user->id],
        ['name' => 'A3', 'status' => 'maintenance', 'rent_amount' => 30000, 'rent_frequency' => 'monthly', 'created_by' => $user->id],
    ]);

    $this->actingAs($user)
        ->get(tenantUrl($organization, "/{$property->slug}/dashboard"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/properties/dashboard')
            ->where('stats.total_units', 3)
            ->where('stats.occupied', 1)
            ->where('stats.vacant', 1)
            ->where('stats.in_maintenance', 1)
            ->where('stats.occupancy_rate', 33)
            ->where('stats.rent_potential', 100000)
            ->where('stats.unit_status.0.status', 'occupied')
            ->where('stats.unit_status.0.count', 1)
            ->where('stats.operations_monthly', fn ($rows) => collect($rows)->count() === 12));
});

test('property navigation persists onto org-level pages within the session', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $organization = createTestOrganization($user);
    $property = $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(tenantUrl($organization, "/{$property->slug}/dashboard"))
        ->assertOk();

    $context = $this->actingAs($user)
        ->get(tenantUrl($organization, '/overview'))
        ->assertOk()
        ->viewData('page')['props']['context'];

    expect($context['property']['slug'] ?? null)->toBe('sunset-heights');
});

test('org-level pages show no property context without a prior visit', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $organization = createTestOrganization($user);
    // Two buildings: with a single property the nav context pre-selects
    // it (see the dashboard tests), so the ambiguous case needs two.
    $organization->properties()->createMany([
        [
            'name' => 'Sunset Heights',
            'slug' => 'sunset-heights',
            'status' => 'active',
            'created_by' => $user->id,
        ],
        [
            'name' => 'Umoja Blocks',
            'slug' => 'umoja-blocks',
            'status' => 'active',
            'created_by' => $user->id,
        ],
    ]);

    $context = $this->actingAs($user)
        ->get(tenantUrl($organization, '/overview'))
        ->assertOk()
        ->viewData('page')['props']['context'];

    expect($context['property'] ?? null)->toBeNull();
});

test('unit forms receive the property-type catalogue', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $organization = createTestOrganization($user);
    $property = $organization->properties()->create([
        'name' => 'Catalogue Courts',
        'slug' => 'catalogue-courts',
        'status' => 'active',
        'created_by' => $user->id,
    ]);

    $expected = PropertyType::query()
        ->where('status', 'active')
        ->orderBy('sort_order')
        ->orderBy('name')
        ->pluck('name')
        ->all();

    expect($expected)->not->toBeEmpty();

    $indexProps = $this->actingAs($user)
        ->get(tenantUrl($organization, '/units'))
        ->assertOk()
        ->viewData('page')['props'];

    $dashboardProps = $this->actingAs($user)
        ->get(tenantUrl($organization, '/catalogue-courts/dashboard'))
        ->assertOk()
        ->viewData('page')['props'];

    expect($indexProps['unitTypes'])->toBe($expected)
        ->and($dashboardProps['unitTypes'])->toBe($expected);
});

test('property attention lists one row per condition capped at fifteen', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $organization = createTestOrganization($user);
    $property = $organization->properties()->create([
        'name' => 'Attention Courts',
        'slug' => 'attention-courts',
        'status' => 'active',
        'created_by' => $user->id,
    ]);

    $property->units()->createMany([
        ['name' => 'A1', 'status' => 'occupied', 'rent_amount' => 25000, 'rent_frequency' => 'monthly', 'created_by' => $user->id],
        ['name' => 'A2', 'status' => 'vacant', 'rent_amount' => 20000, 'rent_frequency' => 'monthly', 'created_by' => $user->id],
    ]);

    $occupant = Occupant::factory()->create(['organization_id' => $organization->id]);

    Lease::factory()->create([
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'unit_id' => $property->units()->where('name', 'A1')->value('id'),
        'person_id' => $occupant->person_id,
        'status' => 'active',
        'starts_at' => now()->subMonthsNoOverflow(2)->toDateString(),
        'ends_at' => now()->addDays(20)->toDateString(),
        'rent_amount' => 25000, 'rent_frequency' => 'monthly',
        'rent_due_day' => 5,
    ]);

    $attention = $this->actingAs($user)
        ->get(tenantUrl($organization, '/attention-courts/dashboard'))
        ->assertOk()
        ->viewData('page')['props']['stats']['attention'];

    // One overdue row, one expiring row for the same lease, one vacant row.
    expect($attention)->toHaveCount(3)
        ->and($attention[0]['severity'])->toBe('danger')
        ->and($attention[0]['text'])->toContain('A1')
        ->and($attention[0]['text'])->toContain('days overdue')
        ->and($attention[0]['href'])->toBe('/attention-courts/leases')
        ->and($attention[0]['linkText'])->toContain('A1')
        ->and($attention[1]['severity'])->toBe('warning')
        ->and($attention[1]['text'])->toContain('A1')
        ->and($attention[1]['href'])->toContain('dialog=suggest')
        ->and($attention[1]['linkText'])->toContain('A1')
        ->and($attention[2]['severity'])->toBe('info')
        ->and($attention[2]['text'])->toContain('A2')
        ->and($attention[2]['href'])->toBe('/attention-courts/occupants?create=1&unit='.$property->units()->where('name', 'A2')->value('id'))
        ->and($attention[2]['linkText'])->toBe('A2');
});

test('property defaulter row reports the reminder throttle state', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $organization = createTestOrganization($user);
    $property = $organization->properties()->create([
        'name' => 'Throttle House',
        'slug' => 'throttle-house',
        'status' => 'active',
        'created_by' => $user->id,
    ]);

    $property->units()->createMany([
        ['name' => 'A1', 'status' => 'occupied', 'rent_amount' => 25000, 'rent_frequency' => 'monthly', 'created_by' => $user->id],
        ['name' => 'A2', 'status' => 'occupied', 'rent_amount' => 20000, 'rent_frequency' => 'monthly', 'created_by' => $user->id],
    ]);

    $makeLease = function (string $unit, ?string $remindedAt) use ($organization, $property) {
        $occupant = Occupant::factory()->create(['organization_id' => $organization->id]);

        $lease = Lease::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'unit_id' => $property->units()->where('name', $unit)->value('id'),
            'person_id' => $occupant->person_id,
            'status' => 'active',
            'starts_at' => now()->subMonthsNoOverflow(2)->toDateString(),
            'ends_at' => now()->addMonthsNoOverflow(4)->toDateString(),
            'rent_amount' => 25000, 'rent_frequency' => 'monthly',
            'rent_due_day' => 5,
        ]);

        if ($remindedAt !== null) {
            $lease->forceFill(['last_reminded_at' => $remindedAt])->save();
        }

        return $lease;
    };

    $makeLease('A1', null);
    $makeLease('A2', now()->subHours(2)->toDateTimeString());

    $defaulters = $this->actingAs($user)
        ->get(tenantUrl($organization, '/throttle-house/dashboard'))
        ->assertOk()
        ->viewData('page')['props']['stats']['collection']['defaulters'];

    expect($defaulters)->toHaveCount(2)
        ->and($defaulters[0]['can_remind'])->toBeTrue()
        ->and($defaulters[0]['remind_throttled'])->toBeFalse()
        ->and($defaulters[0]['last_reminded_ago'])->toBeNull()
        ->and($defaulters[1]['remind_throttled'])->toBeTrue()
        ->and($defaulters[1]['last_reminded_ago'])->toContain('hour');
});

test('property attention caps vacant rows at fifteen', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $organization = createTestOrganization($user);
    $property = $organization->properties()->create([
        'name' => 'Big Block',
        'slug' => 'big-block',
        'status' => 'active',
        'created_by' => $user->id,
    ]);

    $units = [];

    for ($i = 1; $i <= 20; $i++) {
        $units[] = ['name' => "U{$i}", 'status' => 'vacant', 'rent_amount' => 10000, 'rent_frequency' => 'monthly', 'created_by' => $user->id];
    }

    $property->units()->createMany($units);

    $attention = $this->actingAs($user)
        ->get(tenantUrl($organization, '/big-block/dashboard'))
        ->assertOk()
        ->viewData('page')['props']['stats']['attention'];

    expect($attention)->toHaveCount(15)
        ->and($attention[0]['severity'])->toBe('info');
});

test('property dashboard carries collection, attention, expiry and unit grid', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $organization = createTestOrganization($user);
    $property = $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $user->id,
    ]);

    $property->units()->createMany([
        ['name' => 'A1', 'status' => 'occupied', 'rent_amount' => 25000, 'rent_frequency' => 'monthly', 'created_by' => $user->id],
        ['name' => 'A2', 'status' => 'vacant', 'rent_amount' => 20000, 'rent_frequency' => 'monthly', 'created_by' => $user->id],
    ]);

    $occupant = Occupant::factory()->create(['organization_id' => $organization->id]);

    $lease = Lease::factory()->create([
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'person_id' => $occupant->person_id,
        'status' => 'active',
        'starts_at' => now()->subMonthsNoOverflow(2)->toDateString(),
        'ends_at' => now()->addDays(20)->toDateString(),
        'rent_amount' => 25000, 'rent_frequency' => 'monthly',
        'rent_due_day' => 5,
    ]);

    $stats = $this->actingAs($user)
        ->get(tenantUrl($organization, "/{$property->slug}/dashboard"))
        ->assertOk()
        ->viewData('page')['props']['stats'];

    expect($stats['collection']['expected_mtd'])->toBe('25000.00')
        ->and($stats['collection']['overdue_count'])->toBe(1)
        ->and($stats['collection']['defaulters'][0]['lease_id'])->toBe($lease->id)
        ->and($stats['collection']['defaulters'][0]['can_remind'])->toBeTrue()
        ->and($stats['expiring_leases'])->toHaveCount(1)
        ->and($stats['units_grid'])->toHaveCount(1)
        ->and($stats['units_grid'][0]['units'])->toHaveCount(2)
        ->and($stats['units_grid'][0]['units'][0])->toHaveKeys(['id', 'name', 'status', 'overdue', 'occupant', 'type', 'rent_amount', 'rent_frequency'])
        ->and($stats['units_grid'][0]['units'][0]['rent_amount'])->toBe('25000.00')
        ->and($stats['units_grid'][0]['units'][0]['rent_frequency'])->toBe('monthly');
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
            'rent_frequency' => 'monthly',
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
            ->component('organisation/properties/dashboard')
            ->where('context.organization.slug', $organization->slug)
            ->where('context.property.slug', $property->slug)
            ->where('context.property.name', 'Sunset Heights'));
});

test('unauthenticated guests are redirected from tenant property pages', function () {
    $organization = Organization::factory()->create();
    $organization->tenant->domains()->create(['domain' => 'acme.malimanager.test']);

    $this->get(tenantUrl($organization, '/properties'))
        ->assertRedirect(route('login'));
});

test('exceeding the plan unit limit surfaces a toast from the global handler', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $organization = createTestOrganization($user);
    $property = $organization->properties()->create([
        'name' => 'First Estate',
        'slug' => 'first-estate',
        'status' => 'active',
        'created_by' => $user->id,
    ]);
    for ($i = 1; $i <= 10; $i++) {
        $property->units()->create(['name' => 'U'.$i]);
    }

    $this->actingAs($user)
        ->post(tenantUrl($organization, '/first-estate/units'), [
            'name' => 'Overflow Unit',
            'rent_frequency' => 'monthly',
        ])
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data', fn (array $flash) => str_contains(
            $flash['toast']['message'] ?? '',
            'allows up to 10 units',
        ));

    expect($property->units()->count())->toBe(10);
});

test('platform admin can create a property via god-mode without org membership', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createTestOrganization($owner);

    $admin = User::factory()->create(['onboarded_at' => now()]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->post(tenantUrl($organization, '/properties'), [
            'name' => 'Admin Plot',
            'city' => 'Nairobi',
            'address' => '1 Admin Road',
            'units' => [['name' => 'A1', 'type' => '1 Bedroom', 'rent_amount' => 10000, 'rent_frequency' => 'monthly']],
        ])
        ->assertRedirect();

    expect(Property::where('name', 'Admin Plot')->where('organization_id', $organization->id)->exists())->toBeTrue();
});

test('platform admin sees add links on the properties index', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Acme Estates',
        plan: Plan::where('slug', 'starter')->firstOrFail(),
    );
    $organization->properties()->create([
        'name' => 'Seed Estate', 'slug' => 'seed-estate', 'status' => 'active', 'created_by' => $owner->id,
    ]);

    $admin = User::factory()->create(['onboarded_at' => now()]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(tenantUrl($organization, '/properties'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canCreateProperty', true));
});

test('staff with the property.create sub-permission can create a property', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createTestOrganization($owner);

    $staff = User::factory()->create(['onboarded_at' => now()]);
    $role = SubRole::factory()->create(['organization_id' => $organization->id, 'created_by' => $owner->id]);
    $role->subPermissions()->attach(SubPermission::where('key', 'property.create')->firstOrFail()->id);
    $organization->users()->attach($staff->id, ['sub_role_id' => $role->id, 'status' => 'active']);

    $this->actingAs($staff)
        ->post(tenantUrl($organization, '/properties'), [
            'name' => 'Staff Block',
            'city' => 'Nairobi',
            'address' => '2 Staff Lane',
            'units' => [['name' => 'B1', 'type' => '1 Bedroom', 'rent_amount' => 12000, 'rent_frequency' => 'monthly']],
        ])
        ->assertRedirect();

    expect(Property::where('name', 'Staff Block')->where('organization_id', $organization->id)->exists())->toBeTrue();
});

test('staff without the property.create sub-permission is forbidden from creating', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createTestOrganization($owner);

    $staff = User::factory()->create(['onboarded_at' => now()]);
    $organization->users()->attach($staff->id, ['status' => 'active']);

    $this->actingAs($staff)
        ->post(tenantUrl($organization, '/properties'), [
            'name' => 'No Perm Block',
            'city' => 'Nairobi',
            'address' => '3 Denied Way',
            'units' => [['name' => 'C1', 'type' => '1 Bedroom', 'rent_amount' => 12000, 'rent_frequency' => 'monthly']],
        ])
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data', fn (array $flash) => str_contains(
            $flash['toast']['message'] ?? '',
            'do not have permission',
        ));

    expect(Property::where('name', 'No Perm Block')->exists())->toBeFalse();
});

test('staff without property.manage cannot view the properties index', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createTestOrganization($owner);
    $organization->properties()->create([
        'name' => 'Seed Estate', 'slug' => 'seed-estate', 'status' => 'active', 'created_by' => $owner->id,
    ]);

    $staff = User::factory()->create(['onboarded_at' => now()]);
    $organization->users()->attach($staff->id, ['status' => 'active']);

    $this->actingAs($staff)
        ->get(tenantUrl($organization, '/properties'))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data', fn (array $flash) => str_contains(
            $flash['toast']['message'] ?? '',
            'do not have permission',
        ));
});

test('staff with property.manage can view the properties index', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createTestOrganization($owner);
    $organization->properties()->create([
        'name' => 'Seed Estate', 'slug' => 'seed-estate', 'status' => 'active', 'created_by' => $owner->id,
    ]);

    $staff = User::factory()->create(['onboarded_at' => now()]);
    $role = SubRole::factory()->create(['organization_id' => $organization->id, 'created_by' => $owner->id]);
    $role->subPermissions()->attach(SubPermission::where('key', 'property.manage')->firstOrFail()->id);
    $organization->users()->attach($staff->id, ['sub_role_id' => $role->id, 'status' => 'active']);

    $this->actingAs($staff)
        ->get(tenantUrl($organization, '/properties'))
        ->assertOk();
});

test('units index carries property slugs, counts and plan limit for the create sheet', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $organization = createTestOrganization($user);
    $property = $organization->properties()->create([
        'name' => 'First Estate',
        'slug' => 'first-estate',
        'status' => 'active',
        'created_by' => $user->id,
    ]);
    $property->units()->create(['name' => 'A1']);

    $props = $this->actingAs($user)
        ->get(tenantUrl($organization, '/units'))
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['properties'])->toHaveCount(1)
        ->and($props['properties'][0]['slug'])->toBe('first-estate')
        ->and($props['properties'][0]['units_count'])->toBe(1)
        ->and($props['plan'])->toMatchArray(['units_limit' => 10]);
});

test('creating a unit from the index stays on the index', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $organization = createTestOrganization($user);
    $property = $organization->properties()->create([
        'name' => 'First Estate',
        'slug' => 'first-estate',
        'status' => 'active',
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)
        ->from(tenantUrl($organization, '/units'))
        ->post(tenantUrl($organization, '/first-estate/units'), [
            'name' => 'A1',
            'type' => '1 Bedroom',
            'rent_amount' => 25000, 'rent_frequency' => 'monthly',
        ])
        ->assertRedirect(tenantUrl($organization, '/units'));

    expect($property->units()->where('name', 'A1')->exists())->toBeTrue();
    assertSuccessToast();
});
