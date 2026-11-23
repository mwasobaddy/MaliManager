<?php

use App\Models\Lease;
use App\Models\Occupant;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Property;
use App\Models\SubPermission;
use App\Models\SubRole;
use App\Models\Unit;
use App\Models\User;
use App\Services\TenantService;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

function makeUnitProperty(User $owner, string $slug = 'unit-towers'): Property
{
    $organization = app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Unit Towers',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );

    return $organization->properties()->create([
        'name' => 'Unit Towers Residences',
        'slug' => $slug,
        'status' => 'active',
        'created_by' => $owner->id,
    ]);
}

function makeUnit(Property $property, User $owner, array $overrides = []): Unit
{
    return $property->units()->create(array_merge([
        'name' => 'A1',
        'status' => 'vacant',
        'rent_amount' => 25000,
        'rent_frequency' => 'monthly',
        'created_by' => $owner->id,
    ], $overrides));
}

function staffWith(User $owner, Organization $organization, array $keys): User
{
    $staff = User::factory()->create(['onboarded_at' => now()]);
    $role = SubRole::factory()->create([
        'organization_id' => $organization->id,
        'created_by' => $owner->id,
    ]);
    $role->subPermissions()->attach(
        SubPermission::whereIn('key', $keys)->pluck('id')->all(),
    );
    $organization->users()->attach($staff->id, ['sub_role_id' => $role->id, 'status' => 'active']);

    return $staff;
}

function occupyUnit(Property $property, Unit $unit, User $owner): Lease
{
    $occupant = Occupant::factory()->create(['organization_id' => $property->organization_id]);

    return Lease::factory()->create([
        'organization_id' => $property->organization_id,
        'property_id' => $property->id,
        'unit_id' => $unit->id,
        'person_id' => $occupant->person_id,
        'status' => 'active',
        'starts_at' => now()->subMonthsNoOverflow(2)->toDateString(),
        'ends_at' => now()->addMonthsNoOverflow(4)->toDateString(),
        'rent_amount' => 25000,
        'rent_due_day' => 5,
    ]);
}

test('an owner can update a unit', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = makeUnitProperty($owner);
    $unit = makeUnit($property, $owner);

    $this->actingAs($owner)
        ->put(tenantUrl($property->organization, "/units/{$unit->id}"), [
            'name' => 'A1-Renamed',
            'type' => '2 Bedroom',
            'rent_amount' => 30000,
            'rent_frequency' => 'monthly',
        ])
        ->assertRedirect();

    expect($unit->fresh())
        ->name->toBe('A1-Renamed')
        ->type->toBe('2 Bedroom')
        ->rent_amount->toBe('30000.00');
});

test('unit update validates its fields', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = makeUnitProperty($owner);
    $unit = makeUnit($property, $owner);

    $this->actingAs($owner)
        ->put(tenantUrl($property->organization, "/units/{$unit->id}"), [
            'name' => '',
            'rent_amount' => -5,
            'rent_frequency' => 'monthly',
        ])
        ->assertSessionHasErrors(['name', 'rent_amount']);
});

test('staff with unit.edit can update, others get 403', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = makeUnitProperty($owner);
    $unit = makeUnit($property, $owner);

    $editor = staffWith($owner, $property->organization, ['unit.manage', 'unit.edit']);

    $this->actingAs($editor)
        ->put(tenantUrl($property->organization, "/units/{$unit->id}"), [
            'name' => 'A1-Edited',
            'rent_frequency' => 'monthly',
        ])
        ->assertRedirect();

    expect($unit->fresh()->name)->toBe('A1-Edited');

    $viewer = staffWith($owner, $property->organization, ['unit.manage']);

    $this->actingAs($viewer)
        ->put(tenantUrl($property->organization, "/units/{$unit->id}"), [
            'name' => 'A1-Hacked',
            'rent_frequency' => 'monthly',
        ])
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data', fn (array $flash) => str_contains(
            $flash['toast']['message'] ?? '',
            'do not have permission',
        ));

    expect($unit->fresh()->name)->toBe('A1-Edited');
});

