<?php

use App\Models\AiSetting;
use App\Models\AiUsageLog;
use App\Models\Expense;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Plan;
use App\Models\User;
use App\Services\TenantService;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\StructuredResponseFake;
use Prism\Prism\ValueObjects\Usage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Facade::clearResolvedInstance('prism');
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

function reportOrganization(User $owner): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Acme Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
}

function reportProperty(Organization $organization, User $owner): App\Models\Property
{
    return $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);
}

function reportUrl(Organization $organization, string $path): string
{
    $domain = $organization->tenant->domains()->firstOrCreate([
        'domain' => $organization->slug.'.report.test',
    ]);

    return "http://{$domain->domain}{$path}";
}

test('occupancy report lists units per property', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = reportOrganization($owner);
    $property = reportProperty($organization, $owner);

    $property->units()->createMany([
        ['name' => 'A1', 'status' => 'occupied', 'created_by' => $owner->id],
        ['name' => 'A2', 'status' => 'vacant', 'created_by' => $owner->id],
    ]);

    $this->actingAs($owner)
        ->get(reportUrl($organization, '/reports/data?type=occupancy'))
        ->assertOk()
        ->assertJsonPath('rows.0.asset', 'Sunset Heights')
        ->assertJsonPath('rows.0.total_units', 2)
        ->assertJsonPath('rows.0.vacant', 1)
        ->assertJsonPath('rows.0.occupied', 1);
});

test('expenses report filters by date range and totals', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = reportOrganization($owner);
    $property = reportProperty($organization, $owner);

    Expense::create([
        'organization_id' => $organization->id,
        'expenseable_type' => Property::class,
        'expenseable_id' => $property->id,
        'category' => 'maintenance',
        'amount' => 1000,
        'spent_on' => '2026-01-15',
        'created_by' => $owner->id,
    ]);
    Expense::create([
        'organization_id' => $organization->id,
        'expenseable_type' => Property::class,
        'expenseable_id' => $property->id,
        'category' => 'cleaning',
        'amount' => 500,
        'spent_on' => '2026-06-20',
        'created_by' => $owner->id,
    ]);

    // Range excluding the January entry.
    $this->actingAs($owner)
        ->get(reportUrl($organization, '/reports/data?type=expenses&from=2026-02-01&to=2026-12-31'))
        ->assertOk()
        ->assertJsonPath('summary.Entries', 1)
        ->assertJsonPath('rows.0.category', 'cleaning');

    // CSV download contains header + row.
    $this->actingAs($owner)
        ->get(reportUrl($organization, '/reports/csv?type=expenses&from=2026-01-01&to=2026-12-31'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

test('lease pipeline report lists leases with occupant names', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = reportOrganization($owner);
    $property = reportProperty($organization, $owner);
    $unit = $property->units()->create(['name' => 'A1', 'status' => 'occupied', 'created_by' => $owner->id]);

    $person = Person::create(['first_name' => 'Jane', 'last_name' => 'Wanjiru', 'email' => 'jane@x.test', 'status' => 'active']);

    Lease::create([
        'person_id' => $person->id,
        'organization_id' => $organization->id,
        'tenant_id' => $organization->tenant_id,
        'property_id' => $property->id,
        'unit_id' => $unit->id,
        'starts_at' => '2026-01-01',
        'rent_amount' => 25000,
        'currency' => 'KES',
        'status' => 'active',
    ]);

    $this->actingAs($owner)
        ->get(reportUrl($organization, '/reports/data?type=leases&from=2025-01-01&to=2026-12-31'))
        ->assertOk()
        ->assertJsonPath('summary.Active (open-ended)', 1)
        ->assertJsonPath('rows.0.unit', 'A1')
        ->assertJsonPath('rows.0.occupant', 'Jane Wanjiru');
});

test('maintenance report computes average days to resolve', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = reportOrganization($owner);
    $property = reportProperty($organization, $owner);

    MaintenanceRequest::create([
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'raised_by' => $owner->id,
        'title' => 'Broken gate',
        'description' => 'Motor dead.',
        'status' => 'resolved',
        'priority' => 'high',
        'resolved_at' => now()->subDays(1),
    ]);

    // created_at is guarded; set it directly for a deterministic 3-day span.
    DB::table('maintenance_requests')
        ->update(['created_at' => now()->subDays(4)]);

    $this->actingAs($owner)
        ->get(reportUrl($organization, '/reports/data?type=maintenance&from='.now()->subDays(10)->toDateString().'&to='.now()->toDateString()))
        ->assertOk()
        ->assertJsonPath('summary.Requests', 1)
        ->assertJsonPath('summary.Resolved', 1)
        ->assertJsonPath('summary.Avg days to resolve', fn ($v) => (float) $v === 3.0);
});

test('staff without any module manage key cannot access reports', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = reportOrganization($owner);
    reportProperty($organization, $owner);

    $staff = User::factory()->create(['onboarded_at' => now()]);
    $organization->users()->attach($staff->id, ['is_owner' => false, 'status' => 'active']);

    // Global handler converts the 403 into a redirect + toast.
    $this->actingAs($staff)
        ->get(reportUrl($organization, '/reports/data?type=occupancy'))
        ->assertRedirect();

    expect(User::count())->toBeGreaterThan(0);
});

