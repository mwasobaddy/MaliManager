<?php

use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Services\TenantService;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

function modeOrganization(User $owner, string $name = 'Acme Estates'): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $owner,
        name: $name,
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
}

function modeTenantUrl(Organization $organization, string $path = ''): string
{
    $domain = $organization->tenant->domains()->value('domain');

    return "http://{$domain}{$path}";
}

test('personal landlords start in simple mode', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);

    $this->actingAs($user)
        ->post(route('guest.become-landlord'), [
            'organization_name' => 'Solo Rentals',
            'currency' => 'KES',
            'mode' => 'simple',
        ])->assertRedirect();

    expect(Organization::where('name', 'Solo Rentals')->firstOrFail()->mode())->toBe('simple');
});

test('companies start in pro mode', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);

    $this->actingAs($user)
        ->post(route('guest.become-landlord'), [
            'organization_name' => 'Acme Property Group',
            'currency' => 'KES',
            'mode' => 'pro',
        ])->assertRedirect();

    expect(Organization::where('name', 'Acme Property Group')->firstOrFail()->mode())->toBe('pro');
});

test('mode choice is required and constrained', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);

    $this->actingAs($user)
        ->post(route('guest.become-landlord'), [
            'organization_name' => 'Missing Mode',
            'currency' => 'KES',
        ])->assertSessionHasErrors('mode');

    $this->actingAs($user)
        ->post(route('guest.become-landlord'), [
            'organization_name' => 'Bad Mode',
            'currency' => 'KES',
            'mode' => 'enterprise',
        ])->assertSessionHasErrors('mode');
});

test('organizations created before the question default to pro', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = modeOrganization($owner);

    expect($organization->mode())->toBe('pro')
        ->and($organization->isSimpleMode())->toBeFalse();
});

test('owners can switch the interface mode', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = modeOrganization($owner);
    $organization->settings = ['mode' => 'simple'];
    $organization->save();

    $this->actingAs($owner)
        ->put(modeTenantUrl($organization, '/security-policy'), [
            'mode' => 'pro',
        ])->assertRedirect();

    expect($organization->refresh()->mode())->toBe('pro');
});

test('switching mode does not reset the two-factor policy', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = modeOrganization($owner);
    $organization->settings = ['mode' => 'simple', 'require_staff_two_factor' => true];
    $organization->save();

    $this->actingAs($owner)
        ->put(modeTenantUrl($organization, '/security-policy'), [
            'mode' => 'pro',
        ])->assertRedirect();

    $organization->refresh();
    expect($organization->mode())->toBe('pro')
        ->and($organization->requiresStaffTwoFactor())->toBeTrue();
});

test('staff cannot switch the interface mode', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = modeOrganization($owner);
    $organization->settings = ['mode' => 'simple'];
    $organization->save();

    $manager = User::factory()->create(['onboarded_at' => now()]);
    $manager->organizations()->attach($organization->id, [
        'is_owner' => false,
        'sub_role_id' => $organization->subRoles()->where('slug', 'landlord')->firstOrFail()->id,
        'status' => 'active',
    ]);

    $this->actingAs($manager)
        ->put(modeTenantUrl($organization, '/security-policy'), [
            'mode' => 'pro',
        ])->assertRedirect();

    assertErrorToast();
    expect($organization->refresh()->mode())->toBe('simple');
});

test('a second asset suggests pro mode', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = modeOrganization($owner);
    $organization->settings = ['mode' => 'simple'];
    $organization->save();

    expect($organization->refresh()->suggestsProMode())->toBeFalse();

    $organization->properties()->create([
        'name' => 'First Estate',
        'slug' => 'first-estate',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);
    $organization->properties()->create([
        'name' => 'Second Estate',
        'slug' => 'second-estate',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    expect($organization->refresh()->suggestsProMode())->toBeTrue();
});

test('staff presence suggests pro mode and dismissal clears it', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = modeOrganization($owner);
    $organization->settings = ['mode' => 'simple'];
    $organization->save();

    $staff = User::factory()->create(['onboarded_at' => now()]);
    $staff->organizations()->attach($organization->id, [
        'is_owner' => false,
        'sub_role_id' => $organization->subRoles()->where('slug', 'caretaker')->firstOrFail()->id,
        'status' => 'active',
    ]);

    expect($organization->refresh()->suggestsProMode())->toBeTrue();

    $this->actingAs($owner)
        ->put(modeTenantUrl($organization, '/security-policy'), [
            'dismiss_pro_suggestion' => true,
        ])->assertRedirect();

    expect($organization->refresh()->suggestsProMode())->toBeFalse();
});

test('shared context exposes the mode and suggestion', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = modeOrganization($owner);
    $organization->settings = ['mode' => 'simple'];
    $organization->save();

    $this->actingAs($owner)
        ->get(modeTenantUrl($organization, '/roles'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('context.organization.mode', 'simple')
            ->where('context.organization.suggest_pro_mode', false)
            ->where('security.mode', 'simple'));
});
