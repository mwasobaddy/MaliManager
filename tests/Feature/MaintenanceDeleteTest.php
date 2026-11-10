<?php

use App\Models\MaintenanceRequest;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Services\TenantService;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

function maintenanceDeleteOrganization(User $owner): Organization
{
    $organization = app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Acme Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );

    $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    return $organization;
}

function maintenanceDeleteRecord(Organization $organization, User $owner): MaintenanceRequest
{
    $property = $organization->properties()->firstOrFail();

    return MaintenanceRequest::create([
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'raised_by' => $owner->id,
        'title' => 'Broken gate',
        'description' => 'Gate motor is dead.',
        'status' => 'opened',
        'priority' => 'high',
    ]);
}

function maintenanceDeleteUrl(Organization $organization, string $path): string
{
    $domain = $organization->tenant->domains()->value('domain');

    return "http://{$domain}{$path}";
}

test('deleting a maintenance request requires the current password', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = maintenanceDeleteOrganization($owner);
    $record = maintenanceDeleteRecord($organization, $owner);

    $this->actingAs($owner)
        ->delete(maintenanceDeleteUrl($organization, "/maintenance/{$record->id}"))
        ->assertRedirect()
        ->assertInvalid('password');

    expect(MaintenanceRequest::find($record->id))->not->toBeNull();
});

test('deleting a maintenance request with the password soft-deletes it', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = maintenanceDeleteOrganization($owner);
    $record = maintenanceDeleteRecord($organization, $owner);

    $this->actingAs($owner)
        ->delete(maintenanceDeleteUrl($organization, "/maintenance/{$record->id}"), [
            'password' => 'password',
        ])
        ->assertRedirect();

    expect(MaintenanceRequest::find($record->id))->toBeNull()
        ->and(MaintenanceRequest::withTrashed()->find($record->id))->not->toBeNull();
});