test('an owner can delete a vacant unit', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = makeUnitProperty($owner);
    $unit = makeUnit($property, $owner);

    $this->actingAs($owner)
        ->delete(tenantUrl($property->organization, "/units/{$unit->id}"), [
            'password' => 'password',
        ])
        ->assertRedirect();

    expect($unit->fresh()->trashed())->toBeTrue();
});

test('an occupied unit cannot be deleted', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = makeUnitProperty($owner);
    $unit = makeUnit($property, $owner, ['status' => 'occupied']);
    occupyUnit($property, $unit, $owner);

    $this->actingAs($owner)
        ->delete(tenantUrl($property->organization, "/units/{$unit->id}"), [
            'password' => 'password',
        ])
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data', fn (array $flash) => str_contains(
            $flash['toast']['message'] ?? '',
            'occupied unit cannot be deleted',
        ));

    expect($unit->fresh()->trashed())->toBeFalse();
});

test('staff without unit.delete gets 403 on destroy', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = makeUnitProperty($owner);
    $unit = makeUnit($property, $owner);
    $staff = staffWith($owner, $property->organization, ['unit.manage', 'unit.edit']);

    $this->actingAs($staff)
        ->delete(tenantUrl($property->organization, "/units/{$unit->id}"), [
            'password' => 'password',
        ])
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data', fn (array $flash) => str_contains(
            $flash['toast']['message'] ?? '',
            'do not have permission',
        ));

    expect($unit->fresh()->trashed())->toBeFalse();
});

test('a unit from another organization answers 404', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = makeUnitProperty($owner);
    $unit = makeUnit($property, $owner);

    $other = User::factory()->create(['onboarded_at' => now()]);
    $otherProperty = makeUnitProperty($other, 'other-towers');

    $this->actingAs($other)
        ->put(tenantUrl($otherProperty->organization, "/units/{$unit->id}"), [
            'name' => 'A1-Hacked',
            'rent_frequency' => 'monthly',
        ])
        ->assertNotFound();
});

test('an edit can move a unit with no leases to another property', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = makeUnitProperty($owner);
    $other = $property->organization->properties()->create([
        'name' => 'Second Block',
        'slug' => 'second-block',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);
    $unit = makeUnit($property, $owner, ['name' => 'A1']);

    $this->actingAs($owner)
        ->put(tenantUrl($property->organization, "/units/{$unit->id}"), [
            'name' => 'A1',
            'rent_frequency' => 'monthly',
            'property_id' => $other->id,
        ])
        ->assertRedirect();

    expect($unit->fresh()->property_id)->toBe($other->id);
});

test('an edit cannot move a unit with an active lease', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = makeUnitProperty($owner);
    $other = $property->organization->properties()->create([
        'name' => 'Second Block',
        'slug' => 'second-block',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);
    $unit = makeUnit($property, $owner, ['name' => 'A1', 'status' => 'occupied']);
    occupyUnit($property, $unit, $owner);

    $this->actingAs($owner)
        ->put(tenantUrl($property->organization, "/units/{$unit->id}"), [
            'name' => 'A1',
            'rent_frequency' => 'monthly',
            'property_id' => $other->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data', fn (array $flash) => str_contains(
            $flash['toast']['message'] ?? '',
            'active lease',
        ));

    expect($unit->fresh()->property_id)->toBe($property->id);
});

test('an edit appends uploaded photos', function () {
    Storage::fake('public');

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = makeUnitProperty($owner);
    $unit = makeUnit($property, $owner);

    $this->actingAs($owner)
        ->put(tenantUrl($property->organization, "/units/{$unit->id}"), [
            'name' => 'A1',
            'rent_frequency' => 'monthly',
            'photos' => [UploadedFile::fake()->image('new.jpg')],
        ])
        ->assertRedirect();

    expect($unit->fresh()->getMedia('photos'))->toHaveCount(1);
});

