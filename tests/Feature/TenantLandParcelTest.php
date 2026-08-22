<?php

use App\Models\Delegation;
use App\Models\LandParcel;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Services\LandParcelService;
use App\Services\TenantService;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

function createLandParcelOrganization(User $owner, string $name = 'Acme Estates'): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $owner,
        name: $name,
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
}

function parcelTenantUrl(Organization $organization, string $path = ''): string
{
    $domain = $organization->tenant->domains()->value('domain');

    return "http://{$domain}{$path}";
}

function createParcel(Organization $organization, User $owner, array $attributes = []): LandParcel
{
    return app(LandParcelService::class)->create($organization, $owner, array_merge([
        'name' => 'Ruiru Plot A',
        'zoning' => 'residential',
        'status' => 'active',
    ], $attributes));
}

test('land parcels index requires the land_parcel.manage sub-permission', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createLandParcelOrganization($owner);

    $member = User::factory()->create(['onboarded_at' => now()]);
    $organization->users()->attach($member->id, ['status' => 'active']);

    $this->actingAs($member)
        ->get(parcelTenantUrl($organization, '/land-parcels'))
        ->assertRedirect();
    assertErrorToast();
});

test('owner can create a land parcel', function () {
    $owner = User::factory()->create(['onboarded_at' => now(), 'password' => 'secret-pass']);
    $organization = createLandParcelOrganization($owner);

    $this->actingAs($owner)
        ->post(parcelTenantUrl($organization, '/land-parcels'), [
            'name' => 'Ruiru Plot A',
            'zoning' => 'commercial',
            'status' => 'active',
            'available_for_lease' => '0',
            'manager_ids' => [],
        ])
        ->assertRedirect(parcelTenantUrl($organization, '/land-parcels/'.LandParcel::where('name', 'Ruiru Plot A')->first()->slug));

    expect(LandParcel::where('organization_id', $organization->id)->where('name', 'Ruiru Plot A')->exists())->toBeTrue();
});

test('owner can view a land parcel', function () {
    $owner = User::factory()->create(['onboarded_at' => now(), 'password' => 'secret-pass']);
    $organization = createLandParcelOrganization($owner);
    $parcel = createParcel($organization, $owner);

    $this->actingAs($owner)
        ->get(parcelTenantUrl($organization, "/land-parcels/{$parcel->slug}"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('tenant/land-parcels/show'));
});

test('owner can update a land parcel', function () {
    $owner = User::factory()->create(['onboarded_at' => now(), 'password' => 'secret-pass']);
    $organization = createLandParcelOrganization($owner);
    $parcel = createParcel($organization, $owner);

    $this->actingAs($owner)
        ->put(parcelTenantUrl($organization, "/land-parcels/{$parcel->slug}"), [
            'name' => 'Ruiru Plot B',
            'zoning' => 'agricultural',
            'status' => 'active',
            'available_for_lease' => '0',
            'manager_ids' => [],
        ])
        ->assertRedirect(parcelTenantUrl($organization, "/land-parcels/{$parcel->slug}"));

    expect($parcel->fresh()->name)->toBe('Ruiru Plot B')
        ->and($parcel->fresh()->zoning)->toBe('agricultural');
});

test('owner can delete a land parcel', function () {
    $owner = User::factory()->create(['onboarded_at' => now(), 'password' => 'secret-pass']);
    $organization = createLandParcelOrganization($owner);
    $parcel = createParcel($organization, $owner);

    $this->actingAs($owner)
        ->delete(parcelTenantUrl($organization, "/land-parcels/{$parcel->slug}"), [
            'password' => 'secret-pass',
        ])
        ->assertRedirect(parcelTenantUrl($organization, '/land-parcels'));

    expect(LandParcel::where('id', $parcel->id)->exists())->toBeFalse()
        ->and(LandParcel::withTrashed()->where('id', $parcel->id)->exists())->toBeTrue();
});

test('caretaker without the delete permission cannot remove a parcel', function () {
    $owner = User::factory()->create(['onboarded_at' => now(), 'password' => 'secret-pass']);
    $organization = createLandParcelOrganization($owner);
    $parcel = createParcel($organization, $owner);

    $caretaker = User::factory()->create(['onboarded_at' => now(), 'password' => 'secret-pass']);
    $organization->users()->attach($caretaker->id, [
        'sub_role_id' => $organization->subRoles()->where('slug', 'caretaker')->first()->id,
        'status' => 'active',
    ]);

    $this->actingAs($caretaker)
        ->delete(parcelTenantUrl($organization, "/land-parcels/{$parcel->slug}"), [
            'password' => 'secret-pass',
        ])
        ->assertRedirect();
    assertErrorToast();

    expect(LandParcel::where('id', $parcel->id)->exists())->toBeTrue();
});

test('delegated non-owner can manage a parcel, undelegated cannot', function () {
    $owner = User::factory()->create(['onboarded_at' => now(), 'password' => 'secret-pass']);
    $organization = createLandParcelOrganization($owner);
    $parcel = createParcel($organization, $owner);

    $caretaker = User::factory()->create(['onboarded_at' => now(), 'password' => 'secret-pass']);
    $organization->users()->attach($caretaker->id, [
        'sub_role_id' => $organization->subRoles()->where('slug', 'caretaker')->first()->id,
        'status' => 'active',
    ]);

    // Undelegated: the route gate (land_parcel.manage) passes but authorizeAccess 403s.
    $this->actingAs($caretaker)
        ->get(parcelTenantUrl($organization, "/land-parcels/{$parcel->slug}"))
        ->assertRedirect();
    assertErrorToast();

    $membershipId = $organization->users()->where('user_id', $caretaker->id)->first()->pivot->id;

    Delegation::create([
        'organization_id' => $organization->id,
        'organization_user_id' => $membershipId,
        'delegatable_type' => LandParcel::class,
        'delegatable_id' => $parcel->id,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($caretaker)
        ->get(parcelTenantUrl($organization, "/land-parcels/{$parcel->slug}"))
        ->assertOk();
});
