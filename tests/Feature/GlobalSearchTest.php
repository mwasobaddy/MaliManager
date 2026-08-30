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

function makeOrganization(User $owner, string $name): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $owner,
        name: $name,
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
}

function tenantSearchUrl(Organization $organization, string $q): string
{
    $domain = $organization->tenant->domains()->value('domain');

    return "http://{$domain}/search?q=".urlencode($q);
}

test('search is tenant-scoped to the active organization', function () {
    $ownerA = User::factory()->create(['onboarded_at' => now()]);
    $orgA = makeOrganization($ownerA, 'Org A');

    $ownerB = User::factory()->create(['onboarded_at' => now()]);
    $orgB = makeOrganization($ownerB, 'Org B');

    Property::factory()->create(['organization_id' => $orgA->id, 'name' => 'Garden View']);
    Property::factory()->create(['organization_id' => $orgB->id, 'name' => 'Garden View']);

    $response = $this
        ->actingAs($ownerA)
        ->get(tenantSearchUrl($orgA, 'Garden'))
        ->assertOk();

    $properties = $response->json('results.properties');

    expect($properties)->toHaveCount(1)
        ->and($properties[0]['url'])->toContain((string) $orgA->tenant->domains()->value('domain'));
});

test('central platform admin searches across every organization', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $ownerA = User::factory()->create(['onboarded_at' => now()]);
    $orgA = makeOrganization($ownerA, 'Org A');
    Property::factory()->create(['organization_id' => $orgA->id, 'name' => 'Alpha Base']);

    $ownerB = User::factory()->create(['onboarded_at' => now()]);
    $orgB = makeOrganization($ownerB, 'Org B');
    Property::factory()->create(['organization_id' => $orgB->id, 'name' => 'Alpha Site']);

    $response = $this
        ->actingAs($admin)
        ->get(route('search', ['q' => 'Alpha']))
        ->assertOk();

    $properties = $response->json('results.properties');

    expect($properties)->toHaveCount(2);

    $orgIds = collect($properties)->map(function ($p) use ($orgA, $orgB) {
        return str_contains($p['url'], (string) $orgA->tenant->domains()->value('domain')) ? $orgA->id : $orgB->id;
    });

    expect($orgIds)->toContain($orgA->id)
        ->and($orgIds)->toContain($orgB->id);
});

test('central non-admin searcher is scoped to their own organizations', function () {
    $searcher = User::factory()->create();

    $ownerA = User::factory()->create(['onboarded_at' => now()]);
    $orgA = makeOrganization($ownerA, 'Org A');
    Property::factory()->create(['organization_id' => $orgA->id, 'name' => 'Private Villa']);

    $this
        ->actingAs($searcher)
        ->get(route('search', ['q' => 'Private']))
        ->assertOk()
        ->assertJson(['results' => []]);
});

test('search requires a query of at least two characters', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this
        ->actingAs($admin)
        ->getJson(route('search', ['q' => 'a']))
        ->assertStatus(422);
});
