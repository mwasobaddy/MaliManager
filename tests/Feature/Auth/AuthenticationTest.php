<?php

use App\Models\User;
use App\Support\OtpService;
use Illuminate\Support\Facades\RateLimiter;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using a one-time code', function () {
    Mail::fake();

    $user = User::factory()->create(['email' => 'existing@example.com', 'onboarded_at' => now()]);
    $code = app(OtpService::class)->issue($user);

    session(['login.email' => 'existing@example.com']);

    $response = $this->post(route('login.otp.verify'), ['code' => $code]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('users are rate limited on login', function () {
    $user = User::factory()->create(['email' => 'limited@example.com']);

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
    ]);

    $response->assertTooManyRequests();
});

test('users are rate limited on otp verification', function () {
    session(['login.email' => 'limited@example.com']);

    RateLimiter::increment(md5('otp'.implode('|', ['limited@example.com', '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.otp.verify'), [
        'code' => '123456',
    ]);

    $response->assertTooManyRequests();
});
