<?php

use App\Enums\PlatformRole;
use App\Models\LandParcel;
use App\Models\Lease;
use App\Models\Occupant;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Property;
use App\Models\SubRole;
use App\Models\SubscriptionPayment;
use App\Models\Unit;
use App\Models\User;
use App\Services\StaffService;
use App\Services\TenantService;
use Database\Seeders\PlansSeeder;
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
            ->where('defaultTab', 'platform'));
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

it('reports landlord collection, aging and defaulters on the organization tab', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $owner = makeOnboardedUser();
    $owner->assignRole(PlatformRole::OrganizationOwner->value);

    $org = Organization::factory()->create();
    $owner->organizations()->attach($org->id, ['is_owner' => true, 'status' => 'active']);

    $property = Property::factory()->for($org)->create();
    Unit::factory()->create(['property_id' => $property->id, 'status' => 'occupied']);
    Unit::factory()->create(['property_id' => $property->id, 'status' => 'vacant']);

    $occupant = Occupant::factory()->create(['organization_id' => $org->id]);

    $paidLease = Lease::factory()->create([
        'organization_id' => $org->id,
        'property_id' => $property->id,
        'person_id' => $occupant->person_id,
        'status' => 'active',
        'starts_at' => now()->startOfMonth()->toDateString(),
        'ends_at' => null,
        'rent_amount' => 25000,
        'rent_due_day' => 5,
    ]);

    Payment::factory()->create([
        'organization_id' => $org->id,
        'lease_id' => $paidLease->id,
        'occupant_id' => $occupant->id,
        'amount' => 25000,
        'status' => 'received',
        'method' => 'mpesa',
        'paid_on' => now()->toDateString(),
    ]);

    $overdueLease = Lease::factory()->create([
        'organization_id' => $org->id,
        'property_id' => $property->id,
        'person_id' => $occupant->person_id,
        'status' => 'active',
        'starts_at' => now()->subMonthsNoOverflow(3)->toDateString(),
        'ends_at' => null,
        'rent_amount' => 20000,
        'rent_due_day' => 5,
    ]);

    $expiringLease = Lease::factory()->create([
        'organization_id' => $org->id,
        'property_id' => $property->id,
        'person_id' => $occupant->person_id,
        'status' => 'active',
        'starts_at' => now()->startOfMonth()->toDateString(),
        'ends_at' => now()->addDays(20)->toDateString(),
        'rent_amount' => 15000,
        'rent_due_day' => 5,
    ]);

    Payment::factory()->create([
        'organization_id' => $org->id,
        'lease_id' => $expiringLease->id,
        'occupant_id' => $occupant->id,
        'amount' => 15000,
        'status' => 'received',
        'method' => 'mpesa',
        'paid_on' => now()->toDateString(),
    ]);

    $collection = $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->viewData('page')['props']['organization']['collection'];

    expect($collection['expected_mtd'])->toBe('60000.00')
        ->and($collection['collected_mtd'])->toBe('40000.00')
        ->and($collection['collection_rate'])->toBe(66.7)
        ->and($collection['overdue_count'])->toBe(1)
        ->and($collection['occupancy_rate'])->toBe(50.0)
        ->and($collection['vacant'])->toBe(1)
        ->and($collection['defaulters'])->toHaveCount(1)
        ->and($collection['defaulters'][0]['lease_id'])->toBe($overdueLease->id)
        ->and($collection['defaulters'][0]['can_remind'])->toBeTrue()
        ->and($collection['expiry']['within_30'])->toBe(1)
        ->and($collection['properties'])->toHaveCount(1)
        ->and($collection['properties'][0]['income_mtd'])->toBe('40000.00');

    $buckets = collect($collection['aging']);
    expect((float) $buckets->sum('total'))->toBe((float) $collection['arrears_total'])
        ->and($buckets->sum('count'))->toBe(1);

    LandParcel::factory()->create(['organization_id' => $org->id]);

    $portfolio = $this->actingAs($owner)
        ->get(route('dashboard'))
        ->viewData('page')['props']['organization']['portfolio'];

    expect($portfolio['units_grid'])->toHaveCount(1)
        ->and($portfolio['units_grid'][0]['units'])->toHaveCount(2)
        ->and($portfolio['timing']['early'] + $portfolio['timing']['on_time'] + $portfolio['timing']['late'])->toBe(2)
        ->and($portfolio['pnl']['income_ytd'])->toBe('40000.00')
        ->and($portfolio['pnl']['expenses_by_category'])->toBe([])
        ->and($portfolio['land'])->toHaveCount(1)
        ->and($portfolio['land'][0]['latitude'])->not->toBeNull();

    $currentMonth = now()->format('M');
    $taxMonth = collect($portfolio['tax'])->firstWhere('label', $currentMonth);
    expect($taxMonth['total'])->toBe('40000.00');

    $otherProperty = Property::factory()->for($org)->create();
    $scoped = $this->actingAs($owner)
        ->get(route('dashboard', ['property' => $otherProperty->id]))
        ->viewData('page')['props']['organization'];

    expect($scoped['selected_property'])->toBe($otherProperty->id)
        ->and($scoped['collection']['expected_mtd'])->toBe('0.00')
        ->and($scoped['portfolio']['units_grid'])->toHaveCount(1)
        ->and($scoped['portfolio']['units_grid'][0]['units'])->toHaveCount(0);
});

it('hides the remind button from staff without the lease permission', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $owner = makeOnboardedUser();
    $owner->assignRole(PlatformRole::OrganizationOwner->value);

    $org = Organization::factory()->create();
    $owner->organizations()->attach($org->id, ['is_owner' => true, 'status' => 'active']);

    $property = Property::factory()->for($org)->create();
    $occupant = Occupant::factory()->create(['organization_id' => $org->id]);

    Lease::factory()->create([
        'organization_id' => $org->id,
        'property_id' => $property->id,
        'person_id' => $occupant->person_id,
        'status' => 'active',
        'starts_at' => now()->subMonthsNoOverflow(3)->toDateString(),
        'ends_at' => null,
        'rent_amount' => 20000,
        'rent_due_day' => 5,
    ]);

    $subRole = SubRole::factory()->create(['organization_id' => $org->id]);
    $staff = app(StaffService::class)->create($org, $owner, [
        'name' => 'Staffer',
        'email' => 'staff-'.uniqid().'@example.com',
        'phone' => '123456',
        'sub_role_id' => $subRole->id,
        'property_ids' => [$property->id],
    ]);

    $defaulters = $this->actingAs($staff)
        ->get(route('dashboard'))
        ->assertOk()
        ->viewData('page')['props']['organization']['collection']['defaulters'];

    expect($defaulters)->toHaveCount(1)
        ->and($defaulters[0]['can_remind'])->toBeFalse();
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

it('redirects the central dashboard to the overview on an organization subdomain', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    (new PlansSeeder)->run();

    $owner = makeOnboardedUser();
    $organization = app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Redirect Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
    $domain = $organization->tenant->domains()->value('domain');

    $this->actingAs($owner)
        ->get("http://{$domain}/dashboard")
        ->assertRedirect("http://{$domain}/overview");
});
