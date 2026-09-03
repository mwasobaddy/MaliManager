<?php

use App\Models\Audit;
use App\Models\PlatformExpense;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
});

test('platform admin can view expenses index', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    PlatformExpense::factory()->count(2)->create();

    $this->actingAs($admin)
        ->get(route('platform.expenses.index'))
        ->assertOk();
});

test('platform admin can create an expense', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->post(route('platform.expenses.store'), [
        'category' => 'hosting',
        'amount' => 3500,
        'currency' => 'KES',
        'spent_on' => '2026-06-15',
        'vendor_name' => 'AWS',
        'notes' => 'Monthly server bill',
    ]);

    $response->assertRedirect(route('platform.expenses.index'));
    expect(PlatformExpense::where('vendor_name', 'AWS')->exists())->toBeTrue();
});

test('expense stores created_by user id', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $this->actingAs($admin)->post(route('platform.expenses.store'), [
        'category' => 'tooling',
        'amount' => 1200,
        'currency' => 'KES',
        'spent_on' => '2026-07-01',
        'vendor_name' => 'GitHub',
    ]);

    expect(PlatformExpense::latest()->first()->created_by)->toBe($admin->id);
});

test('platform admin can edit and update an expense', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $expense = PlatformExpense::factory()->create(['amount' => 5000]);

    $this->actingAs($admin)
        ->get(route('platform.expenses.edit', $expense))
        ->assertOk();

    $response = $this->actingAs($admin)->patch(route('platform.expenses.update', $expense), [
        'category' => 'marketing',
        'amount' => 8000,
        'currency' => 'KES',
        'spent_on' => $expense->spent_on->format('Y-m-d'),
        'vendor_name' => 'Google Ads',
    ]);

    $response->assertRedirect(route('platform.expenses.index'));
    expect($expense->fresh()->category)->toBe('marketing');
    expect($expense->fresh()->vendor_name)->toBe('Google Ads');
});

test('platform admin can delete an expense', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $expense = PlatformExpense::factory()->create();

    $response = $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->delete(route('platform.expenses.destroy', $expense), [
            'password' => 'password',
        ]);

    $response->assertRedirect(route('platform.expenses.index'));
    expect(PlatformExpense::find($expense->id))->toBeNull();
    expect(PlatformExpense::withTrashed()->find($expense->id))->not->toBeNull();
});

test('user without expense permission is forbidden', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('platform.expenses.index'))
        ->assertRedirect(route('dashboard'));

    $this->actingAs($user)
        ->post(route('platform.expenses.store'), [
            'category' => 'other',
            'amount' => 100,
            'currency' => 'KES',
            'spent_on' => '2026-01-01',
            'vendor_name' => 'Test',
        ])
        ->assertRedirect(route('dashboard'));
});

test('expense validation requires category, amount, currency, and spent_on', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('platform.expenses.store'), [])
        ->assertSessionHasErrors(['category', 'amount', 'currency', 'spent_on']);
});

test('expense category must be one of the defined categories', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('platform.expenses.store'), [
            'category' => 'invalid_category',
            'amount' => 100,
            'currency' => 'KES',
            'spent_on' => '2026-01-01',
            'vendor_name' => 'Test',
        ])
        ->assertSessionHasErrors(['category']);
});

test('creating an expense is audited', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $this->actingAs($admin)->post(route('platform.expenses.store'), [
        'category' => 'software',
        'amount' => 4500,
        'currency' => 'KES',
        'spent_on' => '2026-08-10',
        'vendor_name' => 'Slack',
    ]);

    $audit = Audit::where('event', 'created')
        ->where('subject_type', PlatformExpense::class)
        ->latest()
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->description)->toContain('Created')
        ->and($audit->causer_type)->toBe(User::class)
        ->and($audit->causer_id)->toBe($admin->id)
        ->and($audit->ip_address)->not->toBeNull()
        ->and($audit->tenant_id)->toBeNull();
});

test('updating an expense is audited', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $expense = PlatformExpense::factory()->create(['amount' => 1000]);

    $this->actingAs($admin)->patch(route('platform.expenses.update', $expense), [
        'category' => $expense->category,
        'amount' => 2000,
        'currency' => 'KES',
        'spent_on' => $expense->spent_on->format('Y-m-d'),
        'vendor_name' => $expense->vendor_name,
    ]);

    $audit = Audit::where('event', 'updated')
        ->where('subject_type', PlatformExpense::class)
        ->where('subject_id', $expense->id)
        ->latest()
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->description)->toContain('Updated');
});

test('deleting an expense is audited', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $expense = PlatformExpense::factory()->create();

    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->delete(route('platform.expenses.destroy', $expense), [
            'password' => 'password',
        ]);

    $audit = Audit::where('event', 'deleted')
        ->where('subject_type', PlatformExpense::class)
        ->where('subject_id', $expense->id)
        ->latest()
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->description)->toContain('Deleted');
});

test('admin can filter expenses by category', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    PlatformExpense::factory()->create(['category' => 'hosting', 'vendor_name' => 'AWS']);
    PlatformExpense::factory()->create(['category' => 'marketing', 'vendor_name' => 'Google']);

    $this->actingAs($admin)
        ->get(route('platform.expenses.index', ['category' => 'hosting']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.category', 'hosting'));
});
