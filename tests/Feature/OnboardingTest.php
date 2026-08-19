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

test('onboarding page renders with plans for a new user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('onboarding.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/onboarding')
            ->has('plans'));
});

test('organization onboarding creates a tenant, org, sub-roles and owner', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('onboarding.complete'), [
            'account_type' => 'organization',
            'name' => 'Jane Doe',
            'phone' => '+254712345678',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_name' => 'Sunset Apartments',
            'currency' => 'KES',
            'plan_slug' => 'free',
        ])->assertRedirect('http://sunset-apartments.malimanager.test/properties/create');

    $user->refresh();
    expect($user->isOnboarded())->toBeTrue()
        ->and($user->hasRole(PlatformRole::OrganizationOwner->value))->toBeTrue();

    $organization = Organization::where('name', 'Sunset Apartments')->first();
    expect($organization)->not->toBeNull()
        ->and($organization->plan->slug)->toBe('free')
        ->and($organization->settings['currency'])->toBe('KES')
        ->and($organization->users)->toHaveCount(1)
        ->and($organization->users->first()->pivot->is_owner)->toBeTrue()
        ->and($organization->tenant->domains()->value('domain'))
        ->toBe('sunset-apartments.malimanager.test');
});

test('cross-domain redirect to tenant uses 409 X-Inertia-Location for Inertia requests', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->withHeader('X-Inertia-Version', '1')
        ->post(route('onboarding.complete'), [
            'account_type' => 'organization',
            'name' => 'Jane Doe',
            'phone' => '+254712345678',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_name' => 'Sunset Apartments',
            'currency' => 'KES',
            'plan_slug' => 'free',
        ])->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'http://sunset-apartments.malimanager.test/properties/create');
});

test('occupant onboarding creates a person and links it to the user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('onboarding.complete'), [
            'account_type' => 'occupant',
            'name' => 'Bob Tenant',
            'phone' => '+254700000000',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard'));

    $user->refresh();
    expect($user->isOnboarded())->toBeTrue()
        ->and($user->hasRole(PlatformRole::Tenant->value))->toBeTrue();

    $person = Person::where('email', $user->email)->first();
    expect($person)->not->toBeNull()
        ->and($user->person_id)->toBe($person->id);
});

test('organization onboarding requires a plan', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('onboarding.complete'), [
            'account_type' => 'organization',
            'name' => 'Jane Doe',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_name' => 'Sunset Apartments',
            'currency' => 'KES',
        ])->assertSessionHasErrors('plan_slug');

    expect($user->refresh()->isOnboarded())->toBeFalse();
});

test('existing organization owners can update their settings', function () {
    $user = User::factory()->create();
    $organization = app(TenantService::class)->createOrganization(
        owner: $user,
        name: 'Sunset Apartments',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );

    $this->actingAs($user)
        ->post(route('onboarding.complete'), [
            'name' => $user->name,
            'phone' => null,
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_name' => 'Sunset Apartments Ltd',
            'currency' => 'KES',
            'plan_slug' => 'free',
        ])->assertRedirect('http://sunset-apartments.malimanager.test/properties/create');

    $organization->refresh();
    expect($organization->name)->toBe('Sunset Apartments Ltd')
        ->and($organization->settings['currency'])->toBe('KES')
        ->and($user->refresh()->isOnboarded())->toBeTrue();
});

test('onboarding page redirects already onboarded users', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);

    $this->actingAs($user)
        ->get(route('onboarding.show'))
        ->assertRedirect(route('dashboard'));
});

test('organization owner with a single property is sent straight into it', function () {
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
        ->assertRedirect('http://sunset-apartments.malimanager.test/sunset-heights/dashboard');
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
        ->assertRedirect('http://sunset-apartments.malimanager.test/properties');
});
