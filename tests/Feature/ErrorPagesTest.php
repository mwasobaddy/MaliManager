<?php

use App\Models\User;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

test('full-page 403 renders the branded page outside debug', function () {
    config()->set('app.debug', false);

    $user = User::factory()->create(['onboarded_at' => now()]);

    $this->actingAs($user)
        ->get('/platform/users')
        ->assertForbidden()
        ->assertInertia(fn ($page) => $page->component('errors/403'));
});
