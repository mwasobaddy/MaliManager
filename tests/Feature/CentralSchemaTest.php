<?php

use App\Models\Organization;
use App\Models\Person;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;

test('organization belongs to a tenant and a plan', function () {
    $plan = Plan::factory()->free()->create();
    $organization = Organization::factory()->for($plan, 'plan')->create();

    expect($organization->tenant)->toBeInstanceOf(Tenant::class)
        ->and($organization->plan->slug)->toBe('free');
});

test('tenant has one organization', function () {
    $organization = Organization::factory()->create();

    expect($organization->tenant->organization->id)->toBe($organization->id);
});

test('user belongs to a person', function () {
    $person = Person::factory()->create();
    $user = User::factory()->for($person, 'person')->create();

    expect($user->person->id)->toBe($person->id)
        ->and($person->users)->toHaveCount(1);
});
