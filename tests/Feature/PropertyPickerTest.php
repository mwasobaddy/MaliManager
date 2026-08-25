<?php

use App\Models\Delegation;
use App\Models\Organization;
use App\Models\Property;
use App\Models\SubRole;
use App\Models\User;
use App\Services\PropertyAccessService;
use App\Support\AuthLanding;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function onboardedUser(): User
{
    return User::factory()->create(['onboarded_at' => now()]);
}

it('lists every property for an organization owner across all their organizations', function () {
    $user = onboardedUser();

    $orgOne = Organization::factory()->create();
    $orgTwo = Organization::factory()->create();

    $user->organizations()->attach($orgOne->id, ['is_owner' => true, 'status' => 'active']);
    $user->organizations()->attach($orgTwo->id, ['is_owner' => true, 'status' => 'active']);

    $propertyOne = Property::factory()->for($orgOne)->create();
    $propertyTwo = Property::factory()->for($orgTwo)->create();

    $result = app(PropertyAccessService::class)->organizationsWithProperties($user);

    expect($result)->toHaveCount(2);
    expect(collect($result)->flatMap(fn ($org) => $org['properties'])->pluck('id'))
        ->toContain($propertyOne->id, $propertyTwo->id);
});

it('restricts staff to only their delegated properties', function () {
    $user = onboardedUser();
    $org = Organization::factory()->create();
    $subRole = SubRole::factory()->create();

    $user->organizations()->attach($org->id, [
        'is_owner' => false,
        'sub_role_id' => $subRole->id,
        'status' => 'active',
    ]);

    $delegated = Property::factory()->for($org)->create();
    $other = Property::factory()->for($org)->create();

    $membership = $user->membershipFor($org);
    Delegation::create([
        'organization_user_id' => $membership->id,
        'organization_id' => $org->id,
        'delegatable_type' => Property::class,
        'delegatable_id' => $delegated->id,
    ]);

    $result = app(PropertyAccessService::class)->organizationsWithProperties($user);

    $propertyIds = collect($result)->flatMap(fn ($organization) => $organization['properties'])->pluck('id');

    expect($propertyIds)->toContain($delegated->id);
    expect($propertyIds)->not->toContain($other->id);
});

it('redirects to the central dashboard when the user has multiple properties', function () {
    $user = onboardedUser();

    $orgOne = Organization::factory()->create();
    $orgTwo = Organization::factory()->create();

    $user->organizations()->attach($orgOne->id, ['is_owner' => true, 'status' => 'active']);
    $user->organizations()->attach($orgTwo->id, ['is_owner' => true, 'status' => 'active']);

    Property::factory()->for($orgOne)->create();
    Property::factory()->for($orgTwo)->create();

    $landing = AuthLanding::for($user, Request::create('/'));

    expect(Str::contains($landing, 'dashboard'))->toBeTrue();
});

it('redirects straight to the single property tenant domain', function () {
    $user = onboardedUser();
    $org = Organization::factory()->create();

    $user->organizations()->attach($org->id, ['is_owner' => true, 'status' => 'active']);

    $property = Property::factory()->for($org)->create();
    $org->tenant->domains()->create(['domain' => $org->slug.'.malimanager.test']);

    $landing = AuthLanding::for($user, Request::create('/'));

    expect(Str::contains($landing, $property->slug.'/dashboard'))->toBeTrue();
    expect(Str::contains($landing, $org->slug.'.malimanager.test'))->toBeTrue();
});

it('returns the dashboard when the user has no accessible properties', function () {
    $user = onboardedUser();

    $landing = AuthLanding::for($user, Request::create('/'));

    expect(Str::contains($landing, 'dashboard'))->toBeTrue();
});