test('an edit can reuse another unit’s photos without moving them', function () {
    Storage::fake('public');

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = makeUnitProperty($owner);
    $source = makeUnit($property, $owner, ['name' => 'A1']);
    $target = makeUnit($property, $owner, ['name' => 'A2']);

    $source->addMedia(UploadedFile::fake()->image('shared.jpg'))->toMediaCollection('photos');
    $sourcePhotoId = $source->getMedia('photos')->firstOrFail()->getKey();

    $this->actingAs($owner)
        ->put(tenantUrl($property->organization, "/units/{$target->id}"), [
            'name' => 'A2',
            'rent_frequency' => 'monthly',
            'reuse_photo_ids' => [$sourcePhotoId],
        ])
        ->assertRedirect();

    // The target gains its own copy; the source keeps its photo.
    expect($target->fresh()->getMedia('photos'))->toHaveCount(1)
        ->and($source->fresh()->getMedia('photos'))->toHaveCount(1)
        ->and(
            $target->fresh()->getMedia('photos')->first()->getPath()
            !== $source->fresh()->getMedia('photos')->first()->getPath(),
        )->toBeTrue();
});

test('reuse ignores media from another organization', function () {
    Storage::fake('public');

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = makeUnitProperty($owner);
    $target = makeUnit($property, $owner);

    $stranger = User::factory()->create(['onboarded_at' => now()]);
    $strangerProperty = makeUnitProperty($stranger, 'other-towers');
    $strangerUnit = makeUnit($strangerProperty, $stranger);
    $strangerUnit->addMedia(UploadedFile::fake()->image('theirs.jpg'))->toMediaCollection('photos');
    $foreignId = $strangerUnit->getMedia('photos')->firstOrFail()->getKey();

    $this->actingAs($owner)
        ->put(tenantUrl($property->organization, "/units/{$target->id}"), [
            'name' => 'A2',
            'rent_frequency' => 'monthly',
            'reuse_photo_ids' => [$foreignId],
        ])
        ->assertRedirect();

    expect($target->fresh()->getMedia('photos'))->toHaveCount(0);
});

test('the photo cap counts uploads and reused photos together', function () {
    Storage::fake('public');

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = makeUnitProperty($owner);
    $unit = makeUnit($property, $owner);

    foreach (range(1, Unit::MAX_PHOTOS) as $i) {
        $unit->addMedia(UploadedFile::fake()->image("p{$i}.jpg"))->toMediaCollection('photos');
    }

    $this->actingAs($owner)
        ->put(tenantUrl($property->organization, "/units/{$unit->id}"), [
            'name' => 'A1',
            'rent_frequency' => 'monthly',
            'photos' => [UploadedFile::fake()->image('extra.jpg')],
        ])
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data', fn (array $flash) => str_contains(
            $flash['toast']['message'] ?? '',
            'at most',
        ));

    expect($unit->fresh()->getMedia('photos'))->toHaveCount(Unit::MAX_PHOTOS);
});

test('creating a unit can reuse another unit’s photos', function () {
    Storage::fake('public');

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = makeUnitProperty($owner);
    $source = makeUnit($property, $owner, ['name' => 'A1']);

    $source->addMedia(UploadedFile::fake()->image('shared.jpg'))->toMediaCollection('photos');
    $sourcePhotoId = $source->getMedia('photos')->firstOrFail()->getKey();

    $this->actingAs($owner)
        ->post(tenantUrl($property->organization, "/{$property->slug}/units"), [
            'name' => 'A2',
            'rent_frequency' => 'monthly',
            'reuse_photo_ids' => [$sourcePhotoId],
        ])
        ->assertRedirect();

    $created = Unit::where('property_id', $property->id)->where('name', 'A2')->firstOrFail();

    expect($created->getMedia('photos'))->toHaveCount(1);
});

