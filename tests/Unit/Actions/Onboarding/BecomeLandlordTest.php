<?php

use App\Actions\Onboarding\BecomeLandlord;
use App\Data\Onboarding\BecomeLandlordData;
use App\Models\User;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

function landlordOwner(): array
{
    $owner = User::factory()->create(['onboarded_at' => now()]);

    return [$owner];
}

function landlordData(array $overrides = []): BecomeLandlordData
{
    return new BecomeLandlordData(
        organizationName: $overrides['organization_name'] ?? 'Upgrade Estates',
        currency: $overrides['currency'] ?? 'KES',
        listPublicly: $overrides['list_publicly'] ?? false,
    );
}

test('the action provisions the organization without the public opt-in by default', function () {
    [$owner] = landlordOwner();

    $organization = app(BecomeLandlord::class)->handle($owner, landlordData());

    expect($organization->settings['list_publicly'] ?? false)->toBeFalse()
        ->and($owner->isOwnerOf($organization))->toBeTrue();
});

test('the action starts every organization on the free plan without a trial', function () {
    [$owner] = landlordOwner();

    $organization = app(BecomeLandlord::class)->handle($owner, landlordData());

    expect($organization->plan->slug)->toBe('free')
        ->and($organization->trial_ends_at)->toBeNull()
        ->and($organization->plan_confirmed_at)->toBeNull();
});

test('the action applies the public listing opt-in when set', function () {
    [$owner] = landlordOwner();

    $organization = app(BecomeLandlord::class)->handle($owner, landlordData(['list_publicly' => true]));

    expect($organization->settings['list_publicly'])->toBeTrue();
});
