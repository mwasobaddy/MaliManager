<?php

use App\Enums\AiProvider;
use App\Exceptions\AssistantUnavailableException;
use App\Models\Expense;
use App\Models\Lease;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Support\Ai\AssistantService;
use App\Support\Ai\DataQueryService;
use App\Support\Ai\Scope;
use App\Support\Ai\SemanticLayer;
use App\Support\Ai\TextSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('nvidia provider maps to a groq compatible prism driver', function () {
    expect(AiProvider::Nvidia->prismDriver())->toBe('groq');
});

test('semantic layer catalog is scoped to the caller', function () {
    $platform = SemanticLayer::catalogFor(Scope::PLATFORM);
    expect($platform)->toHaveKey('units')->toHaveKey('leases')->toHaveKey('organizations');

    $person = SemanticLayer::catalogFor(Scope::PERSON);
    expect(array_keys($person))->toBe(['leases', 'maintenance_requests']);
});

test('data query is isolated per organization and validates input', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();

    $propertyA = Property::factory()->create(['organization_id' => $orgA->id]);
    $propertyB = Property::factory()->create(['organization_id' => $orgB->id]);

    Unit::factory()->count(3)->create(['property_id' => $propertyA->id, 'status' => 'occupied']);
    Unit::factory()->count(2)->create(['property_id' => $propertyA->id, 'status' => 'vacant']);
    Unit::factory()->count(4)->create(['property_id' => $propertyB->id, 'status' => 'occupied']);

    $service = app(DataQueryService::class);

    $result = $service->run(['entity' => 'units', 'group_by' => ['status']], Scope::org($orgA->id));
    $rows = collect($result['rows']);
    expect($rows->where('status', 'occupied')->first()['count'])->toBe(3)
        ->and($rows->where('status', 'vacant')->first()['count'])->toBe(2);

    $platformResult = $service->run(['entity' => 'units', 'group_by' => ['status']], Scope::platform());
    expect(collect($platformResult['rows'])->sum('count'))->toBe(9);

    expect(fn () => $service->run(['entity' => 'not_a_table'], Scope::org($orgA->id)))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => $service->run(['entity' => 'units', 'group_by' => ['secret_column']], Scope::org($orgA->id)))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => $service->run(['entity' => 'units', 'measures' => [['type' => 'sum', 'column' => 'status']]], Scope::org($orgA->id)))
        ->toThrow(InvalidArgumentException::class);
});

test('person scope only sees their own leases', function () {
    $org = Organization::factory()->create();
    $property = Property::factory()->create(['organization_id' => $org->id]);
    $unit = Unit::factory()->create(['property_id' => $property->id]);

    $me = Person::factory()->create();
    $other = Person::factory()->create();

    Lease::create(['person_id' => $me->id, 'organization_id' => $org->id, 'property_id' => $property->id, 'unit_id' => $unit->id, 'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addYear()]);
    Lease::create(['person_id' => $other->id, 'organization_id' => $org->id, 'property_id' => $property->id, 'unit_id' => $unit->id, 'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addYear()]);

    $service = app(DataQueryService::class);
    $result = $service->run(['entity' => 'leases', 'group_by' => ['status']], Scope::person($me->id, 1));

    expect($result['row_count'])->toBe(1);
});

test('text search is scoped to the organization', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();

    Expense::create(['organization_id' => $orgA->id, 'category' => 'repair', 'amount' => 100, 'currency' => 'KES', 'spent_on' => now(), 'notes' => 'burst geyser pipe']);
    Expense::create(['organization_id' => $orgB->id, 'category' => 'repair', 'amount' => 100, 'currency' => 'KES', 'spent_on' => now(), 'notes' => 'burst geyser pipe']);

    $service = app(TextSearchService::class);
    $result = $service->search('expenses', 'geyser', Scope::org($orgA->id), 20);

    expect($result['rows'])->toHaveCount(1);
});

test('assistant ask throws when no credential is available', function () {
    $user = User::factory()->create();
    $service = app(AssistantService::class);

    expect(fn () => $service->ask($user, Scope::platform(), 'hello'))
        ->toThrow(AssistantUnavailableException::class);
    expect(fn () => $service->ask($user, Scope::org(999), 'hello'))
        ->toThrow(AssistantUnavailableException::class);
});
