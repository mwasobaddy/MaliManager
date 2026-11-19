<?php

use App\Models\Lease;
use App\Models\Occupant;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Plan;
use App\Models\Property;
use App\Models\SubscriptionPayment;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
});

function overviewAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

test('platform overview reports mrr, past-due and trials', function () {
    $plan = Plan::factory()->create(['name' => 'Growth', 'price' => 7500]);
    $cheapPlan = Plan::factory()->create(['name' => 'Starter', 'price' => 2500]);

    $paying = Organization::factory()->create([
        'plan_id' => $plan->id,
        'plan_confirmed_at' => now()->subDays(40),
        'trial_ends_at' => now()->subDays(10),
        'subscription_ends_at' => now()->addDays(20),
    ]);

    Organization::factory()->create([
        'plan_id' => $cheapPlan->id,
        'plan_confirmed_at' => now()->subDays(60),
        'trial_ends_at' => now()->subDays(50),
        'subscription_ends_at' => now()->subDays(20),
    ]);

    Organization::factory()->create([
        'plan_id' => $plan->id,
        'plan_confirmed_at' => now()->subDays(2),
        'trial_ends_at' => now()->addDays(3),
        'subscription_ends_at' => null,
    ]);

    SubscriptionPayment::factory()->create([
        'organization_id' => $paying->id,
        'plan_id' => $plan->id,
        'amount' => 7500,
        'received_on' => now()->toDateString(),
    ]);
    SubscriptionPayment::factory()->create([
        'organization_id' => $paying->id,
        'plan_id' => $plan->id,
        'amount' => 7500,
        'received_on' => now()->subMonthNoOverflow()->toDateString(),
    ]);

    $this->actingAs(overviewAdmin())
        ->get(route('platform.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('platform/dashboard')
            ->where('overview.kpis.mrr.value', 15000)
            ->where('overview.kpis.mrr.paying_organizations', 2)
            ->where('overview.kpis.past_due.value', 1)
            ->where('overview.kpis.past_due.exposure', 2500)
            ->where('overview.kpis.trials_ending.value', 1)
            ->where('overview.kpis.signups_this_month.value', 3)
            ->where('overview.kpis.collected_this_month.value', 7500)
            ->where('overview.kpis.collected_this_month.last_month', 7500)
            ->where('overview.currency', 'KES')
            ->has('overview.income_trend', 12)
            ->has('overview.alerts')
            ->has('overview.attention', 2));
});

test('platform overview flags subscriptions lapsed over fourteen days', function () {
    $plan = Plan::factory()->create(['price' => 2500]);

    $organization = Organization::factory()->create([
        'name' => 'Lapsed Ltd',
        'plan_id' => $plan->id,
        'plan_confirmed_at' => now()->subDays(60),
        'trial_ends_at' => now()->subDays(50),
        'subscription_ends_at' => now()->subDays(20),
    ]);

    $response = $this->actingAs(overviewAdmin())->get(route('platform.dashboard'));

    $attention = $response->viewData('page')['props']['overview']['attention'];
    $alerts = $response->viewData('page')['props']['overview']['alerts'];

    expect($attention)->toHaveCount(1)
        ->and($attention[0]['name'])->toBe('Lapsed Ltd')
        ->and($attention[0]['reason'])->toBe('past_due')
        ->and($attention[0]['days_past_due'])->toBe(20)
        ->and($alerts[0]['severity'])->toBe('danger')
        ->and($alerts[0]['text'])->toContain('20 days ago');
});

test('platform overview is empty but honest with no organizations', function () {
    $this->actingAs(overviewAdmin())
        ->get(route('platform.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('overview.kpis.mrr.value', 0)
            ->where('overview.kpis.past_due.value', 0)
            ->where('overview.attention', [])
            ->where('overview.alerts.0.text', 'Nothing needs attention.'));
});

test('platform growth reports funnel, cohorts and payment performance', function () {
    $plan = Plan::factory()->create(['price' => 2500]);

    $organization = Organization::factory()->create([
        'plan_id' => $plan->id,
        'plan_confirmed_at' => now()->subDays(70),
        'created_at' => now()->subMonthsNoOverflow(2)->startOfMonth()->addDays(2),
    ]);
    Organization::factory()->create();

    $property = Property::factory()->create(['organization_id' => $organization->id]);
    Unit::factory()->create(['property_id' => $property->id]);
    $occupant = Occupant::factory()->create(['organization_id' => $organization->id]);

    $lease = Lease::factory()->create([
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'person_id' => $occupant->person_id,
        'status' => 'active',
    ]);

    Payment::factory()->create([
        'organization_id' => $organization->id,
        'lease_id' => $lease->id,
        'occupant_id' => $occupant->id,
        'amount' => 25000,
        'status' => 'received',
        'method' => 'mpesa',
        'paid_on' => now()->toDateString(),
    ]);

    SubscriptionPayment::factory()->create([
        'organization_id' => $organization->id,
        'plan_id' => $plan->id,
        'amount' => 2500,
        'period_start' => now()->subMonthsNoOverflow(2)->startOfMonth()->toDateString(),
        'period_end' => now()->addMonth()->toDateString(),
        'received_on' => now()->subMonthsNoOverflow(2)->startOfMonth()->toDateString(),
    ]);

    $succeeded = PaymentAttempt::create([
        'organization_id' => $organization->id,
        'plan_id' => $plan->id,
        'amount' => 2500,
        'currency' => 'KES',
        'gateway' => 'mpesa',
        'status' => 'succeeded',
        'gateway_reference' => 'ws_OK_1',
    ]);
    $succeeded->created_at = now()->subMinutes(5);
    $succeeded->save();
    $failed = PaymentAttempt::create([
        'organization_id' => $organization->id,
        'plan_id' => $plan->id,
        'amount' => 2500,
        'currency' => 'KES',
        'gateway' => 'mpesa',
        'status' => 'failed',
        'gateway_reference' => 'ws_FAIL_1',
        'meta' => ['result_description' => 'Insufficient funds'],
    ]);
    $failed->created_at = now()->subMinutes(2);
    $failed->save();

    $response = $this->actingAs(overviewAdmin())->get(route('platform.dashboard'));

    $props = $response->viewData('page')['props'];

    $funnel = collect($props['growth']['funnel'])->mapWithKeys(fn ($stage) => [$stage['stage'] => $stage]);
    expect($funnel['signed_up']['count'])->toBe(2)
        ->and($funnel['first_property']['count'])->toBe(1)
        ->and($funnel['first_property']['conversion'])->toBe(50.0)
        ->and($funnel['first_unit']['count'])->toBe(1)
        ->and($funnel['first_occupant']['count'])->toBe(1)
        ->and($funnel['first_rent']['count'])->toBe(1)
        ->and($funnel['converted']['count'])->toBe(1);

    $cohort = collect($props['growth']['cohorts']['rows'])
        ->first(fn ($row) => $row['size'] >= 1);
    expect($cohort)->not->toBeNull()
        ->and($cohort['cells'][0]['retained'])->toBeGreaterThanOrEqual(1);

    $today = collect($props['payments']['daily'])->last();
    expect($today['attempts'])->toBe(2)
        ->and($today['success_rate'])->toBe(50.0)
        ->and($today['latency_p50_seconds'])->toBe(210)
        ->and($props['payments']['failures'])->toHaveCount(1)
        ->and($props['payments']['failures'][0]['reason'])->toBe('Insufficient funds');

    $leases = collect($props['adoption']['features'])->firstWhere('key', 'leases');
    expect($props['adoption']['properties_total'])->toBe(1)
        ->and($props['adoption']['units_total'])->toBe(1)
        ->and($props['adoption']['rent_month_to_date'])->toBe(25000.0)
        ->and($leases['organizations'])->toBe(1);

    expect($props['histories']['as_of'])->toBeNull();
});
