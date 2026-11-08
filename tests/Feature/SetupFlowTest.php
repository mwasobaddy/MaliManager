<?php

use App\Models\LandParcel;
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

test('the team step redirects to the first asset without any asset', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createSetupOrganization($owner);

    $this->actingAs($owner)
        ->get(setupTenantUrl($organization, '/setup/team'))
        ->assertRedirect(setupTenantUrl($organization, '/setup/first-asset'));
});

test('the review step redirects to the first asset without any asset', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createSetupOrganization($owner);

    $this->actingAs($owner)
        ->get(setupTenantUrl($organization, '/setup/review'))
        ->assertRedirect(setupTenantUrl($organization, '/setup/first-asset'));
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

test('the create pages stay inside the setup funnel when flagged', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createSetupOrganization($owner);

    $this->actingAs($owner)
        ->get(setupTenantUrl($organization, '/properties/create?setup=1'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('setup/property')
            ->has('organization')
            ->has('plan')
            ->has('propertyTypes')
            ->has('canCreateProperty')
        );

    $this->actingAs($owner)
        ->get(setupTenantUrl($organization, '/properties/create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/properties/create')
            ->has('organization')
            ->has('plan')
            ->has('propertyTypes')
            ->has('canCreateProperty')
        );

    $this->actingAs($owner)
        ->get(setupTenantUrl($organization, '/land-parcels/create?setup=1'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('setup/parcel')
            ->has('organization')
            ->has('managers')
        );

    $this->actingAs($owner)
        ->get(setupTenantUrl($organization, '/land-parcels/create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/land-parcels/create')
            ->has('organization')
            ->has('managers')
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

test('funnel pages report no progress before the organization exists', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);

    $this->actingAs($user)
        ->get(route('guest.become-landlord.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('setup/become-landlord')
            ->where('setupProgress', ['hasOrganization' => false, 'hasAsset' => false])
        );

    $this->actingAs($user)
        ->get(route('onboarding.first-asset'))
        ->assertRedirect(route('guest.become-landlord.show'));
});

test('setup progress flips on once the organization has an asset', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createSetupOrganization($owner);

    $this->actingAs($owner)
        ->get(setupTenantUrl($organization, '/setup/team'))
        ->assertRedirect(setupTenantUrl($organization, '/setup/first-asset'));

    $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->get(setupTenantUrl($organization, '/setup/team'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('setupProgress', ['hasOrganization' => true, 'hasAsset' => true])
        );

    $this->actingAs($owner)
        ->get(setupTenantUrl($organization, '/setup/review'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('setupProgress', ['hasOrganization' => true, 'hasAsset' => true])
        );
});

test('a land parcel alone counts as the first asset for setup progress', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createSetupOrganization($owner);
    LandParcel::factory()->for($organization)->create();

    $this->actingAs($owner)
        ->get(setupTenantUrl($organization, '/setup/team'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('setupProgress', ['hasOrganization' => true, 'hasAsset' => true])
        );
});
