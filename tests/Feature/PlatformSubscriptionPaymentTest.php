<?php

use App\Models\Audit;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;

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

test('platform admin can update a subscription payment', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $payment = SubscriptionPayment::factory()->create(['amount' => 1000]);

    $response = $this->actingAs($admin)->patch(route('platform.subscription-payments.update', $payment), [
        'amount' => 9999,
        'currency' => 'KES',
        'received_on' => $payment->received_on->format('Y-m-d'),
    ]);

    $response->assertRedirect(route('platform.subscription-payments.index'));
    expect($payment->fresh()->amount)->toBe('9999.00');
    assertSuccessToast();
});

test('the legacy create and edit pages redirect to the overlay urls', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $payment = SubscriptionPayment::factory()->create();

    $this->actingAs($admin)
        ->get(route('platform.subscription-payments.create'))
        ->assertRedirect(route('platform.subscription-payments.index', ['create' => 1]));

    $this->actingAs($admin)
        ->get(route('platform.subscription-payments.edit', $payment))
        ->assertRedirect(route('platform.subscription-payments.index', [
            'mode' => 'edit',
            'payment' => $payment->id,
        ]));
});

test('the index resolves the organization and plan for each row', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $organization = Organization::factory()->create(['name' => 'River Rentals']);
    $plan = Plan::first();
    SubscriptionPayment::factory()->create([
        'organization_id' => $organization->id,
        'plan_id' => $plan->id,
    ]);

    $this->actingAs($admin)
        ->get(route('platform.subscription-payments.index'))
        ->assertInertia(fn ($page) => $page
            ->component('platform/subscription-payments/index')
            ->where('payments.data.0.organization.name', 'River Rentals')
            ->where('payments.data.0.plan.name', $plan->name),
        );
});

test('a payment with no organization or plan serialises both as null', function () {
    // The form's Selects reserve the empty value for their placeholder, so a
    // cash payment with nothing linked must render as "—" rather than crash.
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    SubscriptionPayment::factory()->create([
        'organization_id' => null,
        'plan_id' => null,
    ]);

    $this->actingAs($admin)
        ->get(route('platform.subscription-payments.index'))
        ->assertInertia(fn ($page) => $page
            ->where('payments.data.0.organization', null)
            ->where('payments.data.0.plan', null),
        );
});

test('the amount is serialised as a number so the column can sort numerically', function () {
    // `amount` is cast to `decimal:2`, which stringifies. A string amount would
    // sort "1000" after "900" and would need Number() at every call site.
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    SubscriptionPayment::factory()->create(['amount' => 1234.5]);

    $this->actingAs($admin)
        ->get(route('platform.subscription-payments.index'))
        ->assertInertia(fn ($page) => $page
            ->where('payments.data.0.amount', 1234.5),
        );
});

test('the index filters by reference, method, notes and organization name', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $organization = Organization::factory()->create(['name' => 'River Rentals']);

    SubscriptionPayment::factory()->create(['reference' => 'MP-1234', 'method' => 'mpesa']);
    SubscriptionPayment::factory()->create([
        'reference' => 'CC-5678',
        'method' => 'card',
        'notes' => 'settlement batch 9',
        'organization_id' => $organization->id,
    ]);

    foreach (['MP-1234', 'mpesa', 'settlement', 'River Rentals'] as $needle) {
        $this->actingAs($admin)
            ->get(route('platform.subscription-payments.index', ['search' => $needle]))
            ->assertInertia(fn ($page) => $page->has('payments.data', 1));
    }
});

test('the index filters by a received-on date range', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    SubscriptionPayment::factory()->create(['received_on' => '2026-01-15']);
    SubscriptionPayment::factory()->create(['received_on' => '2026-06-15']);
    SubscriptionPayment::factory()->create(['received_on' => '2026-12-15']);

    $this->actingAs($admin)
        ->get(route('platform.subscription-payments.index', [
            'from' => '2026-06-01',
            'to' => '2026-06-30',
        ]))
        ->assertInertia(fn ($page) => $page
            ->has('payments.data', 1)
            ->where('payments.data.0.received_on', '2026-06-15')
            ->where('filters.from', '2026-06-01')
            ->where('filters.to', '2026-06-30'),
        );
});

test('the index sorts by amount and ignores a crafted sort column', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    SubscriptionPayment::factory()->create(['amount' => 900, 'received_on' => '2026-01-01']);
    SubscriptionPayment::factory()->create(['amount' => 100, 'received_on' => '2026-02-01']);

    $this->actingAs($admin)
        ->get(route('platform.subscription-payments.index', ['sort' => 'amount', 'direction' => 'asc']))
        ->assertInertia(fn ($page) => $page
            ->where('sort.column', 'amount')
            ->where('payments.data.0.amount', 100)
            ->where('payments.data.1.amount', 900),
        );

    // Not in the allowlist, so it must not reach the ORDER BY.
    $this->actingAs($admin)
        ->get(route('platform.subscription-payments.index', ['sort' => 'notes; drop table subscription_payments']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('sort.column', null));
});

