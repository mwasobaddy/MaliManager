<?php

use App\Models\LandParcel;
use App\Models\LandParcelSection;
use App\Models\Lease;
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

function lifecycleOrg(User $owner): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Acme Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
}

function lifecycleTenantUrl(Organization $organization, string $path): string
{
    $domain = $organization->tenant->domains()->value('domain');

    return "http://{$domain}{$path}";
}

function lifecycleParcel(Organization $organization, User $owner): LandParcel
{
    return app(LandParcelService::class)->create($organization, $owner, [
        'name' => 'Ruiru Plot A',
        'zoning' => 'residential',
        'status' => 'active',
    ]);
}

function lifecycleSection(Organization $organization, LandParcel $parcel, User $owner): LandParcelSection
{
    return $parcel->sections()->create([
        'name' => 'Block A',
        'status' => 'vacant',
        'created_by' => $owner->id,
    ]);
}

function lifecycleLease(object $test, Organization $organization, LandParcel $parcel, LandParcelSection $section, User $owner): Lease
{
    $response = $test->actingAs($owner)->post(lifecycleTenantUrl($organization, "/land-parcels/{$parcel->slug}/sections/{$section->id}/leases"), [
        'tenant_name' => 'Jane Doe',
        'tenant_email' => 'jane@example.com',
        'rent_amount' => 45000,
        'rent_frequency' => 'monthly',
        'currency' => 'KES',
        'starts_at' => now()->toDateString(),
    ]);
    $response->assertRedirect();

    return Lease::where('land_parcel_section_id', $section->id)->latest('id')->firstOrFail();
}

test('a vacant section can be reserved and released', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = lifecycleOrg($owner);
    $parcel = lifecycleParcel($organization, $owner);
    $section = lifecycleSection($organization, $parcel, $owner);

    $this->actingAs($owner)
        ->patch(lifecycleTenantUrl($organization, "/land-parcels/{$parcel->slug}/sections/{$section->id}/status"), [
            'status' => 'reserved',
        ])->assertRedirect();

    expect($section->refresh()->status)->toBe('reserved');

    $this->actingAs($owner)
        ->patch(lifecycleTenantUrl($organization, "/land-parcels/{$parcel->slug}/sections/{$section->id}/status"), [
            'status' => 'vacant',
        ])->assertRedirect();

    expect($section->refresh()->status)->toBe('vacant');
});

test('leased sections cannot change status without ending the lease', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = lifecycleOrg($owner);
    $parcel = lifecycleParcel($organization, $owner);
    $section = lifecycleSection($organization, $parcel, $owner);
    lifecycleLease($this, $organization, $parcel, $section, $owner);

    $this->actingAs($owner)
        ->patch(lifecycleTenantUrl($organization, "/land-parcels/{$parcel->slug}/sections/{$section->id}/status"), [
            'status' => 'reserved',
        ])->assertRedirect();

    assertErrorToast();
    expect($section->refresh()->status)->toBe('leased');
});

test('leasing an already-leased section is refused', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = lifecycleOrg($owner);
    $parcel = lifecycleParcel($organization, $owner);
    $section = lifecycleSection($organization, $parcel, $owner);
    lifecycleLease($this, $organization, $parcel, $section, $owner);

    $this->actingAs($owner)
        ->post(lifecycleTenantUrl($organization, "/land-parcels/{$parcel->slug}/sections/{$section->id}/leases"), [
            'tenant_name' => 'Second Tenant',
            'tenant_email' => 'second@example.com',
            'rent_amount' => 45000,
            'starts_at' => now()->toDateString(),
        ])->assertRedirect();

    assertErrorToast();
    expect(Lease::where('land_parcel_section_id', $section->id)->count())->toBe(1);
});

test('ending a section lease frees the section back to vacant', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = lifecycleOrg($owner);
    $parcel = lifecycleParcel($organization, $owner);
    $section = lifecycleSection($organization, $parcel, $owner);
    $lease = lifecycleLease($this, $organization, $parcel, $section, $owner);

    $this->actingAs($owner)
        ->post(lifecycleTenantUrl($organization, "/land-parcels/{$parcel->slug}/sections/{$section->id}/leases/end"), [
            'password' => 'password',
        ])->assertRedirect();

    expect($lease->refresh()->status)->toBe('ended')
        ->and($section->refresh()->status)->toBe('vacant');
});

test('ending a section lease requires the password', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = lifecycleOrg($owner);
    $parcel = lifecycleParcel($organization, $owner);
    $section = lifecycleSection($organization, $parcel, $owner);
    $lease = lifecycleLease($this, $organization, $parcel, $section, $owner);

    $this->actingAs($owner)
        ->post(lifecycleTenantUrl($organization, "/land-parcels/{$parcel->slug}/sections/{$section->id}/leases/end"), [
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('password');

    expect($lease->refresh()->status)->toBe('active')
        ->and($section->refresh()->status)->toBe('leased');
});

test('ending a section without an active lease is refused', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = lifecycleOrg($owner);
    $parcel = lifecycleParcel($organization, $owner);
    $section = lifecycleSection($organization, $parcel, $owner);

    $this->actingAs($owner)
        ->post(lifecycleTenantUrl($organization, "/land-parcels/{$parcel->slug}/sections/{$section->id}/leases/end"), [
            'password' => 'password',
        ])->assertRedirect();

    assertErrorToast();
});

test('the parcel show page carries the active lease per section', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = lifecycleOrg($owner);
    $parcel = lifecycleParcel($organization, $owner);
    $section = lifecycleSection($organization, $parcel, $owner);
    lifecycleLease($this, $organization, $parcel, $section, $owner);

    $this->actingAs($owner)
        ->get(lifecycleTenantUrl($organization, "/land-parcels/{$parcel->slug}"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/land-parcels/show')
            ->where('sections.0.status', 'leased')
            ->where('sections.0.active_lease.tenant_name', 'Jane Doe')
            ->where('sections.0.active_lease', fn ($lease) => (float) $lease['rent_amount'] === 45000.0));
});

test('a branch-delegated caretaker can reserve a section in their branch', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = lifecycleOrg($owner);
    $parcel = lifecycleParcel($organization, $owner);
    $branch = $organization->branches()->create(['name' => 'Ruiru', 'slug' => 'ruiru']);
    $parcel->update(['branch_id' => $branch->id]);
    $section = lifecycleSection($organization, $parcel, $owner);

    $staff = User::factory()->create(['onboarded_at' => now()]);
    $staff->organizations()->attach($organization->id, [
        'is_owner' => false,
        'sub_role_id' => $organization->subRoles()->where('slug', 'caretaker')->firstOrFail()->id,
        'status' => 'active',
    ]);

    $this->actingAs($owner)
        ->put(lifecycleTenantUrl($organization, "/staff/{$staff->id}"), [
            'name' => $staff->name,
            'email' => $staff->email,
            'sub_role_id' => $staff->membershipFor($organization)->sub_role_id,
            'branch_ids' => [$branch->id],
        ])->assertRedirect();

    $this->actingAs($staff)
        ->patch(lifecycleTenantUrl($organization, "/land-parcels/{$parcel->slug}/sections/{$section->id}/status"), [
            'status' => 'reserved',
        ])->assertRedirect();

    expect($section->refresh()->status)->toBe('reserved');
});
