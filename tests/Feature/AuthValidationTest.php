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

test('onboarding rejects an invalid account type', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('onboarding.complete'), [
            'account_type' => 'landlord',
            'name' => 'Jane Doe',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('account_type');
});

test('onboarding requires a password of at least eight characters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('onboarding.complete'), [
            'account_type' => 'occupant',
            'name' => 'Jane Doe',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
});

test('onboarding requires a three-character currency code for organizations', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('onboarding.complete'), [
            'account_type' => 'organization',
            'name' => 'Jane Doe',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_name' => 'Sunset Apartments',
            'currency' => 'KE',
            'plan_slug' => 'free',
        ])->assertSessionHasErrors('currency');
});
