<?php

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

test('un-onboarded user is redirected to onboarding from dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('onboarding.show'));
});

test('onboarded user can access the dashboard', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('onboarding page renders without plans for a new user', function () {
    $user = User::factory()->create();

    // Plan choice moved to the landlord upgrade; onboarding is profile-only.
    $this->actingAs($user)
        ->get(route('onboarding.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/onboarding')
            ->missing('plans'));
});

test('guest onboarding completes with profile details only', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('onboarding.complete'), [
            'name' => 'Jane Doe',
            'phone' => '+254712345678',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('guest.home'));

    $user->refresh();
    expect($user->isOnboarded())->toBeTrue()
        ->and($user->name)->toBe('Jane Doe')
        // No org, no role, no person: a guest is just an identity.
        ->and($user->organizations()->count())->toBe(0)
        ->and($user->person_id)->toBeNull()
        ->and(Organization::count())->toBe(0)
        ->and(Person::count())->toBe(0);
});

test('onboarding ignores account type fields from the old flow', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('onboarding.complete'), [
            'account_type' => 'organization',
            'organization_name' => 'Should Not Exist',
            'currency' => 'KES',
            'plan_slug' => 'free',
            'name' => 'Jane Doe',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('guest.home'));

    expect(Organization::count())->toBe(0)
        ->and($user->refresh()->isOnboarded())->toBeTrue();
});

test('onboarding page redirects already onboarded users', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);

    $this->actingAs($user)
        ->get(route('onboarding.show'))
        ->assertRedirect(route('guest.home'));
});

test('organization owner with a single property lands on the picker', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $organization = app(TenantService::class)->createOrganization(
        owner: $user,
        name: 'Sunset Apartments',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
    $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('onboarding.show'))
        ->assertRedirect(route('dashboard'));
});

test('organization owner with multiple properties lands on the picker', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $organization = app(TenantService::class)->createOrganization(
        owner: $user,
        name: 'Sunset Apartments',
        plan: Plan::where('slug', 'starter')->firstOrFail(),
    );
    foreach (['sunset-heights', 'ocean-view'] as $slug) {
        $organization->properties()->create([
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'status' => 'active',
            'created_by' => $user->id,
        ]);
    }

    $this->actingAs($user)
        ->get(route('onboarding.show'))
        ->assertRedirect(route('dashboard'));
});

test('first asset page offers both building and land parcel', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $organization = app(TenantService::class)->createOrganization(
        owner: $user,
        name: 'Setup Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );

    $this->actingAs($user)
        ->get(route('onboarding.first-asset'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('setup/first-asset')
            ->has('organization'));
});

test('first asset page without an organization returns to become-landlord', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);

    $this->actingAs($user)
        ->get(route('onboarding.first-asset'))
        ->assertRedirect(route('guest.become-landlord.show'));
});
