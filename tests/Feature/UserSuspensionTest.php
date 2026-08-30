<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
});

test('active user can browse the app', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'onboarded_at' => now(),
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk();
});

test('suspended user is forced to the suspended page on next request', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $victim = User::factory()->create(['status' => 'active']);

    $this->actingAs($admin)
        ->patch(route('users.status', $victim), ['status' => 'suspended'])
        ->assertRedirect();

    $victim->refresh();
    $this->actingAs($victim)
        ->get('/dashboard')
        ->assertRedirect(route('suspended'));
});

test('inactive user is also forced to the suspended page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $victim = User::factory()->create(['status' => 'active']);

    $this->actingAs($admin)
        ->patch(route('users.status', $victim), ['status' => 'inactive'])
        ->assertRedirect();

    $victim->refresh();
    $this->actingAs($victim)
        ->get('/dashboard')
        ->assertRedirect(route('suspended'));
});

test('suspended user receives a 403 on json requests', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $victim = User::factory()->create(['status' => 'active']);

    $this->actingAs($admin)
        ->patch(route('users.status', $victim), ['status' => 'suspended'])
        ->assertRedirect();

    $victim->refresh();
    $this->actingAs($victim)
        ->withHeader('Accept', 'application/json')
        ->get('/dashboard')
        ->assertForbidden();
});

test('an admin cannot suspend their own account', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->patch(route('users.status', $admin), ['status' => 'suspended'])
        ->assertRedirect();

    expect($admin->fresh()->status)->toBe('active');
});

test('suspending a user revokes their active sessions', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $victim = User::factory()->create(['status' => 'active']);

    DB::table('sessions')->insert([
        'id' => 'test-session',
        'user_id' => $victim->id,
        'payload' => '',
        'last_activity' => time(),
    ]);

    expect(DB::table('sessions')->where('user_id', $victim->id)->count())->toBe(1);

    $this->actingAs($admin)
        ->patch(route('users.status', $victim), ['status' => 'suspended'])
        ->assertRedirect();

    expect(DB::table('sessions')->where('user_id', $victim->id)->count())->toBe(0);
});

test('guest can view the suspended page', function () {
    $this->get(route('suspended'))->assertOk();
});

test('suspended user cannot log in via socialite', function () {
    $suspended = User::factory()->create(['status' => 'suspended']);

    Socialite::fake('google', (new SocialiteUser)->map([
        'id' => '1',
        'email' => $suspended->email,
        'name' => 'Suspended User',
    ]));

    $this->get(route('auth.socialite.callback', 'google'))
        ->assertRedirect(route('login'));
});
