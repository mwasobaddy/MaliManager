<?php

use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Plan;
use App\Models\User;
use App\Services\TenantService;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

function createSetupOrganization(User $owner, string $name = 'Setup Estates'): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $owner,
        name: $name,
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
}

function setupTenantUrl(Organization $organization, string $path = ''): string
{
    $domain = $organization->tenant->domains()->value('domain');

    return "http://{$domain}{$path}";
}

function attachSetupStaff(Organization $organization, User $staff): void
{
    $organization->users()->attach($staff->id, [
        'is_owner' => false,
        'sub_role_id' => $organization->subRoles()->where('slug', 'caretaker')->first()->id,
        'status' => 'active',
    ]);
}

test('a fresh owner can open the team step without any property', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createSetupOrganization($owner);

    $this->actingAs($owner)
        ->get(setupTenantUrl($organization, '/setup/team'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('setup/team')
            ->where('organization.name', 'Setup Estates')
            ->has('sub_roles')
        );
});

test('a fresh owner can open the review step without any property', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createSetupOrganization($owner);

    $this->actingAs($owner)
        ->get(setupTenantUrl($organization, '/setup/review'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('setup/review')
            ->where('organization.name', 'Setup Estates')
            ->where('properties', [])
            ->where('team.0.role', 'owner')
        );
});

test('staff are redirected from the setup funnel instead of denied', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createSetupOrganization($owner);
    $staff = User::factory()->create(['onboarded_at' => now()]);
    attachSetupStaff($organization, $staff);

    foreach (['/setup/team', '/setup/review'] as $path) {
        $this->actingAs($staff)
            ->get(setupTenantUrl($organization, $path))
            ->assertRedirect(setupTenantUrl($organization, '/properties'));
    }

    $this->actingAs($staff)
        ->post(setupTenantUrl($organization, '/setup/team'), [
            'name' => 'Nope Intruder',
            'email' => 'nope@setup.test',
            'sub_role_id' => $organization->subRoles()->where('slug', 'caretaker')->first()->id,
        ])->assertRedirect(setupTenantUrl($organization, '/properties'));

    expect(User::where('email', 'nope@setup.test')->exists())->toBeFalse();
});

test('the team step creates a staff member and continues to review', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createSetupOrganization($owner);
    $subRoleId = $organization->subRoles()->where('slug', 'caretaker')->first()->id;

    $this->actingAs($owner)
        ->post(setupTenantUrl($organization, '/setup/team'), [
            'name' => 'Jane Caretaker',
            'email' => 'jane@setup.test',
            'phone' => '+254700000001',
            'sub_role_id' => $subRoleId,
        ])->assertRedirect(setupTenantUrl($organization, '/setup/review'));

    $member = User::where('email', 'jane@setup.test')->first();
    expect($member)->not->toBeNull();

    $membership = OrganizationUser::where('organization_id', $organization->id)
        ->where('user_id', $member->id)
        ->first();
    expect($membership)->not->toBeNull()
        ->and($membership->sub_role_id)->toBe($subRoleId)
        ->and($membership->is_owner)->toBeFalse();
});

test('the team step requires a role', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createSetupOrganization($owner);

    $this->actingAs($owner)
        ->post(setupTenantUrl($organization, '/setup/team'), [
            'name' => 'Jane Caretaker',
            'email' => 'jane@setup.test',
        ])->assertInvalid('sub_role_id');
});

test('creating the first property inside setup continues to the team step', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createSetupOrganization($owner);

    $this->actingAs($owner)
        ->post(setupTenantUrl($organization, '/properties'), [
            'name' => 'Sunset Heights',
            'city' => 'Nairobi',
            'address' => '12 Riverside Drive',
            'setup' => '1',
            'units' => [
                ['name' => 'A1', 'type' => '1 Bedroom', 'monthly_rent' => 25000],
            ],
        ])->assertRedirect(setupTenantUrl($organization, '/setup/team'));
});

test('creating the first land parcel inside setup continues to the team step', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createSetupOrganization($owner);

    $this->actingAs($owner)
        ->post(setupTenantUrl($organization, '/land-parcels'), [
            'name' => 'Ruiru Plot A',
            'zoning' => 'commercial',
            'status' => 'active',
            'available_for_lease' => '0',
            'manager_ids' => [],
            'setup' => '1',
        ])->assertRedirect(setupTenantUrl($organization, '/setup/team'));
});

test('creating outside setup still lands on the property dashboard', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createSetupOrganization($owner);

    $this->actingAs($owner)
        ->post(setupTenantUrl($organization, '/properties'), [
            'name' => 'Sunset Heights',
            'city' => 'Nairobi',
            'address' => '12 Riverside Drive',
            'units' => [
                ['name' => 'A1', 'type' => '1 Bedroom', 'monthly_rent' => 25000],
            ],
        ])->assertRedirect(setupTenantUrl($organization, '/sunset-heights/dashboard'));
});

test('the create pages expose the setup flag as a server prop', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createSetupOrganization($owner);

    $this->actingAs($owner)
        ->get(setupTenantUrl($organization, '/properties/create?setup=1'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/properties/create')
            ->where('inSetup', true)
        );

    $this->actingAs($owner)
        ->get(setupTenantUrl($organization, '/properties/create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/properties/create')
            ->where('inSetup', false)
        );

    $this->actingAs($owner)
        ->get(setupTenantUrl($organization, '/land-parcels/create?setup=1'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/land-parcels/create')
            ->where('inSetup', true)
        );

    $this->actingAs($owner)
        ->get(setupTenantUrl($organization, '/land-parcels/create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/land-parcels/create')
            ->where('inSetup', false)
        );
});

test('creating a land parcel outside setup still lands on its detail page', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createSetupOrganization($owner);

    $this->actingAs($owner)
        ->post(setupTenantUrl($organization, '/land-parcels'), [
            'name' => 'Ruiru Plot A',
            'zoning' => 'commercial',
            'status' => 'active',
            'available_for_lease' => '0',
            'manager_ids' => [],
        ])->assertRedirect(setupTenantUrl($organization, '/land-parcels/ruiru-plot-a'));
});