test('the default order is newest received first', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    SubscriptionPayment::factory()->create(['received_on' => '2026-01-01']);
    SubscriptionPayment::factory()->create(['received_on' => '2026-09-01']);

    $this->actingAs($admin)
        ->get(route('platform.subscription-payments.index'))
        ->assertInertia(fn ($page) => $page
            ->where('payments.data.0.received_on', '2026-09-01')
            ->where('payments.data.1.received_on', '2026-01-01'),
        );
});

test('an off-page payment id in the url resolves the row for the overlay', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $onPage = collect(range(1, 15))->map(fn (int $day) => SubscriptionPayment::factory()->create([
        'reference' => "Filler {$day}",
        'received_on' => '2026-01-01',
    ]));
    // Oldest, because the default order is received_on desc — a newer one
    // would come first and be on page 1 already.
    $offPage = SubscriptionPayment::factory()->create([
        'reference' => 'OFF-PAGE',
        'received_on' => '2025-01-01',
    ]);

    $this->actingAs($admin)
        ->get(route('platform.subscription-payments.index', ['payment' => $offPage->id]))
        ->assertInertia(fn ($page) => $page
            ->component('platform/subscription-payments/index')
            ->where('focusedPayment.id', $offPage->id)
            ->where('focusedPayment.reference', 'OFF-PAGE'),
        );

    // A row that is already on the page is not sent twice.
    $this->actingAs($admin)
        ->get(route('platform.subscription-payments.index', ['payment' => $onPage->first()->id]))
        ->assertInertia(fn ($page) => $page->where('focusedPayment', null));
});

test('the organization and plan lists are not sent unless they are asked for', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    SubscriptionPayment::factory()->count(2)->create();

    // Eagerly these would re-query and re-serialise both lists on every
    // debounced keystroke, sort and page change, for a form sheet that is
    // closed most of the time.
    $this->actingAs($admin)
        ->get(route('platform.subscription-payments.index'))
        ->assertInertia(fn ($page) => $page
            ->missing('organizations')
            ->missing('plans'),
        );

    // ...and are sent on demand, once a form sheet opens.
    $this->actingAs($admin)
        ->get(route('platform.subscription-payments.index'), [
            'X-Inertia-Partial-Component' => 'platform/subscription-payments/index',
            'X-Inertia-Partial-Data' => 'organizations,plans',
        ], [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('organizations')
            ->has('plans'),
        );
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

test('platform admin can import subscription payments from a csv', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $organization = Organization::factory()->create(['name' => 'Acme Rentals']);
    $plan = Plan::first();

    $csv = "Amount,Received On,Currency,Organization,Plan,Method,Reference\n"
        ."15000,2026-01-05,KES,Acme Rentals,{$plan->name},M-Pesa,MPESA-1\n"
        ."2500,2026-02-05,KES,,,Bank,BNK-2\n";

    $this->actingAs($admin)
        ->post(route('platform.subscription-payments.import'), [
            'file' => UploadedFile::fake()->createWithContent('payments.csv', $csv),
        ])
        ->assertRedirect(route('platform.subscription-payments.index'));

    // Both rows land, and the first is linked by organization/plan name.
    $this->assertDatabaseHas('subscription_payments', [
        'amount' => 15000,
        'organization_id' => $organization->id,
        'plan_id' => $plan->id,
        'method' => 'M-Pesa',
    ]);

    // The second has no org/plan, so it imports unlinked rather than failing.
    $this->assertDatabaseHas('subscription_payments', [
        'amount' => 2500,
        'organization_id' => null,
        'plan_id' => null,
    ]);
});

test('payment import reports per-row errors', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $csv = "Amount,Received On\n"
        ."15000,2026-01-05\n"
        ."not-a-number,2026-01-06\n"
        ."500,\n";

    $this->actingAs($admin)
        ->post(route('platform.subscription-payments.import'), [
            'file' => UploadedFile::fake()->createWithContent('payments.csv', $csv),
        ])
        ->assertRedirect();

    $errors = collect(session('inertia.flash_data')['importErrors'] ?? [])->all();

    expect($errors)->toHaveCount(2)
        ->and(collect($errors)->pluck('row')->all())->toBe([3, 4]);

    $this->assertDatabaseHas('subscription_payments', ['amount' => 15000]);
    $this->assertDatabaseCount('subscription_payments', 1);
});

test('payment export honours the table filters', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    SubscriptionPayment::factory()->create(['amount' => 15000, 'received_on' => '2026-01-05']);
    SubscriptionPayment::factory()->create(['amount' => 2500, 'received_on' => '2026-06-05']);

    $body = $this->actingAs($admin)
        ->get(route('platform.subscription-payments.export', [
            'from' => '2026-06-01',
            'to' => '2026-06-30',
        ]))
        ->streamedContent();

    expect($body)->toContain('2500')
        ->and($body)->not->toContain('15000');
});
