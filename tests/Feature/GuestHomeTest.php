<?php

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

test('the guest home lists the member workspaces', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Workspace Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );

    $domain = $organization->tenant->domains()->first()->domain;

    $this->actingAs($owner)
        ->get(route('guest.home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('guest/home')
            ->has('workspaces', 1)
            ->where('workspaces.0.organization_name', 'Workspace Estates')
            ->where('workspaces.0.role', 'owner')
            ->where('workspaces.0.url', "http://{$domain}/properties")
        );
});

test('the guest home hides workspaces from users without memberships', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);

    $this->actingAs($user)
        ->get(route('guest.home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('workspaces', 0));
});

test('the guest home shapes listings flat for cards', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Listed Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
    $organization->update(['settings' => ['list_publicly' => true, 'currency' => 'KES']]);
    $property = $organization->properties()->create([
        'name' => 'List Court',
        'slug' => 'list-court',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);
    $property->units()->create([
        'name' => 'A1',
        'slug' => 'a1',
        'status' => 'vacant',
        'type' => '1BR',
        'monthly_rent' => 35000,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->get(route('guest.home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('units.data.0.name', 'A1')
            ->where('units.data.0.property_name', 'List Court')
            ->where('units.data.0.organization_name', 'Listed Estates')
            ->where('units.data.0.currency', 'KES')
            ->where('units.data.0.photo_url', null)
            ->where('available_types', ['1BR'])
        );
});

test('the guest home sorts cheapest first', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Sorted Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
    $organization->update(['settings' => ['list_publicly' => true]]);
    $property = $organization->properties()->create([
        'name' => 'Sort Court',
        'slug' => 'sort-court',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);
    $property->units()->createMany([
        ['name' => 'Pricy', 'slug' => 'pricy', 'status' => 'vacant', 'monthly_rent' => 90000, 'created_by' => $owner->id],
        ['name' => 'Cheap', 'slug' => 'cheap', 'status' => 'vacant', 'monthly_rent' => 15000, 'created_by' => $owner->id],
    ]);

    $this->actingAs($owner)
        ->get(route('guest.home', ['sort' => 'cheapest']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('units.data.0.name', 'Cheap')
            ->where('units.data.1.name', 'Pricy')
        );
});
