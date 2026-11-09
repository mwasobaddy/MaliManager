<?php

use App\Mail\RentReminderMail;
use App\Models\Lease;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Plan;
use App\Models\User;
use App\Services\RentArrearsService;
use App\Services\TenantService;
use App\Support\Ai\AssistantService;
use App\Support\Ai\Scope;
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

function createArrearsOrganization(User $owner, string $name = 'Arrears Estates'): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $owner,
        name: $name,
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
}

function arrearsTenantUrl(Organization $organization, string $path = ''): string
{
    $domain = $organization->tenant->domains()->value('domain');

    return "http://{$domain}{$path}";
}

function makeLease(Organization $organization, $property, $unit, Person $person, array $overrides = []): Lease
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

function makePropertyWithUnit(Organization $organization, User $owner, string $name = 'Sunset Heights'): array
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
        'created_by' => $owner->id,
    ]);

    return [$property, $unit];
}

test('rent due day is stored on occupant creation and validated', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createArrearsOrganization($owner);
    [$property, $unit] = makePropertyWithUnit($organization, $owner);

    $this->actingAs($owner)
        ->post(arrearsTenantUrl($organization, "/{$property->slug}/occupants"), [
            'first_name' => 'Jane',
            'email' => 'jane@arrears.test',
            'status' => 'active',
            'unit_ids' => [$unit->id],
            'lease' => [
                'rent_amount' => 25000,
                'rent_due_day' => 5,
            ],
        ])->assertRedirect();

    expect(Lease::where('unit_id', $unit->id)->firstOrFail()->rent_due_day)->toBe(5);

    $this->actingAs($owner)
        ->post(arrearsTenantUrl($organization, "/{$property->slug}/occupants"), [
            'first_name' => 'John',
            'email' => 'john@arrears.test',
            'status' => 'active',
            'unit_ids' => [$unit->id],
            'lease' => ['rent_due_day' => 32],
        ])->assertInvalid('lease.rent_due_day');
});

test('arrears count due months against received payments only', function () {
    $this->travelTo('2026-11-10');

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createArrearsOrganization($owner);
    [$property, $unit] = makePropertyWithUnit($organization, $owner);
    $person = Person::factory()->create();

    // Due on the 5th: Aug, Sep, Oct and Nov (10th past the 5th) = 4 x 10000.
    $lease = makeLease($organization, $property, $unit, $person, ['rent_due_day' => 5]);

    $lease->payments()->create([
        'organization_id' => $organization->id,
        'amount' => 15000, 'currency' => 'KES', 'status' => 'received', 'paid_on' => '2026-09-01',
    ]);
    $lease->payments()->create([
        'organization_id' => $organization->id,
        'amount' => 50000, 'currency' => 'KES', 'status' => 'pending', 'paid_on' => '2026-09-01',
    ]);
    $lease->payments()->create([
        'organization_id' => $organization->id,
        'amount' => 50000, 'currency' => 'KES', 'status' => 'refunded', 'paid_on' => '2026-09-01',
    ]);

    $balance = app(RentArrearsService::class)->balanceFor($lease->refresh());

    expect($balance['expected'])->toBe('40000.00')
        ->and($balance['paid'])->toBe('15000.00')
        ->and($balance['outstanding'])->toBe('25000.00')
        ->and($balance['overdue'])->toBeTrue()
        ->and($balance['latest_due_date'])->toBe('2026-11-05');
});

test('arrears default to the anniversary day and clamp short months', function () {
    $this->travelTo('2026-11-10');

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createArrearsOrganization($owner);
    [$property, $unit] = makePropertyWithUnit($organization, $owner);

    // Started Aug 15, no explicit due day: Aug, Sep, Oct due; Nov 15 not yet.
    $anniversary = makeLease($organization, $property, $unit, Person::factory()->create(), [
        'starts_at' => '2026-08-15',
        'rent_due_day' => null,
    ]);

    $balance = app(RentArrearsService::class)->balanceFor($anniversary);

    expect($balance['expected'])->toBe('30000.00')
        ->and($balance['latest_due_date'])->toBe('2026-10-15');

    // Due on the 31st: Feb counts via the 28th.
    $this->travelTo('2026-03-01');
    $clamped = makeLease($organization, $property, $unit, Person::factory()->create(), [
        'starts_at' => '2026-01-10',
        'rent_due_day' => 31,
    ]);

    $clampedBalance = app(RentArrearsService::class)->balanceFor($clamped);

    expect($clampedBalance['expected'])->toBe('20000.00')
        ->and($clampedBalance['latest_due_date'])->toBe('2026-02-28');
});

test('arrears support weekly frequencies', function () {
    $this->travelTo('2026-11-10');

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createArrearsOrganization($owner);
    [$property, $unit] = makePropertyWithUnit($organization, $owner);

    $lease = makeLease($organization, $property, $unit, Person::factory()->create(), [
        'starts_at' => '2026-10-20',
        'rent_frequency' => 'weekly',
        'rent_amount' => 1000,
    ]);

    $balance = app(RentArrearsService::class)->balanceFor($lease);

    // Weeks starting Oct 20 and 27, Nov 3 and Nov 10 (due at period
    // start, like monthly due dates) = 4 x 1000.
    expect($balance['expected'])->toBe('4000.00')
        ->and($balance['overdue'])->toBeTrue();
});

