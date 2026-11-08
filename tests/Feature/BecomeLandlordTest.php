<?php

use App\Enums\PlatformRole;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Plan;
use App\Models\User;
use App\Services\TenantService;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

test('guest becomes a landlord with an organization and tenancy', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);

    $this->actingAs($user)
        ->post(route('guest.become-landlord'), [
            'organization_name' => 'Sunset Apartments',
            'currency' => 'KES',
            'plan_slug' => 'free',
        ])->assertRedirect('http://sunset-apartments.malimanager.test/setup/first-asset');

    $user->refresh();
    expect($user->hasRole(PlatformRole::OrganizationOwner->value))->toBeTrue();

    $organization = Organization::where('name', 'Sunset Apartments')->first();
    expect($organization)->not->toBeNull()
        ->and($organization->plan->slug)->toBe('free')
        ->and($organization->settings['currency'])->toBe('KES')
        ->and($organization->users)->toHaveCount(1)
        ->and($organization->users->first()->pivot->is_owner)->toBeTrue()
        ->and($organization->tenant->domains()->value('domain'))
        ->toBe('sunset-apartments.malimanager.test');
});

test('landlord upgrade honours the public listing opt-in', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);

    $this->actingAs($user)
        ->post(route('guest.become-landlord'), [
            'organization_name' => 'Open Estates',
            'currency' => 'KES',
            'plan_slug' => 'free',
            'list_publicly' => true,
        ])->assertRedirect();

    expect(Organization::where('name', 'Open Estates')->first()->settings['list_publicly'])->toBeTrue();
});

test('landlord upgrade requires a plan', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);

    $this->actingAs($user)
        ->post(route('guest.become-landlord'), [
            'organization_name' => 'Sunset Apartments',
            'currency' => 'KES',
        ])->assertSessionHasErrors('plan_slug');

    expect(Organization::count())->toBe(0);
});

test('landlord upgrade rejects a bad currency', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);

    $this->actingAs($user)
        ->post(route('guest.become-landlord'), [
            'organization_name' => 'Sunset Apartments',
            'currency' => 'KE',
            'plan_slug' => 'free',
        ])->assertSessionHasErrors('currency');
});

test('existing owners cannot provision a second organization by accident', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    app(TenantService::class)->createOrganization(
        owner: $user,
        name: 'First Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );

    $this->actingAs($user)
        ->post(route('guest.become-landlord'), [
            'organization_name' => 'Second Estates',
            'currency' => 'KES',
            'plan_slug' => 'free',
        ])->assertRedirect();

    expect(Organization::count())->toBe(1);
});

test('cross-domain redirect to tenant uses 409 X-Inertia-Location for Inertia requests', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);

    $this->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->withHeader('X-Inertia-Version', '1')
        ->post(route('guest.become-landlord'), [
            'organization_name' => 'Sunset Apartments',
            'currency' => 'KES',
            'plan_slug' => 'free',
        ])->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'http://sunset-apartments.malimanager.test/setup/first-asset');
});

test('saving a search creates the tenant identity lazily', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);

    expect($user->person_id)->toBeNull();

    $this->actingAs($user)
        ->post(route('guest.saved-searches.store'), [
            'name' => 'Kilimani 2BR',
            'filters' => ['query' => 'Kilimani'],
        ])->assertRedirect();

    $user->refresh();
    expect($user->person_id)->not->toBeNull()
        ->and($user->hasRole(PlatformRole::Searcher->value))->toBeTrue()
        ->and(Person::where('email', $user->email)->first())->not->toBeNull();
});
