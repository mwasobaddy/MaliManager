<?php

use App\Models\Audit;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Database\Models\ImpersonationToken;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
});

test('guests cannot impersonate an organization owner', function () {
    $organization = Organization::factory()->create();

    $this->post(route('organizations.impersonate', $organization))
        ->assertRedirect(route('login'));
});

test('plain users are blocked from impersonating an organization owner', function () {
    $user = User::factory()->create();

    $organization = Organization::factory()->create();
    $organization->users()->attach($user->id, ['is_owner' => true, 'status' => 'active']);

    $this->actingAs($user)
        ->post(route('organizations.impersonate', $organization))
        ->assertRedirect();
    assertErrorToast();
});

test('the impersonate permission is enforced and not just the admin role', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    // Revoke the fine-grained permission from the admin *role* (revoking it from
    // the user would not help — they would still inherit it via the role). The
    // route must then refuse even though the role check alone would pass.
    $admin->roles()->where('name', 'admin')->first()
        ->revokePermissionTo(Permission::findByName('organization.impersonate'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $organization = Organization::factory()->create();
    $organization->tenant->domains()->create(['domain' => 'acme.malimanager.test']);
    $owner = User::factory()->create();
    $organization->users()->attach($owner->id, ['is_owner' => true, 'status' => 'active']);

    $this->actingAs($admin)
        ->post(route('organizations.impersonate', $organization))
        ->assertRedirect();

    // The important part: no impersonation may have been started.
    expect(app(ImpersonationToken::class)->count())->toBe(0);
});

test('admins can impersonate the organization owner without knowing the member id', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $organization = Organization::factory()->create();
    $organization->tenant->domains()->create(['domain' => 'acme.malimanager.test']);
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $staff = User::factory()->create(['onboarded_at' => now()]);
    $organization->users()->attach($owner->id, ['is_owner' => true, 'status' => 'active']);
    $organization->users()->attach($staff->id, ['is_owner' => false, 'status' => 'active']);

    $response = $this->actingAs($admin)
        ->post(route('organizations.impersonate', $organization));

    $token = app(ImpersonationToken::class)->latest('created_at')->firstOrFail();

    $response->assertRedirect('http://acme.malimanager.test/impersonate/'.$token->token);
    expect($token->user_id)->toBe((string) $owner->id);
});

test('admins cannot impersonate an organization that has no owner', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $organization = Organization::factory()->create();
    $organization->tenant->domains()->create(['domain' => 'acme.malimanager.test']);
    $staff = User::factory()->create();
    $organization->users()->attach($staff->id, ['is_owner' => false, 'status' => 'active']);

    $this->actingAs($admin)
        ->post(route('organizations.impersonate', $organization))
        ->assertRedirect();
    assertErrorToast();
});

test('admins cannot impersonate an organization without a configured domain', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $organization->users()->attach($owner->id, ['is_owner' => true, 'status' => 'active']);

    $this->actingAs($admin)
        ->post(route('organizations.impersonate', $organization))
        ->assertRedirect();
    assertErrorToast();
});

test('the organizations page shares the impersonate permission to the frontend', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('organizations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organizations/index')
            ->where('auth.permissions', fn (Collection $permissions) => $permissions->contains('organization.impersonate')));
});

// --- Ending an impersonation -------------------------------------------------
//
// Stancl's UserImpersonation is fire-and-forget: redeeming the token calls
// Auth::loginUsingId() and overwrites the session user, keeping the previous
// identity nowhere. These tests pin the recovery path that makes the session
// recoverable at all, because losing it strands the admin on the owner account
// with no way back that does not involve logging out.

test('starting an impersonation records the admin in the session', function () {
    $admin = User::factory()->create(['name' => 'Kwame Admin']);
    $admin->assignRole('admin');

    $organization = Organization::factory()->create(['name' => 'Acme Estates']);
    $organization->tenant->domains()->create(['domain' => 'acme.malimanager.test']);
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization->users()->attach($owner->id, ['is_owner' => true, 'status' => 'active']);

    $this->actingAs($admin)->post(route('organizations.impersonate', $organization));

    $recorded = session('impersonated_by');

    expect($recorded)->toBeArray()
        ->and($recorded['user_id'])->toBe($admin->id)
        ->and($recorded['name'])->toBe('Kwame Admin')
        ->and($recorded['organization'])->toBe('Acme Estates')
        ->and($recorded['tenant_id'])->toBe($organization->tenant_id)
        ->and($recorded['started_at'])->not->toBeNull();
});

