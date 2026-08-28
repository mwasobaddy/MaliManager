<?php

use App\Models\AgreementTemplate;
use App\Models\LandParcel;
use App\Models\LandParcelSection;
use App\Models\Lease;
use App\Models\Organization;
use App\Models\Person;
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

function sectionOrg(User $owner): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Acme Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
}

function sectionParcel(Organization $organization, User $owner): LandParcel
{
    return app(LandParcelService::class)->create($organization, $owner, [
        'name' => 'Ruiru Plot A',
        'zoning' => 'residential',
        'status' => 'active',
    ]);
}

test('owner can add a section to a parcel', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = sectionOrg($owner);
    $parcel = sectionParcel($organization, $owner);

    $this->actingAs($owner)
        ->post(parcelUrl($organization, "/land-parcels/{$parcel->slug}/sections"), [
            'name' => 'Block A',
            'area' => 2.5,
            'status' => 'vacant',
        ])
        ->assertRedirect(parcelUrl($organization, "/land-parcels/{$parcel->slug}"));

    expect($parcel->sections()->where('name', 'Block A')->exists())->toBeTrue();
});

test('owner can remove a section', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = sectionOrg($owner);
    $parcel = sectionParcel($organization, $owner);
    $section = $parcel->sections()->create([
        'name' => 'Block A',
        'status' => 'vacant',
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->delete(parcelUrl($organization, "/land-parcels/{$parcel->slug}/sections/{$section->id}"))
        ->assertRedirect(parcelUrl($organization, "/land-parcels/{$parcel->slug}"));

    expect(LandParcelSection::find($section->id))->toBeNull();
});

test('parcel show includes sections and permission flags', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = sectionOrg($owner);
    $parcel = sectionParcel($organization, $owner);
    $parcel->sections()->create(['name' => 'Block A', 'status' => 'vacant', 'created_by' => $owner->id]);

    $this->actingAs($owner)
        ->get(parcelUrl($organization, "/land-parcels/{$parcel->slug}"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant/land-parcels/show')
            ->where('sections.0.name', 'Block A')
            ->where('canManageSections', true)
            ->where('canLease', true));
});

test('leasing a section creates a lease and generates the agreement', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = sectionOrg($owner);
    $parcel = sectionParcel($organization, $owner);
    $section = $parcel->sections()->create([
        'name' => 'Block A',
        'status' => 'vacant',
        'created_by' => $owner->id,
    ]);

    // Org-wide agreement template (no property scope).
    $template = AgreementTemplate::create([
        'organization_id' => $organization->id,
        'name' => 'Standard parcel lease',
        'body_html' => 'Lease of {{parcel_name}} / {{section_name}} for {{occupant_name}} at {{rent_amount}}.',
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(parcelUrl($organization, "/land-parcels/{$parcel->slug}/sections/{$section->id}/leases"), [
            'tenant_name' => 'Jane Doe',
            'tenant_email' => 'jane@example.com',
            'rent_amount' => 45000,
            'currency' => 'KES',
            'rent_frequency' => 'monthly',
            'starts_at' => now()->toDateString(),
        ])
        ->assertRedirect(parcelUrl($organization, "/land-parcels/{$parcel->slug}"));

    $lease = Lease::where('land_parcel_section_id', $section->id)->first();
    expect($lease)->not->toBeNull()
        ->and($lease->land_parcel_id)->toBe($parcel->id)
        ->and($lease->person_id)->toBe(Person::where('email', 'jane@example.com')->first()->id)
        ->and($lease->status)->toBe('active')
        ->and($section->fresh()->status)->toBe('leased')
        ->and($lease->agreement_text)->toContain($parcel->name)
        ->and($lease->agreement_text)->toContain($section->name)
        ->and($lease->agreement_text)->toContain('Jane Doe')
        ->and($lease->agreement_text)->toContain(number_format(45000, 2));
});

function parcelUrl(Organization $organization, string $path = ''): string
{
    $domain = $organization->tenant->domains()->value('domain');

    return "http://{$domain}{$path}";
}
