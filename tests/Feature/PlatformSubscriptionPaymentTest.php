<?php

use App\Models\Audit;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

test('platform admin can view subscription payments index', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    SubscriptionPayment::factory()->count(2)->create();

    $this->actingAs($admin)
        ->get(route('platform.subscription-payments.index'))
        ->assertOk();
});

test('platform admin can create a subscription payment', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $plan = Plan::first();

    $response = $this->actingAs($admin)->post(route('platform.subscription-payments.store'), [
        'amount' => 5000,
        'currency' => 'KES',
        'received_on' => '2026-06-15',
        'method' => 'mpesa',
        'reference' => 'MP-1234',
        'plan_id' => $plan->id,
    ]);

    $response->assertRedirect(route('platform.subscription-payments.index'));
    expect(SubscriptionPayment::where('reference', 'MP-1234')->exists())->toBeTrue();
});

test('subscription payment stores created_by user id', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $this->actingAs($admin)->post(route('platform.subscription-payments.store'), [
        'amount' => 2500,
        'currency' => 'KES',
        'received_on' => '2026-07-01',
    ]);

    expect(SubscriptionPayment::latest()->first()->created_by)->toBe($admin->id);
});

test('platform admin can edit and update a subscription payment', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $payment = SubscriptionPayment::factory()->create(['amount' => 1000]);

    $this->actingAs($admin)
        ->get(route('platform.subscription-payments.edit', $payment))
        ->assertOk();

    $response = $this->actingAs($admin)->patch(route('platform.subscription-payments.update', $payment), [
        'amount' => 9999,
        'currency' => 'KES',
        'received_on' => $payment->received_on->format('Y-m-d'),
    ]);

    $response->assertRedirect(route('platform.subscription-payments.index'));
    expect($payment->fresh()->amount)->toBe('9999.00');
});

test('platform admin can delete a subscription payment', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $payment = SubscriptionPayment::factory()->create();

    $response = $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->delete(route('platform.subscription-payments.destroy', $payment), [
            'password' => 'password',
        ]);

    $response->assertRedirect(route('platform.subscription-payments.index'));
    expect(SubscriptionPayment::find($payment->id))->toBeNull();
    expect(SubscriptionPayment::withTrashed()->find($payment->id))->not->toBeNull();
});

test('user without subscription permission is forbidden', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('platform.subscription-payments.index'))
        ->assertRedirect(route('dashboard'));

    $this->actingAs($user)
        ->post(route('platform.subscription-payments.store'), [
            'amount' => 100,
            'currency' => 'KES',
            'received_on' => '2026-01-01',
        ])
        ->assertRedirect(route('dashboard'));
});

test('subscription payment validation requires amount and received_on', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('platform.subscription-payments.store'), [
            'currency' => 'KES',
        ])
        ->assertSessionHasErrors(['amount', 'received_on']);
});

test('creating a subscription payment is audited', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $this->actingAs($admin)->post(route('platform.subscription-payments.store'), [
        'amount' => 7500,
        'currency' => 'KES',
        'received_on' => '2026-08-10',
        'method' => 'card',
        'reference' => 'CC-5678',
    ]);

    $audit = Audit::where('event', 'created')
        ->where('subject_type', SubscriptionPayment::class)
        ->latest()
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->description)->toContain('Created')
        ->and($audit->causer_type)->toBe(User::class)
        ->and($audit->causer_id)->toBe($admin->id)
        ->and($audit->ip_address)->not->toBeNull()
        ->and($audit->tenant_id)->toBeNull();
});

test('updating a subscription payment is audited', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $payment = SubscriptionPayment::factory()->create(['amount' => 1000]);

    $this->actingAs($admin)->patch(route('platform.subscription-payments.update', $payment), [
        'amount' => 2000,
        'currency' => 'KES',
        'received_on' => $payment->received_on->format('Y-m-d'),
    ]);

    $audit = Audit::where('event', 'updated')
        ->where('subject_type', SubscriptionPayment::class)
        ->where('subject_id', $payment->id)
        ->latest()
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->description)->toContain('Updated');
});

test('deleting a subscription payment is audited', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $payment = SubscriptionPayment::factory()->create();

    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->delete(route('platform.subscription-payments.destroy', $payment), [
            'password' => 'password',
        ]);

    $audit = Audit::where('event', 'deleted')
        ->where('subject_type', SubscriptionPayment::class)
        ->where('subject_id', $payment->id)
        ->latest()
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->description)->toContain('Deleted');
});
