<?php

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Stancl\Tenancy\Database\Models\ImpersonationToken;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
});

test('guests are redirected from the admin dashboard', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});

test('non-admin users are forbidden from the admin dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertRedirect();
    assertErrorToast();
});

test('platform admins can view the admin dashboard', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/dashboard'));
});

test('admin dashboard lists organizations and their members', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->users()->attach($member->id, ['is_owner' => true, 'status' => 'active']);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/dashboard')
            ->where('organizations.0.name', $organization->name)
            ->where('organizations.0.users.0.email', $member->email));
});

test('admins can generate an impersonation redirect for an organization member', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $organization = Organization::factory()->create();
    $organization->tenant->domains()->create(['domain' => 'acme.malimanager.test']);
    $member = User::factory()->create(['onboarded_at' => now()]);
    $organization->users()->attach($member->id, ['is_owner' => true, 'status' => 'active']);

    $response = $this->actingAs($admin)
        ->post(route('admin.impersonate', [$organization, $member]));

    $response->assertRedirect('http://acme.malimanager.test/impersonate/'.app(ImpersonationToken::class)->latest('created_at')->first()->token);
});

test('admins cannot impersonate users outside the organization', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $organization = Organization::factory()->create();
    $stranger = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.impersonate', [$organization, $stranger]))
        ->assertRedirect();
    assertErrorToast();
});

test('admins cannot impersonate other admins', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $otherAdmin = User::factory()->create();
    $otherAdmin->assignRole('admin');

    $organization = Organization::factory()->create();
    $organization->users()->attach($otherAdmin->id, ['is_owner' => false, 'status' => 'active']);

    $this->actingAs($admin)
        ->post(route('admin.impersonate', [$organization, $otherAdmin]))
        ->assertRedirect();
    assertErrorToast();
});

test('impersonated user is logged in on the tenant domain', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $organization = Organization::factory()->create();
    $organization->tenant->domains()->create(['domain' => 'acme.malimanager.test']);
    $member = User::factory()->create(['onboarded_at' => now()]);
    $organization->users()->attach($member->id, ['is_owner' => true, 'status' => 'active']);

    $this->actingAs($admin)
        ->post(route('admin.impersonate', [$organization, $member]));

    $token = app(ImpersonationToken::class)->latest('created_at')->firstOrFail();

    $response = $this->get("http://acme.malimanager.test/impersonate/{$token->token}");

    $response->assertRedirect('http://acme.malimanager.test/properties');
    $this->assertAuthenticatedAs($member);
});
