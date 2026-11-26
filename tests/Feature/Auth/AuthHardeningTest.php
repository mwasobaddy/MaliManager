<?php

use App\Models\Audit;
use App\Models\LoginOtp;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Services\TenantService;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

function hardeningOrganization(User $owner, string $name = 'Acme Estates'): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $owner,
        name: $name,
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
}

function hardeningTenantUrl(Organization $organization, string $path = ''): string
{
    $domain = $organization->tenant->domains()->value('domain');

    return "http://{$domain}{$path}";
}

function attachHardeningStaff(Organization $organization, User $staff, string $slug = 'caretaker'): void
{
    $staff->organizations()->attach($organization->id, [
        'is_owner' => false,
        'sub_role_id' => $organization->subRoles()->where('slug', $slug)->firstOrFail()->id,
        'status' => 'active',
    ]);
}

function hardeningIssueCode(User $user): string
{
    return LoginOtp::where('user_id', $user->id)->latest('id')->value('code');
}

test('login with remember-me persists the session via the recaller cookie', function () {
    Mail::fake();

    $user = User::factory()->create(['email' => 'existing@example.com', 'onboarded_at' => now()]);

    $this->post(route('login.store'), ['email' => 'existing@example.com', 'remember' => true])
        ->assertRedirect(route('login.otp'));

    expect(session('login.remember'))->toBeTrue();

    $this->post(route('login.otp.verify'), ['code' => hardeningIssueCode($user)])
        ->assertRedirect()
        ->assertCookie(Auth::getRecallerName());

    $this->assertAuthenticatedAs($user);
    expect(session('login.remember'))->toBeNull();
});

test('login without remember-me leaves no recaller cookie', function () {
    Mail::fake();

    $user = User::factory()->create(['email' => 'plain@example.com', 'onboarded_at' => now()]);

    $this->post(route('login.store'), ['email' => 'plain@example.com'])
        ->assertRedirect(route('login.otp'));

    $this->post(route('login.otp.verify'), ['code' => hardeningIssueCode($user)])
        ->assertRedirect()
        ->assertCookieMissing(Auth::getRecallerName());

    $this->assertAuthenticatedAs($user);
});

test('one-time code issuance is audit logged', function () {
    Mail::fake();

    $user = User::factory()->create(['email' => 'logged@example.com']);

    $this->post(route('login.store'), ['email' => 'logged@example.com'])
        ->assertRedirect(route('login.otp'));

    expect(Audit::where('event', 'otp.issued')
        ->where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->exists())->toBeTrue();
});

test('resends are audit logged as re-issues', function () {
    Mail::fake();

    $user = User::factory()->create(['email' => 'resend@example.com']);

    session(['login.email' => 'resend@example.com']);

    $this->post(route('login.otp.resend'))->assertRedirect();

    expect(Audit::where('event', 'otp.issued')
        ->where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->exists())->toBeTrue();
});

test('suspended accounts are blocked at login and the attempt is logged', function () {
    $user = User::factory()->create(['email' => 'suspended@example.com', 'status' => 'suspended']);

    $this->post(route('login.store'), ['email' => 'suspended@example.com'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();

    expect(Audit::where('event', 'login.suspended')
        ->where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->exists())->toBeTrue();
});

test('staff without two-factor are redirected when the organization requires it', function () {
    Mail::fake();

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = hardeningOrganization($owner);
    $organization->settings = ['require_staff_two_factor' => true];
    $organization->save();

    $staff = User::factory()->create(['onboarded_at' => now()]);
    attachHardeningStaff($organization, $staff);

    $this->post(route('login.store'), ['email' => $staff->email])
        ->assertRedirect(route('login.otp'));

    $this->post(route('login.otp.verify'), ['code' => hardeningIssueCode($staff)])
        ->assertRedirect(route('security.edit'));

    $flash = session()->get('inertia.flash_data', []);
    expect($flash['toast']['type'] ?? null)->toBe('warning');
});

test('staff with confirmed two-factor pass the requirement', function () {
    Mail::fake();

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = hardeningOrganization($owner);
    $organization->settings = ['require_staff_two_factor' => true];
    $organization->save();

    $staff = User::factory()->create(['onboarded_at' => now()]);
    $staff->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt('test-codes'),
        'two_factor_confirmed_at' => now(),
    ])->save();
    attachHardeningStaff($organization, $staff);

    $this->post(route('login.store'), ['email' => $staff->email])
        ->assertRedirect(route('login.otp'));

    $response = $this->post(route('login.otp.verify'), ['code' => hardeningIssueCode($staff)]);

    $response->assertRedirect();
    expect($response->headers->get('Location'))->not->toBe(route('security.edit'));
});

test('owners are exempt from their own two-factor requirement', function () {
    Mail::fake();

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = hardeningOrganization($owner);
    $organization->settings = ['require_staff_two_factor' => true];
    $organization->save();

    $this->post(route('login.store'), ['email' => $owner->email])
        ->assertRedirect(route('login.otp'));

    $response = $this->post(route('login.otp.verify'), ['code' => hardeningIssueCode($owner)]);

    $response->assertRedirect();
    expect($response->headers->get('Location'))->not->toBe(route('security.edit'));
});

test('owners can toggle the staff two-factor requirement', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = hardeningOrganization($owner);

    $this->actingAs($owner)
        ->put(hardeningTenantUrl($organization, '/security-policy'), [
            'require_staff_two_factor' => true,
        ])->assertRedirect();

    expect($organization->refresh()->requiresStaffTwoFactor())->toBeTrue()
        ->and(Audit::where('event', 'organization.security_updated')->exists())->toBeTrue();
});

test('non-owners cannot change the security policy', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = hardeningOrganization($owner);

    $manager = User::factory()->create(['onboarded_at' => now()]);
    $manager->organizations()->attach($organization->id, [
        'is_owner' => false,
        'sub_role_id' => $organization->subRoles()->where('slug', 'landlord')->firstOrFail()->id,
        'status' => 'active',
    ]);

    $this->actingAs($manager)
        ->put(hardeningTenantUrl($organization, '/security-policy'), [
            'require_staff_two_factor' => true,
        ])->assertRedirect();

    assertErrorToast();
    expect($organization->refresh()->requiresStaffTwoFactor())->toBeFalse();
});

test('suspended page shows the support contact and reactivation form', function () {
    $this->get(route('suspended'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/suspended')
            ->where('supportEmail', config('mail.from.address')));
});

test('reactivation requests are logged and acknowledged without enumerating accounts', function () {
    $this->post(route('reactivation.store'), ['email' => 'unknown@example.com'])
        ->assertRedirect(route('suspended'));

    $flash = session()->get('inertia.flash_data', []);
    expect($flash['toast']['type'] ?? null)->toBe('success')
        ->and(Audit::where('event', 'reactivation.requested')->exists())->toBeTrue();
});
