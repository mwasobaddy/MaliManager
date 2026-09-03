<?php

use App\Enums\PlatformRole;
use App\Models\Organization;
use App\Models\SubRole;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\StaffService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeOnboardedUser(): User
{
    return User::factory()->create(['onboarded_at' => now(), 'email_verified_at' => now()]);
}

it('shows admin stats only to users with the advanced metrics permission', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = makeOnboardedUser();
    $admin->assignRole(PlatformRole::Admin->value);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('admin')
            ->where('admin.organizations_count', 0)
            ->where('defaultTab', 'admin'));
});

it('returns null sections for users without the relevant permissions', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $user = makeOnboardedUser();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('admin', null)
            ->where('organization', null)
            ->where('searcher', null)
            ->where('occupant', null));
});

it('grants the organization tab to owners and staff but not searchers', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $owner = makeOnboardedUser();
    $owner->assignRole(PlatformRole::OrganizationOwner->value);

    $searcher = makeOnboardedUser();
    $searcher->assignRole(PlatformRole::Searcher->value);

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->has('organization')->where('defaultTab', 'organization'));

    $this->actingAs($searcher)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('organization', null)->has('searcher'));
});

it('grants the organization metrics permission to new staff members', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $owner = makeOnboardedUser();
    $organization = Organization::factory()->create();
    $organization->users()->attach($owner->id, ['is_owner' => true, 'status' => 'active']);
    $subRole = SubRole::factory()->create();

    $staff = app(StaffService::class)->create($organization, $owner, [
        'name' => 'Staffer',
        'email' => 'staff-'.uniqid().'@example.com',
        'phone' => '123456',
        'sub_role_id' => $subRole->id,
        'property_ids' => [],
    ]);

    expect($staff->can('view organization metrics'))->toBeTrue();
});

it('builds real monthly series and ytd totals for the admin payload', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = makeOnboardedUser();
    $admin->assignRole(PlatformRole::Admin->value);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('admin.financials_monthly')
            ->where('admin.income_total', 0)
            ->where('admin.expenses_total', 0)
            ->where('admin.net_total', 0)
            ->has('available_years')
            ->etc())
        ->assertInertia(fn ($page) => $page
            ->where('admin.financials_monthly.0.label', 'Jan')
            ->has('admin.financials_monthly.0.income')
            ->has('admin.financials_monthly.0.expenses'));
});

it('returns daily labels when a month filter is selected', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = makeOnboardedUser();
    $admin->assignRole(PlatformRole::Admin->value);

    SubscriptionPayment::factory()->create([
        'received_on' => '2026-03-15',
        'amount' => 100,
    ]);

    $this->actingAs($admin)
        ->get(route('dashboard', ['year' => 2026, 'month' => 3]))
        ->assertInertia(fn ($page) => $page
            ->has('admin.financials_monthly.0')
            ->where('admin.financials_monthly.0.label', '1')
            ->where('admin.financials_monthly.14.label', '15')
            ->where('admin.financials_monthly.14.income', 100)
            ->has('admin.financials_monthly.30')
            ->where('admin.financials_monthly.30.label', '31')
            ->has('admin.income_total'));
});

it('builds merged operations series for the organization payload', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $owner = makeOnboardedUser();
    $owner->assignRole(PlatformRole::OrganizationOwner->value);

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('organization.operations_monthly')
            ->where('organization.operations_monthly.0.label', 'Jan')
            ->has('organization.operations_monthly.0.expenses')
            ->has('organization.operations_monthly.0.maintenance'));
});
