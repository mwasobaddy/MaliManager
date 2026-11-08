<?php

use App\Models\User;
use App\Support\OtpService;

test('login rejects emails longer than 255 characters', function () {
    $this->post(route('login.store'), ['email' => str_repeat('a', 250).'@example.com'])
        ->assertSessionHasErrors('email');
});

test('otp verify rejects codes that are not six digits', function () {
    $user = User::factory()->create(['email' => 'existing@example.com']);
    app(OtpService::class)->issue($user);

    session(['login.email' => 'existing@example.com']);

    $this->post(route('login.otp.verify'), ['code' => '12345'])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('onboarding requires a name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('onboarding.complete'), [
            'name' => '',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('name');
});

test('onboarding requires a password of at least eight characters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('onboarding.complete'), [
            'name' => 'Jane Doe',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
});

test('landlord upgrade requires a three-character currency code', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);

    $this->actingAs($user)
        ->post(route('guest.become-landlord'), [
            'organization_name' => 'Sunset Apartments',
            'currency' => 'KE',
            'plan_slug' => 'free',
        ])->assertSessionHasErrors('currency');
});