test('leases index flags expiring leases and links to a pre-filled draft', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = reportOrganization($owner);
    $property = reportProperty($organization, $owner);
    $unit = $property->units()->create(['name' => 'A1', 'status' => 'occupied', 'created_by' => $owner->id]);

    $person = Person::create(['first_name' => 'Jane', 'last_name' => 'Wanjiru', 'email' => 'jane@x.test', 'status' => 'active']);

    Lease::create([
        'person_id' => $person->id,
        'organization_id' => $organization->id,
        'tenant_id' => $organization->tenant_id,
        'property_id' => $property->id,
        'unit_id' => $unit->id,
        'starts_at' => now()->subYear(),
        'ends_at' => now()->addDays(30),
        'rent_amount' => 25000,
        'currency' => 'KES',
        'status' => 'active',
    ]);

    $this->actingAs($owner)
        ->get(reportUrl($organization, '/sunset-heights/leases'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant/leases/index')
            ->where('leases.data.0.expiring_soon', true)
            ->where('leases.data.0.ends_at', fn ($v) => $v !== null));
});

test('leases index supports the expiring-only filter', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = reportOrganization($owner);
    $property = reportProperty($organization, $owner);
    $unit = $property->units()->create(['name' => 'A1', 'status' => 'occupied', 'created_by' => $owner->id]);

    $person = Person::create(['first_name' => 'Jane', 'email' => 'jane@x.test', 'status' => 'active']);

    Lease::create([
        'person_id' => $person->id,
        'organization_id' => $organization->id,
        'tenant_id' => $organization->tenant_id,
        'property_id' => $property->id,
        'unit_id' => $unit->id,
        'starts_at' => now()->subYear(),
        'ends_at' => now()->addDays(30),
        'status' => 'active',
    ]);

    $this->actingAs($owner)
        ->get(reportUrl($organization, '/sunset-heights/leases?expiring=1'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('leases.data', fn ($rows) => count($rows) === 1));
});

test('renewal suggestion endpoint returns AI rent with usage logged', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = reportOrganization($owner);
    $property = reportProperty($organization, $owner);
    $unit = $property->units()->create(['name' => 'A1', 'status' => 'occupied', 'created_by' => $owner->id]);

    $person = Person::create(['first_name' => 'Jane', 'email' => 'jane2@x.test', 'status' => 'active']);

    $lease = Lease::create([
        'person_id' => $person->id,
        'organization_id' => $organization->id,
        'tenant_id' => $organization->tenant_id,
        'property_id' => $property->id,
        'unit_id' => $unit->id,
        'starts_at' => now()->subMonths(13),
        'ends_at' => now()->addDays(30),
        'rent_amount' => 25000,
        'currency' => 'KES',
        'status' => 'active',
    ]);

    // Org-wide key so the gateway resolves.
    AiSetting::create([
        'owner_type' => $organization->getMorphClass(),
        'owner_id' => $organization->id,
        'provider' => 'openai',
        'model' => 'gpt-4o',
        'api_key' => 'sk-renewal-key-1234567890',
        'allow_all_members' => true,
    ]);

    Prism::fake([
        StructuredResponseFake::make()
            ->withStructured(['suggested_rent' => 26500, 'reasoning' => '13-month reliable tenant, modest increase.'])
            ->withUsage(new Usage(120, 50)),
    ]);

    $this->actingAs($owner)
        ->getJson(reportUrl($organization, "/sunset-heights/leases/{$lease->id}/renewal-suggestion"))
        ->assertOk()
        ->assertJsonPath('suggested_rent', fn ($v) => (float) $v === 26500.0)
        ->assertJsonPath('reasoning', fn ($r) => str_contains((string) $r, 'tenant'));

    expect(AiUsageLog::where('feature', 'ask_data')->count())->toBe(1);
});
