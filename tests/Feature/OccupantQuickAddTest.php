<?php

use App\Models\Lease;
use App\Models\Occupant;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Plan;
use App\Models\Property;
use App\Models\User;
use App\Services\TenantService;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

function quickAddOrganization(User $owner, string $name = 'Acme Estates'): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $owner,
        name: $name,
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
}

function quickAddProperty(Organization $organization, User $owner, string $name = 'Sunset Heights'): Property
{
    $property = $organization->properties()->create([
        'name' => $name,
        'slug' => str($name)->slug(),
        'status' => 'active',
        'created_by' => $owner->id,
    ]);
    $property->units()->create([
        'name' => 'A1',
        'status' => 'vacant',
        'rent_amount' => 25000,
        'rent_frequency' => 'monthly',
        'created_by' => $owner->id,
    ]);

    return $property->refresh();
}

function quickAddTenantUrl(Organization $organization, string $path, Property $property): string
{
    $domain = $organization->tenant->domains()->value('domain');

    return "http://{$domain}".str_replace('{property}', $property->slug, $path);
}

test('quick add creates a tenant without an email or user account', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = quickAddOrganization($owner);
    $property = quickAddProperty($organization, $owner);
    $unitId = $property->units()->where('name', 'A1')->value('id');

    $this->actingAs($owner)
        ->post(quickAddTenantUrl($organization, '/{property}/occupants', $property), [
            'first_name' => 'John',
            'phone' => '0712000001',
            'status' => 'active',
            'unit_ids' => [$unitId],
            'move_in_now' => true,
            'lease' => ['rent_amount' => 5000, 'rent_frequency' => 'monthly', 'currency' => 'KES'],
        ])->assertRedirect();

    $person = Person::where('phone', '+254712000001')->firstOrFail();
    expect($person->email)->toBeNull();

    $occupant = Occupant::where('organization_id', $organization->id)->where('person_id', $person->id)->firstOrFail();
    expect($occupant->status)->toBe('active');

    expect(Lease::where('person_id', $person->id)->where('unit_id', $unitId)->where('status', 'active')->exists())->toBeTrue()
        ->and(User::where('phone', '+254712000001')->where('notification_channel', 'sms')->exists())->toBeTrue();
});

test('quick add reuses the person record for a known phone number', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = quickAddOrganization($owner);
    $property = quickAddProperty($organization, $owner);
    $unitId = $property->units()->where('name', 'A1')->value('id');

    $payload = [
        'first_name' => 'John',
        'phone' => '0712000002',
        'status' => 'active',
        'unit_ids' => [$unitId],
        'move_in_now' => true,
        'lease' => ['rent_amount' => 5000, 'rent_frequency' => 'monthly', 'currency' => 'KES'],
    ];

    $this->actingAs($owner)->post(quickAddTenantUrl($organization, '/{property}/occupants', $property), $payload)->assertRedirect();
    $this->actingAs($owner)->post(quickAddTenantUrl($organization, '/{property}/occupants', $property), $payload)->assertRedirect();

    expect(Person::where('phone', '+254712000002')->count())->toBe(1);
});

test('move-in requires the first rent amount up front', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = quickAddOrganization($owner);
    $property = quickAddProperty($organization, $owner);
    $unitId = $property->units()->where('name', 'A1')->value('id');

    $this->actingAs($owner)
        ->post(quickAddTenantUrl($organization, '/{property}/occupants', $property), [
            'first_name' => 'John',
            'phone' => '0712000003',
            'status' => 'active',
            'unit_ids' => [$unitId],
            'move_in_now' => true,
            'lease' => ['rent_frequency' => 'monthly', 'currency' => 'KES'],
        ])->assertSessionHasErrors('lease.rent_amount');

    expect(Occupant::where('organization_id', $organization->id)->count())->toBe(0);
});

test('an occupant needs at least an email or a phone number', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = quickAddOrganization($owner);
    $property = quickAddProperty($organization, $owner);
    $unitId = $property->units()->where('name', 'A1')->value('id');

    $this->actingAs($owner)
        ->post(quickAddTenantUrl($organization, '/{property}/occupants', $property), [
            'first_name' => 'Ghost',
            'status' => 'active',
            'unit_ids' => [$unitId],
            'move_in_now' => true,
            'lease' => ['rent_amount' => 5000, 'rent_frequency' => 'monthly', 'currency' => 'KES'],
        ])->assertSessionHasErrors('email');

    expect(Occupant::where('organization_id', $organization->id)->count())->toBe(0);
});

test('adding an email later links the occupant to a user account', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = quickAddOrganization($owner);
    $property = quickAddProperty($organization, $owner);
    $unitId = $property->units()->where('name', 'A1')->value('id');

    $this->actingAs($owner)
        ->post(quickAddTenantUrl($organization, '/{property}/occupants', $property), [
            'first_name' => 'Jane',
            'phone' => '0712000004',
            'status' => 'active',
            'unit_ids' => [$unitId],
            'move_in_now' => true,
            'lease' => ['rent_amount' => 5000, 'rent_frequency' => 'monthly', 'currency' => 'KES'],
        ])->assertRedirect();

    $occupant = Occupant::where('organization_id', $organization->id)->firstOrFail();

    $this->actingAs($owner)
        ->put(quickAddTenantUrl($organization, "/{property}/occupants/{$occupant->id}", $property), [
            'first_name' => 'Jane',
            'email' => 'jane@example.com',
            'phone' => '0712000004',
            'status' => 'active',
            'unit_ids' => [$unitId],
        ])->assertRedirect();

    expect($occupant->refresh()->person->email)->toBe('jane@example.com')
        ->and(User::where('email', 'jane@example.com')->exists())->toBeTrue();

    $linked = User::where('email', 'jane@example.com')->firstOrFail();
    expect($linked->person_id)->toBe($occupant->refresh()->person_id);
});

test('occupant CSV import moves tenants in and reports bad rows', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = quickAddOrganization($owner);
    $property = quickAddProperty($organization, $owner);
    $property->units()->create([
        'name' => 'A2',
        'status' => 'vacant',
        'rent_amount' => 30000,
        'rent_frequency' => 'monthly',
        'created_by' => $owner->id,
    ]);

    $csv = implode("\n", [
        'First name,Last name,Email,Phone,Unit,Rent amount',
        'Alice,Wanjiru,alice@example.com,0712000010,A1,25000',
        ',Missing,Missing,0712000011,A2,30000',
        'Bob,,bob-at-example,0712000012,A2,30000',
        'Cara,,cara@example.com,0712000013,NoSuchUnit,30000',
    ]);

    $this->actingAs($owner)
        ->post(quickAddTenantUrl($organization, '/{property}/occupants/import', $property), [
            'file' => UploadedFile::fake()->createWithContent('tenants.csv', $csv),
        ])->assertRedirect();

    $flash = session()->get('inertia.flash_data', []);
    expect($flash['toast']['type'] ?? null)->toBe('warning');

    $errors = collect($flash['importErrors'] ?? [])->all();
    expect(Occupant::where('organization_id', $organization->id)->count())->toBe(1)
        ->and(Person::where('email', 'alice@example.com')->exists())->toBeTrue()
        ->and(count($errors))->toBe(3);
});