test('the units index ships photos and names for the edit sheet', function () {
    Storage::fake('public');

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = makeUnitProperty($owner);
    $unit = makeUnit($property, $owner, ['name' => 'A1']);
    $unit->addMedia(UploadedFile::fake()->image('front.jpg'))->toMediaCollection('photos');

    $props = $this->actingAs($owner)
        ->get(tenantUrl($property->organization, '/units'))
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['units']['data'][0]['photos'])->toHaveCount(1)
        ->and($props['units']['data'][0]['photos'][0])->toHaveKeys(['id', 'url', 'name'])
        ->and($props['properties'][0]['unit_names'])->toContain('A1');
});

test('unit search matches across words', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = makeUnitProperty($owner);
    makeUnit($property, $owner, ['name' => 'A1', 'type' => '1 Bedroom']);
    makeUnit($property, $owner, ['name' => 'B2', 'type' => '2 Bedroom']);

    // A single word matches the type column.
    $this->actingAs($owner)
        ->get(tenantUrl($property->organization, '/units?search=Bedroom'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('units.data', fn ($rows) => count($rows) === 2));

    // Multiple words narrow (AND): name plus type matches, a mixed pair does not.
    $this->actingAs($owner)
        ->get(tenantUrl($property->organization, '/units?search=A1+Bedroom'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('units.data', fn ($rows) => count($rows) === 1 && $rows[0]['name'] === 'A1'));

    $this->actingAs($owner)
        ->get(tenantUrl($property->organization, '/units?search=A1+2+Bedroom'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('units.data', fn ($rows) => count($rows) === 0));
});

test('an edit cannot file a unit under another organization’s property', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = makeUnitProperty($owner);
    $unit = makeUnit($property, $owner);

    $stranger = User::factory()->create(['onboarded_at' => now()]);
    $strangerProperty = makeUnitProperty($stranger, 'other-towers');

    $this->actingAs($owner)
        ->put(tenantUrl($property->organization, "/units/{$unit->id}"), [
            'name' => 'A1',
            'rent_frequency' => 'monthly',
            'property_id' => $strangerProperty->id,
        ])
        ->assertSessionHasErrors('property_id');

    expect($unit->fresh()->property_id)->toBe($property->id);
});

test('a cover photo can be set and switched between photos', function () {
    Storage::fake('public');

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = makeUnitProperty($owner);
    $unit = makeUnit($property, $owner);

    foreach (['one.jpg', 'two.jpg'] as $file) {
        $unit->addMedia(UploadedFile::fake()->image($file))->toMediaCollection('photos');
    }

    $ids = $unit->fresh()->getMedia('photos')->pluck('id')->all();

    $this->actingAs($owner)
        ->put(tenantUrl($property->organization, "/units/{$unit->id}"), [
            'name' => 'A1',
            'rent_frequency' => 'monthly',
            'cover_photo_id' => $ids[1],
        ])
        ->assertRedirect();

    $flags = $unit->fresh()->getMedia('photos')->mapWithKeys(
        fn ($media) => [$media->getKey() => (bool) $media->getCustomProperty('cover', false)],
    )->all();

    expect($flags)->toBe([$ids[0] => false, $ids[1] => true]);

    $props = $this->actingAs($owner)
        ->get(tenantUrl($property->organization, '/units'))
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['units']['data'][0]['thumbnail'])
        ->toBe($unit->fresh()->getMedia('photos')->firstWhere('id', $ids[1])->getUrl())
        ->and($props['units']['data'][0]['photos'][1]['cover'])->toBeTrue();
});

test('a cover id from another unit is ignored, not applied', function () {
    Storage::fake('public');

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = makeUnitProperty($owner);
    $unit = makeUnit($property, $owner, ['name' => 'A1']);
    $other = makeUnit($property, $owner, ['name' => 'A2']);

    $unit->addMedia(UploadedFile::fake()->image('mine.jpg'))->toMediaCollection('photos');
    $other->addMedia(UploadedFile::fake()->image('theirs.jpg'))->toMediaCollection('photos');
    $foreignId = $other->getMedia('photos')->firstOrFail()->getKey();

    $this->actingAs($owner)
        ->put(tenantUrl($property->organization, "/units/{$unit->id}"), [
            'name' => 'A1',
            'rent_frequency' => 'monthly',
            'cover_photo_id' => $foreignId,
        ])
        ->assertRedirect();

    expect($unit->fresh()->getMedia('photos')->first()->getCustomProperty('cover', false))
        ->toBeFalse();
});

