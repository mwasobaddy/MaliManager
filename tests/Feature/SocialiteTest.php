<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

test('socialite redirect route forwards to the provider', function () {
    Socialite::shouldReceive('driver->redirect')->once()->andReturn(
        redirect()->away('https://accounts.google.com/o/oauth2/auth')
    );

    $this->get(route('auth.socialite.redirect', ['provider' => 'google']))
        ->assertRedirect('https://accounts.google.com/o/oauth2/auth');
});

test('socialite callback logs in an existing user by email', function () {
    $user = User::factory()->create(['email' => 'existing@example.com', 'onboarded_at' => now()]);

    Socialite::shouldReceive('driver->user')->once()->andReturn(
        SocialiteUser::fake(['name' => 'Existing User', 'email' => 'existing@example.com'])
    );

    $this->get(route('auth.socialite.callback', ['provider' => 'google']))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('socialite callback creates a user when none exists', function () {
    Socialite::shouldReceive('driver->user')->once()->andReturn(
        SocialiteUser::fake(['name' => 'New User', 'email' => 'new@example.com'])
    );

    $this->get(route('auth.socialite.callback', ['provider' => 'google']))
        ->assertRedirect(route('onboarding.show'));

    $user = User::where('email', 'new@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->provider)->toBe('google');
});

test('socialite callback redirects back to login on failure', function () {
    Socialite::shouldReceive('driver->user')->once()->andThrow(
        new RuntimeException('Access denied')
    );

    $this->get(route('auth.socialite.callback', ['provider' => 'google']))
        ->assertRedirect(route('login'));

    assertErrorToast();
});