test('leases index filters overdue and shows balances', function () {
    $this->travelTo('2026-11-10');

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createArrearsOrganization($owner);
    [$property, $unitA] = makePropertyWithUnit($organization, $owner);
    $unitB = $property->units()->create(['name' => 'B2', 'status' => 'vacant', 'created_by' => $owner->id]);

    $overdue = makeLease($organization, $property, $unitA, Person::factory()->create(), ['rent_due_day' => 5]);

    $paidUp = makeLease($organization, $property, $unitB, Person::factory()->create(), ['rent_due_day' => 5]);
    $paidUp->payments()->create([
        'organization_id' => $organization->id,
        'amount' => 999999, 'currency' => 'KES', 'status' => 'received', 'paid_on' => '2026-11-01',
    ]);

    $this->actingAs($owner)
        ->get(arrearsTenantUrl($organization, "/{$property->slug}/leases?overdue=1"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/leases/index')
            ->has('leases.data', 1)
            ->where('leases.data.0.id', $overdue->id)
            ->where('leases.data.0.is_overdue', true)
            ->where('leases.data.0.outstanding', '40000.00')
        );

    $this->actingAs($owner)
        ->get(arrearsTenantUrl($organization, "/{$property->slug}/leases"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('leases.data', 2)
            ->where('leases.data.1.is_overdue', false)
        );
});

test('staff without access are redirected from leases, not denied', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createArrearsOrganization($owner);
    [$property] = makePropertyWithUnit($organization, $owner);

    $staff = User::factory()->create(['onboarded_at' => now()]);
    $organization->users()->attach($staff->id, [
        'is_owner' => false,
        'sub_role_id' => $organization->subRoles()->where('slug', 'caretaker')->first()->id,
        'status' => 'active',
    ]);

    $this->actingAs($staff)
        ->get(arrearsTenantUrl($organization, "/{$property->slug}/leases"))
        ->assertRedirect();

    assertErrorToast();
});

test('an owner can remind an overdue lease on the occupant channel', function () {
    Mail::fake();
    $this->travelTo('2026-11-10');

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createArrearsOrganization($owner);
    [$property, $unit] = makePropertyWithUnit($organization, $owner);

    $person = Person::factory()->create(['email' => 'tenant@arrears.test']);
    User::factory()->create([
        'email' => 'tenant@arrears.test',
        'person_id' => $person->id,
        'notification_channel' => 'email',
    ]);
    $lease = makeLease($organization, $property, $unit, $person, ['rent_due_day' => 5]);

    $this->actingAs($owner)
        ->post(arrearsTenantUrl($organization, "/{$property->slug}/leases/{$lease->id}/remind"))
        ->assertRedirect();

    Mail::assertSent(RentReminderMail::class, fn ($mail) => $mail->hasTo('tenant@arrears.test'));
    expect($lease->refresh()->last_reminded_at)->not->toBeNull();

    // Throttled within 24 hours.
    $this->actingAs($owner)
        ->post(arrearsTenantUrl($organization, "/{$property->slug}/leases/{$lease->id}/remind"))
        ->assertRedirect();

    assertErrorToast();
    Mail::assertSent(RentReminderMail::class, 1);
});

test('reminding a paid-up lease is refused', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createArrearsOrganization($owner);
    [$property, $unit] = makePropertyWithUnit($organization, $owner);

    $lease = makeLease($organization, $property, $unit, Person::factory()->create());
    $lease->payments()->create([
        'organization_id' => $organization->id,
        'amount' => 999999, 'currency' => 'KES', 'status' => 'received', 'paid_on' => today()->toDateString(),
    ]);

    $this->actingAs($owner)
        ->post(arrearsTenantUrl($organization, "/{$property->slug}/leases/{$lease->id}/remind"))
        ->assertRedirect();

    assertErrorToast();
    expect($lease->refresh()->last_reminded_at)->toBeNull();
});

test('the AI broadcast tool is registered and drafts before sending', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createArrearsOrganization($owner);

    $service = app(AssistantService::class);
    $method = new ReflectionMethod($service, 'specialisedTools');
    $method->setAccessible(true);

    $artifacts = [];
    $tools = $method->invokeArgs($service, [$owner, Scope::org($organization->id), &$artifacts]);
    $names = array_map(fn ($tool) => $tool->name(), $tools);

    expect($names)->toContain('send_rent_reminders');
});

test('payment details round-trip to billing and the tenant rentals page', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createArrearsOrganization($owner);

    $this->actingAs($owner)
        ->put(arrearsTenantUrl($organization, '/billing/payment-details'), [
            'paybill_number' => '522522',
            'account_reference' => 'UNIT-A1',
            'till_number' => null,
            'notes' => 'Rent due on the 5th',
        ])->assertRedirect();

    expect($organization->refresh()->paymentDetailsSummary())->toContain('522522');

    $person = Person::factory()->create();
    $user = User::factory()->create(['person_id' => $person->id]);
    [$property, $unit] = makePropertyWithUnit($organization, $owner);
    makeLease($organization, $property, $unit, $person);

    $this->actingAs($user)
        ->get(route('searcher.rentals'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('searcher/rentals')
            ->where('leases.0.payment_details.paybill_number', '522522')
            ->has('leases.0.outstanding')
        );
});

test('staff cannot update payment details', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createArrearsOrganization($owner);

    $staff = User::factory()->create(['onboarded_at' => now()]);
    $organization->users()->attach($staff->id, [
        'is_owner' => false,
        'sub_role_id' => $organization->subRoles()->where('slug', 'caretaker')->first()->id,
        'status' => 'active',
    ]);

    $this->actingAs($staff)
        ->put(arrearsTenantUrl($organization, '/billing/payment-details'), [
            'paybill_number' => '999999',
        ])->assertRedirect();

    assertErrorToast();
    expect($organization->refresh()->paymentDetails()['paybill_number'])->toBeNull();
});