test('reused photos copy in the requested order, not id order', function () {
    Storage::fake('public');

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = makeUnitProperty($owner);
    $source = makeUnit($property, $owner, ['name' => 'A1']);
    $target = makeUnit($property, $owner, ['name' => 'A2']);

    foreach (['one.jpg', 'two.jpg', 'three.jpg'] as $file) {
        $source->addMedia(UploadedFile::fake()->image($file))->toMediaCollection('photos');
    }

    $ids = $source->fresh()->getMedia('photos')->pluck('id')->all();
    $reversed = array_reverse($ids);

    $this->actingAs($owner)
        ->put(tenantUrl($property->organization, "/units/{$target->id}"), [
            'name' => 'A2',
            'rent_frequency' => 'monthly',
            'reuse_photo_ids' => $reversed,
        ])
        ->assertRedirect();

    expect(
        $target->fresh()->getMedia('photos')->map->file_name->all(),
    )->toBe(['three.jpg', 'two.jpg', 'one.jpg']);
});

test('reuse skips library photos whose files are gone', function () {
    Storage::fake('public');

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = makeUnitProperty($owner);
    $source = makeUnit($property, $owner, ['name' => 'A1']);
    $target = makeUnit($property, $owner, ['name' => 'A2']);

    foreach (['kept.jpg', 'ghost.jpg'] as $file) {
        $source->addMedia(UploadedFile::fake()->image($file))->toMediaCollection('photos');
    }

    $media = $source->fresh()->getMedia('photos');
    $ghost = $media->firstWhere('file_name', 'ghost.jpg');
    Storage::disk($ghost->disk)->delete($ghost->getPathRelativeToRoot());
    $keptId = $media->firstWhere('file_name', 'kept.jpg')->getKey();

    $this->actingAs($owner)
        ->put(tenantUrl($property->organization, "/units/{$target->id}"), [
            'name' => 'A2',
            'rent_frequency' => 'monthly',
            'reuse_photo_ids' => [$ghost->getKey(), $keptId],
        ])
        ->assertRedirect();

    // The save succeeds with the surviving photo; the ghost is skipped.
    expect($target->fresh()->getMedia('photos')->map->file_name->all())->toBe(['kept.jpg']);
});

test('units export downloads the filtered set as csv', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = makeUnitProperty($owner);
    makeUnit($property, $owner, ['name' => 'A1']);
    makeUnit($property, $owner, ['name' => 'B2', 'rent_frequency' => 'weekly', 'rent_amount' => 7000]);

    $response = $this->actingAs($owner)
        ->get(tenantUrl($property->organization, '/units/export?search=B2'))
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');

    $lines = array_values(array_filter(explode("\n", trim($response->streamedContent()))));

    expect($lines)->toHaveCount(2)
        ->and($lines[0])->toContain('Billing frequency')
        ->and($lines[1])->toContain('B2')
        ->and($lines[1])->toContain('weekly');
});

test('units export page scope honors pagination', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = makeUnitProperty($owner);
    makeUnit($property, $owner, ['name' => 'A1']);

    $response = $this->actingAs($owner)
        ->get(tenantUrl($property->organization, '/units/export?scope=page&page=2'))
        ->assertOk();

    $lines = array_values(array_filter(explode("\n", trim($response->streamedContent()))));

    // Only the header: page two is empty.
    expect($lines)->toHaveCount(1);
});

test('staff without unit.manage cannot export units', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = makeUnitProperty($owner);
    $staff = staffWith($owner, $property->organization, ['unit.edit']);

    $this->actingAs($staff)
        ->get(tenantUrl($property->organization, '/units/export'))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data', fn (array $flash) => str_contains(
            $flash['toast']['message'] ?? '',
            'do not have permission',
        ));
});

