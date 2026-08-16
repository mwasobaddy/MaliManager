<?php

use App\Mail\LoginOtpMail;
use App\Models\LoginOtp;
use App\Models\User;
use App\Support\OtpService;
use Illuminate\Support\Facades\Mail;

test('login sends an OTP for an existing user and stores the email', function () {
    Mail::fake();

    $user = User::factory()->create(['email' => 'existing@example.com']);

    $this->post(route('login.store'), ['email' => 'existing@example.com'])
        ->assertRedirect(route('login.otp'));

    Mail::assertSent(LoginOtpMail::class, fn ($mail) => $mail->hasTo('existing@example.com'));

    $this->assertGuest();
    expect(session('login.email'))->toBe('existing@example.com')
        ->and(LoginOtp::where('user_id', $user->id)->count())->toBe(1);
});

test('login creates a user from an unknown email and sends an OTP', function () {
    Mail::fake();

    $this->post(route('login.store'), ['email' => 'brand.new@example.com'])
        ->assertRedirect(route('login.otp'));

    Mail::assertSent(LoginOtpMail::class);

    $user = User::where('email', 'brand.new@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Brand.new')
        ->and(LoginOtp::where('user_id', $user->id)->count())->toBe(1);
});

test('login validates the email', function () {
    $this->post(route('login.store'), ['email' => 'not-an-email'])
        ->assertSessionHasErrors('email');
});

test('otp page requires a stored email', function () {
    $this->get(route('login.otp'))->assertRedirect(route('login'));
});

test('otp page shows the pending email', function () {
    session(['login.email' => 'existing@example.com']);

    $this->get(route('login.otp'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/otp')
            ->where('email', 'existing@example.com'));
});

test('correct code marks the email verified and logs the user in', function () {
    Mail::fake();

    $user = User::factory()->create(['email' => 'existing@example.com']);
    $code = app(OtpService::class)->issue($user);

    session(['login.email' => 'existing@example.com']);

    $this->post(route('login.otp.verify'), ['code' => $code])
        ->assertRedirect(route('onboarding.show'));

    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->email_verified_at)->not->toBeNull()
        ->and(session('login.email'))->toBeNull()
        ->and(LoginOtp::where('user_id', $user->id)->first()->used_at)->not->toBeNull();
});

test('correct code redirects onboarded users to the dashboard', function () {
    Mail::fake();

    $user = User::factory()->create(['email' => 'existing@example.com', 'onboarded_at' => now()]);
    $code = app(OtpService::class)->issue($user);

    session(['login.email' => 'existing@example.com']);

    $this->post(route('login.otp.verify'), ['code' => $code])
        ->assertRedirect(route('dashboard'));
});

test('wrong code is rejected', function () {
    Mail::fake();

    $user = User::factory()->create(['email' => 'existing@example.com']);
    app(OtpService::class)->issue($user);

    session(['login.email' => 'existing@example.com']);

    $this->post(route('login.otp.verify'), ['code' => '000000'])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('expired code is rejected', function () {
    Mail::fake();

    $user = User::factory()->create(['email' => 'existing@example.com']);
    LoginOtp::create([
        'user_id' => $user->id,
        'code' => '123456',
        'expires_at' => now()->subMinute(),
    ]);

    session(['login.email' => 'existing@example.com']);

    $this->post(route('login.otp.verify'), ['code' => '123456'])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('used code cannot be reused', function () {
    Mail::fake();

    $user = User::factory()->create(['email' => 'existing@example.com']);
    $code = app(OtpService::class)->issue($user);

    session(['login.email' => 'existing@example.com']);
    $this->post(route('login.otp.verify'), ['code' => $code])->assertRedirect();

    $this->post('/logout');

    session(['login.email' => 'existing@example.com']);
    $this->post(route('login.otp.verify'), ['code' => $code])
        ->assertSessionHasErrors('code');
});

test('resend reuses the existing code when it is still valid', function () {
    Mail::fake();

    $user = User::factory()->create(['email' => 'existing@example.com']);
    $code = app(OtpService::class)->issue($user);

    session(['login.email' => 'existing@example.com']);

    $this->post(route('login.otp.resend'))
        ->assertRedirect()
        ->assertSessionHas('status');

    expect(LoginOtp::where('user_id', $user->id)->count())->toBe(1);

    Mail::assertSent(LoginOtpMail::class, fn ($mail) => $mail->code === $code);
});

test('resend generates a new code when the previous one has expired', function () {
    Mail::fake();

    $user = User::factory()->create(['email' => 'existing@example.com']);
    LoginOtp::create([
        'user_id' => $user->id,
        'code' => '654321',
        'expires_at' => now()->subMinute(),
    ]);

    session(['login.email' => 'existing@example.com']);

    $this->post(route('login.otp.resend'))->assertRedirect();

    $otp = LoginOtp::where('user_id', $user->id)->latest('id')->first();
    expect($otp->code)->not->toBe('654321')
        ->and(LoginOtp::where('user_id', $user->id)->count())->toBe(2);
});

test('resend requires a stored email', function () {
    $this->post(route('login.otp.resend'))->assertRedirect(route('login'));
});
