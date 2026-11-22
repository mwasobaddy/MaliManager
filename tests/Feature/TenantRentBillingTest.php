<?php

use App\Mail\RentReminderMail;
use App\Models\Lease;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Plan;
use App\Models\Unit;
use App\Models\User;
use App\Services\RentArrearsService;
use App\Services\TenantService;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

function billingOrg(User $owner, string $name = 'Billing Estates'): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $owner,
        name: $name,
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
}

function billingProperty(Organization $organization, User $owner, string $name = 'Sunset Heights'): array
{
    $property = $organization->properties()->create([
        'name' => $name,
        'slug' => Str::slug($name).'-'.Str::random(4),
        'status' => 'active',
        'created_by' => $owner->id,
    ]);
    $unit = $property->units()->create([
        'name' => 'A1',
        'status' => 'vacant',
        'rent_frequency' => 'monthly',
        'rent_amount' => 25000,
        'created_by' => $owner->id,
    ]);

    return [$property, $unit];
}

function billingUrl(Organization $organization, string $path = ''): string
{
    return 'http://'.$organization->tenant->domains()->value('domain').$path;
}

function billingLease(Organization $organization, $property, $unit, Person $person, array $overrides = []): Lease
{
    return Lease::create([
        'person_id' => $person->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'unit_id' => $unit->id,
        'starts_at' => '2026-08-01',
        'rent_amount' => 10000,
        'rent_frequency' => 'monthly',
        'currency' => 'KES',
        'status' => 'active',
        ...$overrides,
    ]);
}

test('units require a billing frequency and persist it', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = billingOrg($owner);
    $property = billingProperty($organization, $owner)[0];

    $this->actingAs($owner)
        ->post(billingUrl($organization, "/{$property->slug}/units"), [
            'name' => 'B1',
            'rent_amount' => 2500,
        ])
        ->assertSessionHasErrors('rent_frequency');

    $this->actingAs($owner)
        ->post(billingUrl($organization, "/{$property->slug}/units"), [
            'name' => 'B1',
            'rent_frequency' => 'fortnightly',
            'rent_amount' => 2500,
        ])
        ->assertSessionHasErrors('rent_frequency');

    $this->actingAs($owner)
        ->post(billingUrl($organization, "/{$property->slug}/units"), [
            'name' => 'B1',
            'rent_frequency' => 'daily',
            'rent_amount' => 2500,
        ])
        ->assertRedirect();

    $unit = Unit::where('property_id', $property->id)->where('name', 'B1')->firstOrFail();

    expect($unit->rent_frequency)->toBe('daily')
        ->and($unit->rent_amount)->toBe('2500.00');
});

test('monthly equivalents normalize every frequency', function () {
    $daily = new Unit(['rent_frequency' => 'daily', 'rent_amount' => 2500]);
    $weekly = new Unit(['rent_frequency' => 'weekly', 'rent_amount' => 1000]);
    $monthly = new Unit(['rent_frequency' => 'monthly', 'rent_amount' => 25000]);
    $yearly = new Unit(['rent_frequency' => 'yearly', 'rent_amount' => 120000]);
    $legacy = new Unit(['rent_amount' => 5000]);

    expect($daily->monthlyEquivalent())->toBe(75000.0)
        ->and($weekly->monthlyEquivalent())->toBe(round(1000 * 52 / 12, 2))
        ->and($monthly->monthlyEquivalent())->toBe(25000.0)
        ->and($yearly->monthlyEquivalent())->toBe(10000.0)
        ->and($legacy->monthlyEquivalent())->toBe(5000.0)
        ->and($daily->rentSuffix())->toBe('/day')
        ->and($weekly->rentSuffix())->toBe('/wk')
        ->and($monthly->rentSuffix())->toBe('/mo')
        ->and($yearly->rentSuffix())->toBe('/yr');
});

test('arrears accrue daily, weekly by weekday and yearly by month', function () {
    $this->travelTo('2026-11-10');

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = billingOrg($owner);
    [$property, $unit] = billingProperty($organization, $owner);
    $arrears = app(RentArrearsService::class);

    $daily = billingLease($organization, $property, $unit, Person::factory()->create(), [
        'starts_at' => '2026-11-08',
        'rent_amount' => 2500,
        'rent_frequency' => 'daily',
    ]);

    // Starts Wednesday, due Fridays: first due Friday 2026-11-06 only.
    $weekly = billingLease($organization, $property, $unit, Person::factory()->create(), [
        'starts_at' => '2026-11-04',
        'rent_amount' => 7000,
        'rent_frequency' => 'weekly',
        'rent_due_weekday' => 5,
    ]);

    // June 20 anniversary, three periods elapsed.
    $yearly = billingLease($organization, $property, $unit, Person::factory()->create(), [
        'starts_at' => '2024-03-15',
        'rent_amount' => 120000,
        'rent_frequency' => 'yearly',
        'rent_due_month' => 6,
        'rent_due_day' => 20,
    ]);

    expect($arrears->balanceFor($daily)['expected'])->toBe('7500.00')
        ->and($arrears->balanceFor($weekly)['expected'])->toBe('7000.00')
        ->and($arrears->balanceFor($yearly)['expected'])->toBe('360000.00')
        ->and($arrears->firstOverdueDate($weekly)->toDateString())->toBe('2026-11-06');
});

test('the flag job stamps the first unpaid date and clears the paid', function () {
    $this->travelTo('2026-11-10');

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = billingOrg($owner);
    [$property, $unit] = billingProperty($organization, $owner);
    $lease = billingLease($organization, $property, $unit, Person::factory()->create(), [
        'starts_at' => '2026-08-01',
        'rent_amount' => 10000,
        'rent_due_day' => 5,
    ]);

    $this->artisan('rent:flag-overdue')->assertSuccessful();

    expect($lease->fresh()->overdue_since?->toDateString())->toBe('2026-08-05');

    $lease->payments()->create([
        'organization_id' => $organization->id,
        'amount' => 999999, 'currency' => 'KES', 'status' => 'received', 'paid_on' => '2026-11-09',
    ]);

    $this->artisan('rent:flag-overdue')->assertSuccessful();

    expect($lease->fresh()->overdue_since)->toBeNull();
});

test('the 3am job reminds flagged leases and skips the rest', function () {
    $this->travelTo('2026-11-10');
    Mail::fake();

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = billingOrg($owner);
    [$property, $unit] = billingProperty($organization, $owner);

    $due = billingLease($organization, $property, $unit, Person::factory()->create(['email' => 'due@rent.test']), [
        'starts_at' => '2026-08-01',
        'rent_amount' => 10000,
        'rent_due_day' => 5,
        'overdue_since' => '2026-08-05',
    ]);
    $fresh = billingLease($organization, $property, $unit, Person::factory()->create(['email' => 'fresh@rent.test']), [
        'starts_at' => '2026-08-01',
        'rent_amount' => 10000,
        'rent_due_day' => 5,
        'overdue_since' => '2026-11-10',
    ]);

    $this->artisan('rent:remind-overdue')->assertSuccessful();

    Mail::assertSent(RentReminderMail::class, 1);
    expect($due->fresh()->last_reminded_at)->not->toBeNull()
        ->and($fresh->fresh()->last_reminded_at)->toBeNull();

    // Same day again: the 24h throttle turns the send into a skip.
    $this->artisan('rent:remind-overdue')->assertSuccessful();
    Mail::assertSent(RentReminderMail::class, 1);
});
