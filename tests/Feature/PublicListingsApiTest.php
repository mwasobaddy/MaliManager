<?php

use App\Models\Organization;
use App\Models\Plan;
use App\Models\Unit;
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

function listingOrganization(User $owner, string $name, bool $public): Organization
{
    $organization = app(TenantService::class)->createOrganization(
        owner: $owner,
        name: $name,
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );

    $organization->update(['settings' => ['list_publicly' => $public]]);

    return $organization;
}

function listingUnit(Organization $organization, array $overrides = []): Unit
{
    $property = $organization->properties()->create([
        'name' => 'List Court',
        'slug' => 'list-court-'.str()->random(6),
        'status' => 'active',
        'created_by' => $organization->users()->first()->id,
    ]);

    return Unit::factory()->create(array_merge([
        'property_id' => $property->id,
        'status' => 'vacant',
    ], $overrides));
}

test('the public api lists vacant units of opted-in organizations', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = listingOrganization($owner, 'Open Estates', true);
    listingUnit($organization, ['name' => 'A1', 'monthly_rent' => 25000]);

    $this->getJson('/api/listings')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'A1')
        ->assertJsonPath('data.0.monthly_rent', '25000.00')
        ->assertJsonMissing(['owner' => true]);
});

test('the public api hides occupied units', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = listingOrganization($owner, 'Open Estates', true);
    listingUnit($organization, ['status' => 'occupied']);

    $this->getJson('/api/listings')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('the public api hides units of organizations that did not opt in', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = listingOrganization($owner, 'Quiet Estates', false);
    listingUnit($organization);

    $this->getJson('/api/listings')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('the public api honours a unit-level holdback', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = listingOrganization($owner, 'Open Estates', true);
    listingUnit($organization, ['list_publicly' => false]);

    $this->getJson('/api/listings')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('the public api filters by rent range', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = listingOrganization($owner, 'Open Estates', true);
    listingUnit($organization, ['name' => 'Cheap', 'monthly_rent' => 15000]);
    listingUnit($organization, ['name' => 'Pricy', 'monthly_rent' => 90000]);

    $this->getJson('/api/listings?min_rent=20000&max_rent=50000')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->getJson('/api/listings?min_rent=10000&max_rent=50000')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Cheap');
});

test('the public api needs no authentication', function () {
    $this->getJson('/api/listings')->assertOk();
});

test('the public api sorts cheapest first with rentless units last', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = listingOrganization($owner, 'Open Estates', true);
    listingUnit($organization, ['name' => 'Pricy', 'monthly_rent' => 90000]);
    listingUnit($organization, ['name' => 'NoRent', 'monthly_rent' => null]);
    listingUnit($organization, ['name' => 'Cheap', 'monthly_rent' => 15000]);

    $this->getJson('/api/listings?sort=cheapest')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Cheap')
        ->assertJsonPath('data.1.name', 'Pricy')
        ->assertJsonPath('data.2.name', 'NoRent');
});

test('the public api rejects an unknown sort', function () {
    $this->getJson('/api/listings?sort=random')->assertStatus(422);
});

test('listing payloads carry display currency and photo slot', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = listingOrganization($owner, 'Open Estates', true);
    $organization->update(['settings' => ['list_publicly' => true, 'currency' => 'UGX']]);
    listingUnit($organization, ['name' => 'A1', 'monthly_rent' => 25000]);

    $this->getJson('/api/listings')
        ->assertOk()
        ->assertJsonPath('data.0.currency', 'UGX')
        ->assertJsonPath('data.0.photo_url', null)
        ->assertJsonPath('data.0.property_name', 'List Court')
        ->assertJsonPath('data.0.organization_name', 'Open Estates');
});
