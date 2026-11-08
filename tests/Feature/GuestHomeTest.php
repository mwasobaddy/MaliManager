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
