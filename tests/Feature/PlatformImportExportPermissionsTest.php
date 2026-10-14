<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Import and export are separate permissions from the existing manage ones, so
 * a role that can browse a table must not be able to bulk-write to it, and a
 * role that can create one record must not be able to import a thousand by
 * uploading a file. These pin both halves.
 *
 * Two things to know about how this app behaves:
 *
 * - `bootstrap/app.php` renders every 403 as a toast and redirects (so an
 *   impersonated admin gets an explanation instead of a dead end), so these
 *   tests assert "redirected and nothing happened" rather than
 *   `assertForbidden()`. The gate itself is asserted directly, which is the part
 *   that the frontend's button visibility depends on.
 * - Permissions are revoked by name rather than deleted wholesale, because
 *   Spatie throws `PermissionDoesNotExist` when a gate references a name with no
 *   row, which would surface as a 500 and test nothing.
 */
beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();

    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole('admin');

    $this->editor = User::factory()->create(['email_verified_at' => now()]);
    $this->editor->assignRole('admin');

    /**
     * Revoke by name from both the role and any direct grant, so a permission
     * given earlier in the test cannot survive on the pivot Spatie keeps
     * between a user and a permission.
     */
    $this->revoke = function (array $names) {
        $ids = SpatiePermission::whereIn('name', $names)->pluck('id');

        $this->editor->roles()->first()->permissions()->detach($ids);
        $this->editor->permissions()->detach($ids);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->editor->unsetRelation('roles')->unsetRelation('permissions');
    };

    /**
     * Grant on the role rather than on the user, matching how the roles screen
     * actually assigns permissions.
     */
    $this->grant = function (string $name) {
        $this->editor->roles()->first()->givePermissionTo($name);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->editor->unsetRelation('roles')->unsetRelation('permissions');
    };
});

/**
 * A blocked request never reaches the table it names, so the expected redirect
 * is anything other than that table's index.
 */
function notIndex(string $importRoute): Closure
{
    return function (string $value) use ($importRoute) {
        return $value !== url(str_replace('.import', '.index', $importRoute));
    };
}

/**
 * `assertHeader` needs a plain value, so resolve the closure to the "blocked"
 * placeholder the exception handler actually redirects to.
 */
function assertBlocked(TestResponse $response, string $importRoute): void
{
    $index = url(str_replace('.import', '.index', $importRoute));

    expect($response->headers->get('Location'))
        ->not->toBe($index);
}

/**
 * import route, export route, import permission, export permission,
 * import gate, export gate.
 */
dataset('csv endpoints', [
    'plans' => [
        'platform.plans.import',
        'platform.plans.export',
        'plan.import',
        'plan.export',
        'importPlans',
        'exportPlans',
        "Name,Slug\nPro,pro\n",
    ],
    'subscription payments' => [
        'platform.subscription-payments.import',
        'platform.subscription-payments.export',
        'subscription.import',
        'subscription.export',
        'importSubscriptionPayments',
        'exportSubscriptionPayments',
        "Amount,Received On\n5000,2026-01-05\n",
    ],
    'expenses' => [
        'platform.expenses.import',
        'platform.expenses.export',
        'platform-expense.import',
        'platform-expense.export',
        'importPlatformExpenses',
        'exportPlatformExpenses',
        "Category,Amount,Spent On\nhosting,1200,2026-01-31\n",
    ],
]);

test('the import and export gates track their own permissions only', function (
    string $importRoute,
    string $exportRoute,
    string $importName,
    string $exportName,
    string $importGate,
    string $exportGate,
    string $csv,
) {
    // Both revoked: neither gate opens.
    ($this->revoke)([$importName, $exportName]);

    expect(Gate::forUser($this->editor)->allows($importGate))->toBeFalse()
        ->and(Gate::forUser($this->editor)->allows($exportGate))->toBeFalse();

    // Import only.
    ($this->revoke)([$exportName]);
    ($this->grant)($importName);

    expect(Gate::forUser($this->editor)->allows($importGate))->toBeTrue()
        ->and(Gate::forUser($this->editor)->allows($exportGate))->toBeFalse();

    // Export only.
    ($this->revoke)([$importName]);
    ($this->grant)($exportName);

    expect(Gate::forUser($this->editor)->allows($importGate))->toBeFalse()
        ->and(Gate::forUser($this->editor)->allows($exportGate))->toBeTrue();
})->with('csv endpoints');

test('a role without import permission cannot import', function (
    string $importRoute,
    string $exportRoute,
    string $importName,
    string $exportName,
    string $importGate,
    string $exportGate,
    string $csv,
) {
    ($this->revoke)([$importName]);

    $response = $this->actingAs($this->editor)
        ->post(route($importRoute), [
            'file' => UploadedFile::fake()->createWithContent('x.csv', $csv),
        ]);

    // A permitted import redirects to the table's index; a blocked one is
    // turned into a toast by the exception handler and lands elsewhere.
    $response->assertRedirect();
    assertBlocked($response, $importRoute);
})->with('csv endpoints');

test('a role without export permission cannot export', function (
    string $importRoute,
    string $exportRoute,
    string $importName,
    string $exportName,
    string $importGate,
    string $exportGate,
    string $csv,
) {
    ($this->revoke)([$exportName]);

    $this->actingAs($this->editor)
        ->get(route($exportRoute))
        ->assertRedirect();
})->with('csv endpoints');

test('an admin can reach every csv endpoint', function (string $importRoute, string $exportRoute) {
    $this->actingAs($this->admin)
        ->get(route($exportRoute))
        ->assertOk();
})->with('csv endpoints');

test('every new permission is seeded and assignable', function (
    string $importRoute,
    string $exportRoute,
    string $importName,
    string $exportName,
) {
    $this->assertDatabaseHas('permissions', ['name' => $importName]);
    $this->assertDatabaseHas('permissions', ['name' => $exportName]);

    // The roles screen lists permissions from the enum, so the gate definition
    // and the grantable row cannot drift apart.
    $this->actingAs($this->admin)
        ->get(route('platform.roles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('permissionCatalog')
            ->where('permissionCatalog', fn ($catalog) => collect($catalog)->pluck('value')
                ->contains($importName) && collect($catalog)->pluck('value')->contains($exportName)),
        );
})->with('csv endpoints');
