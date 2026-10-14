<?php

use App\Models\Audit;
use App\Models\PlatformExpense;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
});

test('platform admin can view expenses index', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    PlatformExpense::factory()->count(2)->create();

    $this->actingAs($admin)
        ->get(route('platform.expenses.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('platform/expenses/index')
            ->has('expenses.data', 2)
            // The filters overlay renders a radio group from this list, so it
            // has to be the same closed set the `category` rule validates.
            ->where('categories', PlatformExpense::CATEGORIES),
        );
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
    assertSuccessToast();
});

test('the legacy create and edit pages redirect to the overlay urls', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $expense = PlatformExpense::factory()->create();

    $this->actingAs($admin)
        ->get(route('platform.expenses.create'))
        ->assertRedirect(route('platform.expenses.index', ['create' => 1]));

    $this->actingAs($admin)
        ->get(route('platform.expenses.edit', $expense))
        ->assertRedirect(route('platform.expenses.index', [
            'mode' => 'edit',
            'expense' => $expense->id,
        ]));
});

test('the amount is serialised as a number so the column can sort numerically', function () {
    // `amount` is cast to `decimal:2`, which stringifies. A string amount would
    // sort "1000" after "900" and would need Number() at every call site.
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    PlatformExpense::factory()->create(['amount' => 1234.5, 'spent_on' => '2026-05-05']);

    $this->actingAs($admin)
        ->get(route('platform.expenses.index'))
        ->assertInertia(fn ($page) => $page
            ->where('expenses.data.0.amount', 1234.5)
            ->where('expenses.data.0.spent_on', '2026-05-05'),
        );
});

test('the index filters by search, category and date range', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    PlatformExpense::factory()->create([
        'category' => 'hosting',
        'vendor_name' => 'Hetzner',
        'spent_on' => '2026-01-15',
    ]);
    PlatformExpense::factory()->create([
        'category' => 'marketing',
        'vendor_name' => 'Google Ads',
        'notes' => 'settlement batch 9',
        'spent_on' => '2026-06-15',
    ]);

    foreach (['Hetzner', 'Google', 'settlement'] as $needle) {
        $this->actingAs($admin)
            ->get(route('platform.expenses.index', ['search' => $needle]))
            ->assertInertia(fn ($page) => $page->has('expenses.data', 1));
    }

    $this->actingAs($admin)
        ->get(route('platform.expenses.index', ['category' => 'hosting']))
        ->assertInertia(fn ($page) => $page
            ->has('expenses.data', 1)
            ->where('filters.category', 'hosting'),
        );

    $this->actingAs($admin)
        ->get(route('platform.expenses.index', [
            'from' => '2026-06-01',
            'to' => '2026-06-30',
        ]))
        ->assertInertia(fn ($page) => $page
            ->has('expenses.data', 1)
            ->where('expenses.data.0.vendor_name', 'Google Ads'),
        );
});

test('the index sorts by amount and ignores a crafted sort column', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    PlatformExpense::factory()->create(['amount' => 900, 'spent_on' => '2026-01-01']);
    PlatformExpense::factory()->create(['amount' => 100, 'spent_on' => '2026-02-01']);

    $this->actingAs($admin)
        ->get(route('platform.expenses.index', ['sort' => 'amount', 'direction' => 'asc']))
        ->assertInertia(fn ($page) => $page
            ->where('sort.column', 'amount')
            ->where('expenses.data.0.amount', 100)
            ->where('expenses.data.1.amount', 900),
        );

    // Not in the allowlist, so it must not reach the ORDER BY.
    $this->actingAs($admin)
        ->get(route('platform.expenses.index', ['sort' => 'notes; drop table platform_expenses']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('sort.column', null));
});

test('an off-page expense id in the url resolves the row for the overlay', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    // Pin the fillers' date so the target genuinely lands on page 2; the
    // default order is spent_on desc.
    $onPage = collect(range(1, 15))->map(fn (int $day) => PlatformExpense::factory()->create([
        'vendor_name' => "Filler {$day}",
        'spent_on' => '2026-06-01',
    ]));
    $offPage = PlatformExpense::factory()->create([
        'vendor_name' => 'Off page',
        'spent_on' => '2025-01-01',
    ]);

    $this->actingAs($admin)
        ->get(route('platform.expenses.index', ['expense' => $offPage->id]))
        ->assertInertia(fn ($page) => $page
            ->component('platform/expenses/index')
            ->where('focusedExpense.id', $offPage->id)
            ->where('focusedExpense.vendor_name', 'Off page'),
        );

    // A row that is already on the page is not sent twice.
    $this->actingAs($admin)
        ->get(route('platform.expenses.index', ['expense' => $onPage->first()->id]))
        ->assertInertia(fn ($page) => $page->where('focusedExpense', null));
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

test('platform admin can import expenses from a csv', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $csv = "Category,Amount,Currency,Spent On,Vendor Name,Notes\n"
        ."hosting,1200,KES,2026-01-31,Vercel,Monthly fee\n"
        ."tooling,45000,KES,2026-02-14,Repair Co,Generator\n";

    $this->actingAs($admin)
        ->post(route('platform.expenses.import'), [
            'file' => UploadedFile::fake()->createWithContent('expenses.csv', $csv),
        ])
        ->assertRedirect(route('platform.expenses.index'));

    $this->assertDatabaseHas('platform_expenses', [
        'category' => 'hosting',
        'amount' => 1200,
        'vendor_name' => 'Vercel',
    ]);

    $this->assertDatabaseHas('platform_expenses', [
        'category' => 'tooling',
        'amount' => 45000,
    ]);
});

test('expense import rejects an unknown category and reports the row', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $csv = "Category,Amount,Spent On\n"
        ."hosting,1200,2026-01-31\n"
        ."not-a-category,900,2026-02-01\n"
        ."tooling,free,2026-02-02\n";

    $this->actingAs($admin)
        ->post(route('platform.expenses.import'), [
            'file' => UploadedFile::fake()->createWithContent('expenses.csv', $csv),
        ])
        ->assertRedirect();

    $errors = collect(session('inertia.flash_data')['importErrors'] ?? [])->all();

    expect($errors)->toHaveCount(2)
        ->and(collect($errors)->pluck('row')->all())->toBe([3, 4]);

    $this->assertDatabaseCount('platform_expenses', 1);
});

test('expense export honours the table filters', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    PlatformExpense::factory()->create(['category' => 'hosting', 'spent_on' => '2026-01-31']);
    PlatformExpense::factory()->create(['category' => 'tooling', 'spent_on' => '2026-06-01']);

    $body = $this->actingAs($admin)
        ->get(route('platform.expenses.export', ['category' => 'tooling']))
        ->streamedContent();

    expect($body)->toContain('tooling')
        ->and($body)->not->toContain('hosting');
});
