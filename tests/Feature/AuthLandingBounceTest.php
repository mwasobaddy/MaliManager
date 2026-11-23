<?php

use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Services\TenantService;
use App\Support\AuthLanding;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Request;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

function bounceOrganization(User $owner, string $name, string $slug): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $owner,
        name: $name,
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
}

function bounceDomain(Organization $organization): string
{
    return (string) $organization->tenant->domains()->value('domain');
}

function tenantRequest(string $domain, string $path = '/overview'): Request
{
    return Request::create("http://{$domain}{$path}", 'GET');
}

test('a non-member bounces to their own tenant overview with a warning', function () {
    $ownerA = User::factory()->create(['onboarded_at' => now()]);
    $orgA = bounceOrganization($ownerA, 'Alpha Estates', 'alpha-bounce');

    $ownerB = User::factory()->create(['onboarded_at' => now()]);
    $orgB = bounceOrganization($ownerB, 'Beta Estates', 'beta-bounce');

    tenancy()->initialize($orgA->tenant);

    $landing = AuthLanding::for($ownerB, tenantRequest(bounceDomain($orgA)));

    expect($landing)->toBe('http://'.bounceDomain($orgB).'/overview');

    $flash = session()->get('inertia.flash_data', []);

    expect($flash['toast']['type'] ?? null)->toBe('warning')
        ->and($flash['toast']['message'] ?? '')->toContain('no access');
});

test('a member stays on the organization instead of bouncing', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = bounceOrganization($owner, 'Gamma Estates', 'gamma-bounce');

    tenancy()->initialize($organization->tenant);

    $landing = AuthLanding::for($owner, tenantRequest(bounceDomain($organization)));

    // No properties or parcels yet, so the owner falls through to the
    // first-asset funnel step on their own domain — never another org.
    expect($landing)->toBe('http://'.bounceDomain($organization).'/setup/first-asset');

    $flash = session()->get('inertia.flash_data', []);

    expect($flash['toast']['type'] ?? null)->not->toBe('warning');
});

test('a platform admin passes through without bouncing', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = bounceOrganization($owner, 'Delta Estates', 'delta-bounce');

    $admin = User::factory()->create(['onboarded_at' => now()]);
    $admin->assignRole('admin');

    tenancy()->initialize($organization->tenant);

    $landing = AuthLanding::for($admin, tenantRequest(bounceDomain($organization)));

    expect($landing)->toBe(route('dashboard'));

    $flash = session()->get('inertia.flash_data', []);

    expect($flash)->toBe([]);
});

test('a user with no organization falls through to the default landing', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = bounceOrganization($owner, 'Epsilon Estates', 'epsilon-bounce');

    $drifter = User::factory()->create(['onboarded_at' => now()]);

    tenancy()->initialize($organization->tenant);

    $landing = AuthLanding::for($drifter, tenantRequest(bounceDomain($organization)));

    expect($landing)->toBe(route('dashboard'));

    $flash = session()->get('inertia.flash_data', []);

    expect($flash)->toBe([]);
});

test('an inactive membership bounces like a non-member', function () {
    $ownerA = User::factory()->create(['onboarded_at' => now()]);
    $orgA = bounceOrganization($ownerA, 'Zeta Estates', 'zeta-bounce');

    $ownerB = User::factory()->create(['onboarded_at' => now()]);
    $orgB = bounceOrganization($ownerB, 'Eta Estates', 'eta-bounce');

    $orgA->users()->attach($ownerB->id, ['is_owner' => false, 'status' => 'inactive']);

    tenancy()->initialize($orgA->tenant);

    $landing = AuthLanding::for($ownerB, tenantRequest(bounceDomain($orgA)));

    expect($landing)->toBe('http://'.bounceDomain($orgB).'/overview');

    $flash = session()->get('inertia.flash_data', []);

    expect($flash['toast']['type'] ?? null)->toBe('warning');
});