function unitCsv(array $rows): string
{
    $handle = fopen('php://temp', 'r+');

    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }

    rewind($handle);
    $content = (string) stream_get_contents($handle);
    fclose($handle);

    $path = tempnam(sys_get_temp_dir(), 'units').'.csv';
    file_put_contents($path, $content);

    return $path;
}

test('units import creates rows and updates by id', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = makeUnitProperty($owner);
    $existing = makeUnit($property, $owner, ['name' => 'A1']);

    $path = unitCsv([
        ['ID', 'Name', 'Type', 'Billing frequency', 'Rent amount', 'Status', 'Property', 'Photos', 'Created at'],
        [$existing->id, 'A1-Renamed', '2 Bedroom', 'weekly', '7000', 'vacant', $property->name, '0', ''],
        ['', 'B2', 'Studio', 'daily', '2500', 'vacant', $property->name, '0', ''],
    ]);

    $this->actingAs($owner)
        ->post(tenantUrl($property->organization, '/units/import'), [
            'file' => new UploadedFile($path, 'units.csv', 'text/csv', null, true),
        ])
        ->assertRedirect();

    expect($existing->fresh()->name)->toBe('A1-Renamed')
        ->and($existing->fresh()->rent_frequency)->toBe('weekly')
        ->and(Unit::where('property_id', $property->id)->where('name', 'B2')->exists())->toBeTrue();
});

test('units import skips bad rows with a per-row report', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = makeUnitProperty($owner);

    $path = unitCsv([
        ['ID', 'Name', 'Type', 'Billing frequency', 'Rent amount', 'Status', 'Property', 'Photos', 'Created at'],
        ['', '', 'Studio', 'monthly', '2500', 'vacant', $property->name, '0', ''],
        ['', 'B2', 'Studio', 'fortnightly', '2500', 'vacant', $property->name, '0', ''],
        ['', 'B3', 'Studio', 'monthly', '2500', 'occupied', $property->name, '0', ''],
        ['', 'B4', 'Studio', 'monthly', '2500', 'vacant', 'No Such Place', '0', ''],
        ['', 'B5', 'Studio', 'monthly', '2500', 'vacant', $property->name, '7', ''],
    ]);

    $this->actingAs($owner)
        ->post(tenantUrl($property->organization, '/units/import'), [
            'file' => new UploadedFile($path, 'units.csv', 'text/csv', null, true),
        ])
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data', fn (array $flash) => str_contains(
            $flash['toast']['message'] ?? '',
            '1 imported, 4 failed',
        ));

    expect(Unit::where('property_id', $property->id)->where('name', 'B5')->exists())->toBeTrue();
});

test('staff without both unit keys cannot import units', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = makeUnitProperty($owner);
    $staff = staffWith($owner, $property->organization, ['unit.manage', 'unit.create']);

    $path = unitCsv([['ID', 'Name'], ['', 'B9']]);

    $this->actingAs($staff)
        ->post(tenantUrl($property->organization, '/units/import'), [
            'file' => new UploadedFile($path, 'units.csv', 'text/csv', null, true),
        ])
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data', fn (array $flash) => str_contains(
            $flash['toast']['message'] ?? '',
            'do not have permission',
        ));

    expect(Unit::where('property_id', $property->id)->where('name', 'B9')->exists())->toBeFalse();
});

test('units export honors a column subset', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $property = makeUnitProperty($owner);
    makeUnit($property, $owner, ['name' => 'A1']);

    $response = $this->actingAs($owner)
        ->get(tenantUrl($property->organization, '/units/export?columns[]=Name&columns[]=Rent+amount'))
        ->assertOk();

    $lines = array_values(array_filter(explode("\n", trim($response->streamedContent()))));

    expect($lines[0])->toBe('Name,"Rent amount"')
        ->and($lines[1])->toContain('A1');
});
