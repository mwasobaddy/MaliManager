<?php

use App\Actions\Metering\RecordMeterReading;
use App\Data\Metering\RecordMeterReadingData;
use App\Enums\UtilityType;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Unit;
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

function actionMeterGraph(): array
{
    $owner = User::factory()->create(['onboarded_at' => now()]);

    $organization = app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Action Meter Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );

    $property = $organization->properties()->create([
        'name' => 'Action Court',
        'slug' => 'action-court',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $unit = Unit::factory()->create(['property_id' => $property->id]);

    return [$owner, $organization, $unit];
}

function actionMeterData(
    Organization $organization,
    Unit $unit,
    string $reading,
    string $readOn,
    UtilityType $utility = UtilityType::Electricity,
): RecordMeterReadingData {
    return new RecordMeterReadingData(
        organizationId: $organization->id,
        unitId: $unit->id,
        utility: $utility,
        reading: $reading,
        readOn: $readOn,
    );
}

test('the action returns null consumption when there is no earlier reading', function () {
    [$owner, $organization, $unit] = actionMeterGraph();

    $reading = app(RecordMeterReading::class)
        ->handle($owner, actionMeterData($organization, $unit, '500.000', '2026-01-01'));

    expect($reading->consumption)->toBeNull();
});

test('the action subtracts the previous reading of the same meter', function () {
    [$owner, $organization, $unit] = actionMeterGraph();

    $action = app(RecordMeterReading::class);

    $action->handle($owner, actionMeterData($organization, $unit, '500.000', '2026-01-01'));
    $second = $action->handle($owner, actionMeterData($organization, $unit, '642.250', '2026-02-01'));

    expect((float) $second->consumption)->toBe(142.25);
});

test('the action ignores a previous reading for a different utility', function () {
    [$owner, $organization, $unit] = actionMeterGraph();

    $action = app(RecordMeterReading::class);

    $action->handle($owner, actionMeterData($organization, $unit, '500.000', '2026-01-01', UtilityType::Electricity));
    $water = $action->handle($owner, actionMeterData($organization, $unit, '80.000', '2026-02-01', UtilityType::Water));

    expect($water->consumption)->toBeNull();
});

test('the action refuses to report a negative consumption when the meter runs backwards', function () {
    [$owner, $organization, $unit] = actionMeterGraph();

    $action = app(RecordMeterReading::class);

    $action->handle($owner, actionMeterData($organization, $unit, '900.000', '2026-01-01'));
    $afterReset = $action->handle($owner, actionMeterData($organization, $unit, '10.000', '2026-02-01'));

    expect($afterReset->consumption)->toBeNull()
        ->and((float) $afterReset->reading)->toBe(10.0);
});
