<?php

use App\Models\Organization;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantService;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
});

test('TenantService creates a full organization tenancy', function () {
    $owner = User::factory()->create();
    $plan = Plan::factory()->free()->create();

    $organization = app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Sunset Apartments',
        plan: $plan,
        email: 'owner@sunset.example',
    );

    expect($organization)->toBeInstanceOf(Organization::class)
        ->and($organization->exists)->toBeTrue()
        ->and(Tenant::find($organization->tenant_id))->not->toBeNull()
        ->and($organization->subRoles)->toHaveCount(3)
        ->and($organization->users)->toHaveCount(1)
        ->and($organization->users->first()->pivot->is_owner)->toBeTrue()
        ->and($owner->hasRole('organization-owner'))->toBeTrue();
});

test('organization slugs are unique', function () {
    $owner = User::factory()->create();
    $plan = Plan::factory()->free()->create();
    $service = app(TenantService::class);

    $first = $service->createOrganization($owner, 'Sunset Apartments', $plan);
    $second = $service->createOrganization($owner, 'Sunset Apartments', $plan);

    expect($first->slug)->toBe('sunset-apartments')
        ->and($second->slug)->toBe('sunset-apartments-1');
});
