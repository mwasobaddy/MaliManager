<?php

use App\Enums\PlatformRole;
use App\Models\Organization;
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

function assistantViewsOrganization(User $owner): Organization
{
    $organization = app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Acme Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );

    $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    return $organization;
}

function assistantViewsTenantUrl(Organization $organization, string $path = ''): string
{
    $domain = $organization->tenant->domains()->value('domain');

    return "http://{$domain}{$path}";
}

test('organization assistant page renders the assistant view', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = assistantViewsOrganization($owner);

    $this->actingAs($owner)
        ->get(assistantViewsTenantUrl($organization, '/assistant'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/assistant/index'));
});

test('organization assistant history renders the history view', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = assistantViewsOrganization($owner);

    $this->actingAs($owner)
        ->get(assistantViewsTenantUrl($organization, '/assistant/history'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/assistant/history'));
});

test('searcher assistant page renders the assistant view', function () {
    $searcher = User::factory()->create(['onboarded_at' => now()]);
    $searcher->givePermissionTo('ai.use');

    $this->actingAs($searcher)
        ->get('/searcher/assistant')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('searcher/assistant/index'));
});

test('searcher assistant history renders the history view', function () {
    $searcher = User::factory()->create(['onboarded_at' => now()]);
    $searcher->givePermissionTo('ai.use');

    $this->actingAs($searcher)
        ->get('/searcher/assistant/history')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('searcher/assistant/history'));
});

test('platform assistant page renders the assistant view', function () {
    $admin = User::factory()->create(['onboarded_at' => now()]);
    $admin->assignRole(PlatformRole::Admin->value);

    $this->actingAs($admin)
        ->get('/platform/assistant')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('platform/assistant/index'));
});