test('the impersonation flag is shared to the frontend so the banner can render', function () {
    $admin = User::factory()->create(['name' => 'Kwame Admin']);
    $admin->assignRole('admin');

    $organization = Organization::factory()->create(['name' => 'Acme Estates']);
    $organization->tenant->domains()->create(['domain' => 'acme.malimanager.test']);
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization->users()->attach($owner->id, ['is_owner' => true, 'status' => 'active']);

    $this->actingAs($admin)
        ->post(route('organizations.impersonate', $organization));

    // Every page gets the flag, on either domain — the session cookie spans both.
    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('impersonation.admin.name', 'Kwame Admin')
            ->where('impersonation.organization', 'Acme Estates')
            ->where('impersonation.can_return', true),
        );
});

test('no impersonation flag is shared when nobody is impersonating', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('impersonation', null));
});

test('an admin can hand the session back and gets their own account again', function () {
    $admin = User::factory()->create(['name' => 'Kwame Admin']);
    $admin->assignRole('admin');

    $organization = Organization::factory()->create(['name' => 'Acme Estates']);
    $organization->tenant->domains()->create(['domain' => 'acme.malimanager.test']);
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization->users()->attach($owner->id, ['is_owner' => true, 'status' => 'active']);

    $this->actingAs($admin)->post(route('organizations.impersonate', $organization));

    $response = $this->actingAs($owner)
        ->post(route('impersonation.stop'));

    $response->assertRedirect(rtrim(config('app.url'), '/').'/organizations');

    $this->assertAuthenticatedAs($admin);
    expect(session('impersonated_by'))->toBeNull();
});

test('ending an impersonation is audited so the trail has an end', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $organization = Organization::factory()->create(['name' => 'Acme Estates']);
    $organization->tenant->domains()->create(['domain' => 'acme.malimanager.test']);
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization->users()->attach($owner->id, ['is_owner' => true, 'status' => 'active']);

    $this->actingAs($admin)->post(route('organizations.impersonate', $organization));
    $this->actingAs($owner)->post(route('impersonation.stop'));

    $audit = Audit::where('event', 'impersonation.stopped')->latest()->first();

    expect($audit)->not->toBeNull()
        ->and($audit->description)->toContain('Acme Estates')
        ->and($audit->tenant_id)->toBe($organization->tenant_id);
});

test('the stop route does not require the admin role, because the owner is not one', function () {
    // The current user during an impersonation is the owner, who by definition
    // fails the `admin` middleware. Gating the stop route on `admin` would make
    // the only way out of an impersonation unreachable.
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $organization = Organization::factory()->create();
    $organization->tenant->domains()->create(['domain' => 'acme.malimanager.test']);
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization->users()->attach($owner->id, ['is_owner' => true, 'status' => 'active']);

    $this->actingAs($admin)->post(route('organizations.impersonate', $organization));

    $this->actingAs($owner)
        ->post(route('impersonation.stop'))
        ->assertRedirect();

    $this->assertAuthenticatedAs($admin);
});

test('stopping signs out rather than restoring a demoted admin', function () {
    // Offering a button that logs someone into a session the `admin` middleware
    // will refuse is worse than not offering one, so `can_return` is false and
    // the stop itself signs them out.
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $organization = Organization::factory()->create();
    $organization->tenant->domains()->create(['domain' => 'acme.malimanager.test']);
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization->users()->attach($owner->id, ['is_owner' => true, 'status' => 'active']);

    $this->actingAs($admin)->post(route('organizations.impersonate', $organization));

    $admin->roles()->detach();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('impersonation.can_return', false));

    $this->actingAs($owner)->post(route('impersonation.stop'));

    $this->assertGuest();
});

