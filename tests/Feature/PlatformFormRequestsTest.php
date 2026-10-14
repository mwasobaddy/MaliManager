<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * The validation rules for the platform write endpoints, after they moved out
 * of inline `$request->validate()` calls and into the `Platform` form requests.
 *
 * These are rule tests, not behaviour tests — `UserSuspensionTest` and friends
 * already cover what a valid request *does*. What was untested was what an
 * invalid one is rejected as, and with a typed request that rejection is easy to
 * break silently: move a rule and the endpoint still works, it just quietly
 * accepts bad input.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();

    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole('admin');
});

test('user status rejects an unknown status', function () {
    $victim = User::factory()->create(['status' => 'active']);

    $this->actingAs($this->admin)
        ->patch(route('platform.users.status', $victim), ['status' => 'banished'])
        ->assertSessionHasErrors('status');

    expect($victim->fresh()->status)->toBe('active');
});

test('user status is required', function () {
    $victim = User::factory()->create(['status' => 'active']);

    $this->actingAs($this->admin)
        ->patch(route('platform.users.status', $victim))
        ->assertSessionHasErrors('status');

    expect($victim->fresh()->status)->toBe('active');
});

test('user status accepts every valid status', function (string $status) {
    $victim = User::factory()->create(['status' => 'active']);

    $this->actingAs($this->admin)
        ->patch(route('platform.users.status', $victim), ['status' => $status])
        ->assertSessionHasNoErrors();

    expect($victim->fresh()->status)->toBe($status);
})->with(['active', 'inactive', 'suspended']);

test('role permissions reject a key that is not a real permission', function () {
    $role = Role::findByName('searcher');

    // The seeder gives `searcher` two permissions; a rejected payload must not
    // half-apply, so the starting set has to survive untouched.
    $before = $role->permissions->pluck('name')->sort()->values()->all();

    $this->actingAs($this->admin)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->patch(route('platform.roles.update', $role), [
            'permissions' => ['user.create', 'user.not-a-real-key'],
        ])
        ->assertSessionHasErrors('permissions.1');

    expect($role->fresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe($before);
});

test('role permissions accept real keys and sync the role', function () {
    $role = Role::findByName('searcher');

    $this->actingAs($this->admin)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->patch(route('platform.roles.update', $role), [
            'permissions' => ['user.create', 'user.edit'],
        ])
        ->assertSessionHasNoErrors();

    expect($role->fresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['user.create', 'user.edit']);
});

test('role permissions may be emptied', function () {
    $role = Role::findByName('searcher');

    $this->actingAs($this->admin)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->patch(route('platform.roles.update', $role), ['permissions' => []])
        ->assertSessionHasNoErrors();

    expect($role->fresh()->permissions)->toHaveCount(0);
});

test('the assistant rejects an over-long question', function () {
    // A 422 naming `question` is proof the typed request rejected it: the rule
    // runs before the controller body, so a request that reached the gateway
    // could not produce this response.
    $this->actingAs($this->admin)
        ->postJson(route('platform.assistant.ask'), [
            'question' => str_repeat('a', 1001),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('question');
});

test('the assistant rejects a missing question', function () {
    $this->actingAs($this->admin)
        ->postJson(route('platform.assistant.ask'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('question');
});

test('impersonating a user outside the organization is refused', function () {
    $organization = Organization::factory()->create();
    $stranger = User::factory()->create();

    $this->actingAs($this->admin)
        ->from(route('platform.organizations.index'))
        ->post(route('platform.impersonate', [$organization, $stranger]))
        ->assertRedirect();

    // A 403 becomes a toast redirect in this app, so assert on what did not
    // happen rather than on the status code.
    expect(session('impersonated_by'))->toBeNull();
});

test('import requests reject a non-csv upload', function (string $route, string $field = 'file') {
    $this->actingAs($this->admin)
        ->post(route($route), [
            $field => UploadedFile::fake()->create('payload.php', 10, 'application/x-php'),
        ])
        ->assertSessionHasErrors($field);
})->with([
    'plans' => 'platform.plans.import',
    'payments' => 'platform.subscription-payments.import',
    'expenses' => 'platform.expenses.import',
    'users' => 'platform.users.import',
    'organizations' => 'platform.organizations.import',
]);

test('import requests reject a file over 2mb', function (string $route) {
    $this->actingAs($this->admin)
        ->post(route($route), [
            'file' => UploadedFile::fake()->createWithContent('big.csv', str_repeat('a', 2_100_000)),
        ])
        ->assertSessionHasErrors('file');
})->with([
    'plans' => 'platform.plans.import',
    'users' => 'platform.users.import',
]);

test('destroying a user requires the current password', function () {
    $victim = User::factory()->create(['status' => 'active']);

    $this->actingAs($this->admin)
        ->delete(route('platform.users.destroy', $victim), ['password' => 'wrong'])
        ->assertSessionHasErrors('password');

    expect(User::find($victim->id))->not->toBeNull();

    $this->actingAs($this->admin)
        ->delete(route('platform.users.destroy', $victim), ['password' => 'password'])
        ->assertSessionHasNoErrors();

    expect(User::find($victim->id))->toBeNull();
});

test('destroying an organization requires the current password', function () {
    $organization = Organization::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('platform.organizations.destroy', $organization), ['password' => 'nope'])
        ->assertSessionHasErrors('password');

    expect(Organization::find($organization->id))->not->toBeNull();
});

test('the admin password factory value is still the default', function () {
    // Guards the fixture the password-confirmation tests above rely on.
    expect(Hash::check('password', $this->admin->password))->toBeTrue();
});
