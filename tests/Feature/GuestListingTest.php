<?php

use App\Models\Organization;
use App\Models\Plan;
use App\Models\Unit;
use App\Models\User;
use App\Services\TenantService;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

function publicOrganization(User $owner, string $name): Organization
{
    $organization = app(TenantService::class)->createOrganization(
        owner: $owner,
        name: $name,
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
    $organization->update(['settings' => ['list_publicly' => true, 'currency' => 'KES']]);

    return $organization;
}

function publicUnit(Organization $organization, array $overrides = []): Unit
{
    $property = $organization->properties()->create([
        'name' => 'Detail Court',
        'slug' => 'detail-court-'.str()->random(6),
        'status' => 'active',
        'city' => 'Nairobi',
        'created_by' => $organization->users()->first()->id,
    ]);

    return Unit::factory()->create(array_merge([
        'property_id' => $property->id,
        'name' => 'B2',
        'status' => 'vacant',
        'type' => '2BR',
        'monthly_rent' => 45000,
        'deposit' => 45000,
    ], $overrides));
}

test('the public listing page renders a listed unit with photos and facts', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = publicOrganization($owner, 'Open Estates');
    $unit = publicUnit($organization);
    $unit->addMedia(File::fake()->image('b2.jpg'))->toMediaCollection('photos');

    $this->actingAs(User::factory()->create(['onboarded_at' => now()]))
        ->get(route('guest.listings.show', ['unit' => $unit->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('guest/listing')
            ->where('unit.name', 'B2')
            ->where('unit.type', '2BR')
            ->where('unit.monthly_rent', '45000.00')
            ->where('unit.currency', 'KES')
            ->where('unit.property_name', 'Detail Court')
            ->where('unit.property_city', 'Nairobi')
            ->where('unit.organization_name', 'Open Estates')
            ->where('unit.landlord_since', (int) now()->format('Y'))
            ->where('unit.trust.photos', true)
            ->where('unit.trust.deposit', true)
            ->has('unit.photos', 1)
        );
});

test('the public listing page hides unpublished units', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = publicOrganization($owner, 'Open Estates');

    $occupied = publicUnit($organization, ['status' => 'occupied']);
    $heldBack = publicUnit($organization, ['list_publicly' => false]);

    $quiet = app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Quiet Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
    $optedOut = publicUnit($quiet);

    $viewer = User::factory()->create(['onboarded_at' => now()]);

    foreach ([$occupied, $heldBack, $optedOut] as $unit) {
        $this->actingAs($viewer)
            ->get(route('guest.listings.show', ['unit' => $unit->id]))
            ->assertNotFound();
    }

    $this->actingAs($viewer)
        ->get(route('guest.listings.show', ['unit' => 999999]))
        ->assertNotFound();
});

test('trust badges stay empty when there is nothing to show', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = publicOrganization($owner, 'Open Estates');
    $unit = publicUnit($organization, ['deposit' => null]);

    $this->actingAs(User::factory()->create(['onboarded_at' => now()]))
        ->get(route('guest.listings.show', ['unit' => $unit->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('unit.trust.photos', false)
            ->where('unit.trust.deposit', false)
            ->where('unit.trust.landlord_since', (int) now()->format('Y'))
        );
});