test('stopping with no impersonation in progress is harmless', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('impersonation.stop'))
        ->assertRedirect(rtrim(config('app.url'), '/'));
});

test('guests cannot stop an impersonation', function () {
    $this->post(route('impersonation.stop'))->assertRedirect(route('login'));
});

// --- The button explains itself instead of failing after the click ------------

test('the row tells the frontend when impersonation is blocked, and why', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $impersonable = Organization::factory()->create(['name' => 'Acme Estates']);
    $impersonable->tenant->domains()->create(['domain' => 'acme.malimanager.test']);
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $impersonable->users()->attach($owner->id, ['is_owner' => true, 'status' => 'active']);

    // The owner *is* a platform admin, so the endpoint refuses.
    $adminOwned = Organization::factory()->create(['name' => 'Admin Owned']);
    $adminOwned->tenant->domains()->create(['domain' => 'admin-owned.malimanager.test']);
    $adminOwned->users()->attach($admin->id, ['is_owner' => true, 'status' => 'active']);

    $this->actingAs($admin)
        ->get(route('organizations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('organizations.data', function (Collection $rows) {
                $byName = $rows->keyBy('name');

                expect($byName['Acme Estates']['impersonation_blocked_reason'])->toBeNull()
                    ->and($byName['Admin Owned']['impersonation_blocked_reason'])
                    ->toContain('Admins cannot be impersonated');

                return true;
            }),
        );
});

test('a row with no owner or no domain reports that instead', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $noDomain = Organization::factory()->create(['name' => 'No Domain']);
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $noDomain->users()->attach($owner->id, ['is_owner' => true, 'status' => 'active']);

    $noOwner = Organization::factory()->create(['name' => 'No Owner']);
    $noOwner->tenant->domains()->create(['domain' => 'no-owner.malimanager.test']);

    $this->actingAs($admin)
        ->get(route('organizations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('organizations.data', function (Collection $rows) {
                $byName = $rows->keyBy('name');

                expect($byName['No Domain']['impersonation_blocked_reason'])->toContain('no domain')
                    ->and($byName['No Owner']['impersonation_blocked_reason'])->toContain('no owner');

                return true;
            }),
        );
});

test('a 403 during an impersonation explains the session rather than the permission', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $organization = Organization::factory()->create(['name' => 'Acme Estates']);
    $organization->tenant->domains()->create(['domain' => 'acme.malimanager.test']);
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization->users()->attach($owner->id, ['is_owner' => true, 'status' => 'active']);

    $this->actingAs($admin)->post(route('organizations.impersonate', $organization));

    // The owner hits a central admin page they have no permission for. The
    // generic "you do not have permission" copy sends them hunting for a
    // permission they never had; the real answer is the borrowed session.
    $response = $this->actingAs($owner)
        ->get(route('organizations.index'));

    $response->assertRedirect(route('dashboard'));

    $toast = session()->get('inertia.flash_data')['toast'] ?? [];

    expect($toast['type'] ?? null)->toBe('warning')
        ->and($toast['message'] ?? '')->toContain('Acme Estates')
        ->and($toast['message'] ?? '')->toContain('Return to admin');
});

test('the stop route is registered exactly once', function () {
    // Defining `POST /impersonation/stop` in both routes/platform.php and
    // routes/tenant.php collides *silently*: Laravel's route collection keeps
    // one entry per method+URI, and `route:list` still shows a single tidy row.
    // The tenant copy wins because tenant.php is loaded later, and it carries
    // PreventAccessFromCentralDomains — which makes the central stop
    // unreachable, so an admin has no way out of an impersonation at all.
    $matches = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (Illuminate\Routing\Route $route) => $route->uri() === 'impersonation/stop'
            && in_array('POST', (array) $route->methods(), true));

    expect($matches)->toHaveCount(1)
        ->and($matches->first()->gatherMiddleware())
        ->not->toContain(PreventAccessFromCentralDomains::class);

    // And it must not be behind `admin`: during an impersonation the current
    // user is the owner, who would be refused by that middleware.
    expect($matches->first()->gatherMiddleware())->not->toContain('admin');
});
