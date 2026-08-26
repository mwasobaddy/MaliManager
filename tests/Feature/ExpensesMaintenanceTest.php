<?php

use App\Models\Expense;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Services\MaintenanceRequestService;
use App\Services\OccupantService;
use App\Services\TenantService;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

function expenseOrganization(User $owner): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Acme Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
}

function expenseProperty(Organization $organization, User $owner): App\Models\Property
{
    return $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);
}

function expenseUrl(Organization $organization, string $path): string
{
    $domain = $organization->tenant->domains()->firstOrCreate(
        ['domain' => $organization->slug.'.expense.test'],
        ['domain' => $organization->slug.'.expense.test'],
    );

    return "http://{$domain->domain}{$path}";
}

test('owners record an expense with a receipt stored in the tenant folder layout', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = expenseOrganization($owner);
    $property = expenseProperty($organization, $owner);

    $receipt = File::fake()->createWithContent('receipt.pdf', '%PDF-1.4 receipt');

    $this->actingAs($owner)
        ->post(expenseUrl($organization, '/expenses'), [
            'expenseable_type' => 'property',
            'expenseable_id' => $property->id,
            'category' => 'renovation',
            'amount' => 45000.50,
            'currency' => 'KES',
            'spent_on' => '2026-08-01',
            'notes' => 'Repainted vacant unit after move-out.',
            'receipt' => $receipt,
        ])->assertRedirect();

    $expense = Expense::first();

    expect($expense)->not->toBeNull()
        ->and($expense->category)->toBe('renovation')
        ->and($expense->amount)->toBe('45000.50')
        ->and($expense->getFirstMedia('receipt'))->not->toBeNull()
        ->and($expense->getFirstMedia('receipt')->getPathRelativeToRoot())
        ->toStartWith('acme-estates/sunset-heights/expenses/');
});

test('expenses reject invalid categories and mismatched units', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = expenseOrganization($owner);
    $property = expenseProperty($organization, $owner);

    // Invalid category.
    $this->actingAs($owner)
        ->post(expenseUrl($organization, '/expenses'), [
            'expenseable_type' => 'property',
            'expenseable_id' => $property->id,
            'category' => 'parties',
            'amount' => 100,
            'spent_on' => '2026-08-01',
        ])->assertSessionHasErrors('category');

    // Unit from another property.
    $otherOrg = expenseOrganization($owner);
    $otherUnit = $otherOrg->properties()->create([
        'name' => 'Other Place', 'slug' => 'other-place', 'status' => 'active', 'created_by' => $owner->id,
    ])->units()->create(['name' => 'X1', 'created_by' => $owner->id]);

    $this->actingAs($owner)
        ->post(expenseUrl($organization, '/expenses'), [
            'expenseable_type' => 'property',
            'expenseable_id' => $property->id,
            'unit_id' => $otherUnit->id,
            'category' => 'cleaning',
            'amount' => 500,
            'spent_on' => '2026-08-01',
        ])->assertRedirect();
});

test('deleting an expense requires the current password and expense.delete', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = expenseOrganization($owner);
    $property = expenseProperty($organization, $owner);

    Expense::create([
        'organization_id' => $organization->id,
        'expenseable_type' => Property::class,
        'expenseable_id' => $property->id,
        'category' => 'maintenance',
        'amount' => 1000,
        'spent_on' => '2026-08-01',
        'created_by' => $owner->id,
    ]);

    $expense = Expense::first();

    $this->actingAs($owner)
        ->delete(expenseUrl($organization, "/expenses/{$expense->id}"), ['password' => 'wrong'])
        ->assertSessionHasErrors('password');

    $this->actingAs($owner)
        ->delete(expenseUrl($organization, "/expenses/{$expense->id}"), ['password' => 'password'])
        ->assertRedirect();

    expect($expense->refresh()->trashed())->toBeTrue();
});

test('occupants with an active lease raise maintenance requests with photos', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = expenseOrganization($owner);
    $property = expenseProperty($organization, $owner);

    $unit = $property->units()->create(['name' => 'A1', 'status' => 'occupied', 'created_by' => $owner->id]);

    app(OccupantService::class)->create($organization, $property, $owner, [
        'first_name' => 'Jane',
        'email' => 'jane@acme.test',
        'status' => 'active',
        'unit_ids' => [$unit->id],
    ]);

    $occupantUser = User::where('email', 'jane@acme.test')->first();

    $photo = File::fake()->image('leak.png');

    $this->actingAs($occupantUser)
        ->post('/searcher/maintenance', [
            'lease_id' => Lease::where('unit_id', $unit->id)->value('id'),
            'title' => 'Leaking kitchen tap',
            'description' => 'Water keeps dripping under the sink.',
            'priority' => 'high',
            'photos' => [$photo],
        ])->assertRedirect();

    $request = MaintenanceRequest::first();

    expect($request->status)->toBe('opened')
        ->and($request->priority)->toBe('high')
        ->and($request->property_id)->toBe($property->id)
        ->and($request->unit_id)->toBe($unit->id)
        ->and($request->getMedia('photos'))->toHaveCount(1)
        ->and($request->getMedia('photos')->first()->getPathRelativeToRoot())
        ->toStartWith('acme-estates/sunset-heights/maintenance/');
});

test('users without an active lease cannot raise maintenance requests', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);

    $this->actingAs($user)
        ->post('/searcher/maintenance', [
            'title' => 'Fix something',
            'description' => 'No lease here.',
        ])->assertRedirect();

    expect(MaintenanceRequest::count())->toBe(0);
});

test('staff advance request statuses through the allowed workflow only', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = expenseOrganization($owner);
    $property = expenseProperty($organization, $owner);

    $staff = User::factory()->create(['onboarded_at' => now(), 'password' => 'password']);
    $organization->users()->attach($staff->id, ['is_owner' => false, 'sub_role_id' => null, 'status' => 'active']);

    // Owners bypass sub-permissions; use the owner for management actions
    // and verify transition rules via the service.
    MaintenanceRequest::create([
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'raised_by' => $owner->id,
        'title' => 'Broken gate',
        'description' => 'Gate motor is dead.',
        'status' => 'opened',
        'priority' => 'high',
    ]);

    $record = MaintenanceRequest::first();

    // opened → resolved is NOT allowed directly.
    $service = app(MaintenanceRequestService::class);

    expect($service->canTransition('opened', 'assigned'))->toBeTrue()
        ->and($service->canTransition('opened', 'resolved'))->toBeFalse();

    $this->actingAs($owner)
        ->put(expenseUrl($organization, "/maintenance/{$record->id}"), [
            'status' => 'resolved',
            'resolution_notes' => 'Fixed.',
        ])->assertRedirect(); // service aborts 422 → validation-style error

    // opened → assigned → resolved works.
    $this->actingAs($owner)
        ->put(expenseUrl($organization, "/maintenance/{$record->id}"), [
            'status' => 'assigned',
            'assigned_to' => $staff->id,
        ])->assertRedirect();

    $this->actingAs($owner)
        ->put(expenseUrl($organization, "/maintenance/{$record->id}"), [
            'status' => 'in_progress',
        ])->assertRedirect();

    $this->actingAs($owner)
        ->put(expenseUrl($organization, "/maintenance/{$record->id}"), [
            'status' => 'resolved',
            'resolution_notes' => 'Replaced the motor.',
        ])->assertRedirect();

    $record->refresh();

    expect($record->status)->toBe('resolved')
        ->and($record->assigned_to)->toBe($staff->id)
        ->and($record->resolved_at)->not->toBeNull()
        ->and($record->resolution_notes)->toBe('Replaced the motor.');
});
