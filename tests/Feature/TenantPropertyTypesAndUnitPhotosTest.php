<?php

use App\Enums\PropertyCategory;
use App\Enums\SubPermissionKey;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\SubPermission;
use App\Models\SubRole;
use App\Models\Unit;
use App\Models\User;
use App\Services\TenantService;
use App\Support\DefaultPropertyTypes;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();

    $this->owner = User::factory()->create(['onboarded_at' => now()]);
    $this->organization = app(TenantService::class)->createOrganization(
        owner: $this->owner,
        name: 'Savannah Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
});

function unitUrl(Organization $organization, string $path): string
{
    return 'http://'.$organization->tenant->domains()->value('domain').$path;
}

// ------------------------------------------------------------ the catalog --

test('a new organization is seeded with the property-type catalog', function () {
    $types = $this->organization->propertyTypes()->get();

    expect($types)->toHaveCount(52)
        ->and($types->where('category', PropertyCategory::Residential->value)->count())->toBe(26)
        ->and($types->where('category', PropertyCategory::Commercial->value)->count())->toBe(26);

    expect($types->pluck('name'))->toContain('Bedsitter', 'Villa', 'Distribution Warehouse')
        ->not->toContain('Residential', 'Commercial');
});

test('every seeded type gets a slug derived from its name', function () {
    $villa = $this->organization->propertyTypes()->where('name', 'Villa')->firstOrFail();

    expect($villa->slug)->toBe('villa');

    $bnb = $this->organization->propertyTypes()->where('name', 'Bed & Breakfast (BnB)')->firstOrFail();
    expect($bnb->slug)->toBe('bed-breakfast-bnb');
});

test('the catalog is per organization, not shared', function () {
    $other = app(TenantService::class)->createOrganization(
        owner: User::factory()->create(['onboarded_at' => now()]),
        name: 'Other Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );

    $mine = $this->organization->propertyTypes()->where('name', 'Villa')->firstOrFail();
    $theirs = $other->propertyTypes()->where('name', 'Villa')->firstOrFail();

    expect($mine->id)->not->toBe($theirs->id);

    $mine->update(['name' => 'Mansion 2']);

    expect($other->propertyTypes()->where('name', 'Villa')->exists())->toBeTrue();
});

test('seeding the catalog does not flood the audit log', function () {
    // 52 rows per organization would bury the log; only the owner creating the
    // organization should be visible.
    expect($this->organization->propertyTypes()->count())->toBe(52);

    $logged = DB::table('activity_log')->count();

    // Nothing in this test's setup should have logged 52 property types.
    expect($logged)->toBeLessThan(10);
});

test('syncFor is additive and idempotent', function () {
    expect(DefaultPropertyTypes::syncFor($this->organization))->toBe(0);

    // An owner who deletes a type keeps it deleted across re-seeds.
    $this->organization->propertyTypes()->where('slug', 'villa')->delete();

    expect(DefaultPropertyTypes::syncFor($this->organization))->toBe(0)
        ->and($this->organization->propertyTypes()->where('slug', 'villa')->exists())->toBeFalse();
});

// -------------------------------------------------------- managing the catalog --

test('the property types page lists the catalog', function () {
    $this->actingAs($this->owner)
        ->post(unitUrl($this->organization, '/properties'), ['name' => 'First Property'])
        ->assertRedirect();

    $this->actingAs($this->owner)
        ->get(unitUrl($this->organization, '/property-types'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/property-types/index')
            ->has('propertyTypes.data')
            ->where('counts.residential', 26)
            ->where('counts.commercial', 26),
        );
});

test('an owner can create a property type', function () {
    $this->actingAs($this->owner)
        ->post(unitUrl($this->organization, '/property-types'), [
            'name' => 'Coworking Loft',
            'category' => 'commercial',
        ])
        ->assertRedirect();

    $type = $this->organization->propertyTypes()->where('name', 'Coworking Loft')->firstOrFail();

    expect($type->slug)->toBe('coworking-loft')
        ->and($type->category)->toBe(PropertyCategory::Commercial);
});

test('a property type cannot reuse an existing name or slug', function () {
    $this->actingAs($this->owner)
        ->post(unitUrl($this->organization, '/property-types'), [
            'name' => 'Villa',
            'category' => 'residential',
        ])
        ->assertSessionHasErrors('name');

    // "Bar / Pub" already exists with slug `bar-pub`; "Bar-Pub" collides on the
    // derived slug even though the raw names differ.
    $this->actingAs($this->owner)
        ->post(unitUrl($this->organization, '/property-types'), [
            'name' => 'Bar-Pub',
            'category' => 'commercial',
        ])
        ->assertSessionHasErrors('name');
});

test('deleting a type in use unlabels its properties instead of cascading', function () {
    $type = $this->organization->propertyTypes()->where('slug', 'villa')->firstOrFail();
    $property = Property::factory()->create([
        'organization_id' => $this->organization->id,
        'property_type_id' => $type->id,
    ]);

    $this->actingAs($this->owner)
        ->delete(unitUrl($this->organization, '/property-types/'.$type->id))
        ->assertRedirect();

    $property->refresh();

    // Soft delete, so the foreign key still points at the row — what matters is
    // that resolving the relation yields nothing, so the property renders as
    // untyped rather than reaching into a deleted row.
    expect($property->trashed())->toBeFalse()
        ->and($property->propertyType)->toBeNull()
        ->and(PropertyType::withTrashed()->find($property->property_type_id))->not->toBeNull();
});

test('a staff member without the write permission cannot create a type', function () {
    $staff = User::factory()->create(['onboarded_at' => now()]);
    $this->organization->users()->attach($staff->id, [
        'is_owner' => false,
        'sub_role_id' => SubRole::factory()->create([
            'organization_id' => $this->organization->id,
        ])->id,
        'status' => 'active',
    ]);

    $this->actingAs($staff)
        ->post(unitUrl($this->organization, '/property-types'), [
            'name' => 'Sneaky',
            'category' => 'residential',
        ]);

    expect($this->organization->propertyTypes()->where('name', 'Sneaky')->exists())->toBeFalse();
});

test('a role holding only the read gate can load the catalog', function () {
    $staff = User::factory()->create(['onboarded_at' => now()]);
    $role = SubRole::factory()->create(['organization_id' => $this->organization->id]);
    $this->organization->users()->attach($staff->id, [
        'is_owner' => false,
        'sub_role_id' => $role->id,
        'status' => 'active',
    ]);
    $role->subPermissions()->sync(
        SubPermission::whereIn('key', [SubPermissionKey::PropertyTypeManage->value])->pluck('id')
    );

    $this->actingAs($staff)
        ->get(unitUrl($this->organization, '/property-types'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canCreate', false)
            ->where('canEdit', false)
            ->where('canDelete', false),
        );
});

test('the catalog exports with its columns', function () {
    $this->actingAs($this->owner)
        ->post(unitUrl($this->organization, '/properties'), ['name' => 'First Property']);

    $response = $this->actingAs($this->owner)
        ->get(unitUrl($this->organization, '/property-types/export?category=residential'));

    $response->assertOk();
    $csv = $response->streamedContent();

    expect($csv)->toContain('Bedsitter')
        ->not->toContain('Data Center Facility');
});

// ---------------------------------------------------------- typed properties --

test('a property stores its type and location link', function () {
    $type = $this->organization->propertyTypes()->where('slug', 'villa')->firstOrFail();

    $this->actingAs($this->owner)
        ->post(unitUrl($this->organization, '/properties'), [
            'name' => 'Riverside Villas',
            'city' => 'Nairobi',
            'location_url' => 'https://maps.app.goo.gl/abc123',
            'property_type_id' => $type->id,
        ])
        ->assertRedirect();

    $property = Property::where('name', 'Riverside Villas')->firstOrFail();

    expect($property->property_type_id)->toBe($type->id)
        ->and($property->location_url)->toBe('https://maps.app.goo.gl/abc123');
});

test('the location link must be an http url', function () {
    $this->actingAs($this->owner)
        ->post(unitUrl($this->organization, '/properties'), [
            'name' => 'Riverside Villas',
            'location_url' => 'javascript:alert(1)',
        ])
        ->assertSessionHasErrors('location_url');
});

test('a property cannot borrow another organization type', function () {
    $other = app(TenantService::class)->createOrganization(
        owner: User::factory()->create(['onboarded_at' => now()]),
        name: 'Other Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
    $theirType = $other->propertyTypes()->firstOrFail();

    $this->actingAs($this->owner)
        ->post(unitUrl($this->organization, '/properties'), [
            'name' => 'Riverside Villas',
            'property_type_id' => $theirType->id,
        ])
        ->assertSessionHasErrors('property_type_id');
});

// -------------------------------------------------------------- unit photos --

test('a unit created with photos stores them under its own folder', function () {
    Storage::fake(config('filesystems.default'));

    $this->actingAs($this->owner)
        ->post(unitUrl($this->organization, '/properties'), [
            'name' => 'Sunset Heights',
            'units' => [
                [
                    'name' => 'A1',
                    'monthly_rent' => 25000,
                    'photos' => [
                        UploadedFile::fake()->image('front.jpg'),
                        UploadedFile::fake()->image('kitchen.jpg'),
                    ],
                ],
            ],
        ])
        ->assertRedirect();

    $property = Property::where('name', 'Sunset Heights')->firstOrFail();
    $unit = $property->units()->firstOrFail();

    expect($unit->slug)->toBe('a1')
        ->and($unit->getMedia('photos'))->toHaveCount(2);

    // The agreed path: {org}/{property-slug}/{unit-slug}/photos
    foreach ($unit->getMedia('photos') as $media) {
        expect($media->getPathRelativeToRoot())
            ->toStartWith('savannah-estates/sunset-heights/a1/photos');
    }
});

test('units with the same name in different properties get different folders', function () {
    Storage::fake(config('filesystems.default'));

    // The free plan allows a single property, and the second create would be
    // rejected on the limit before it ever reached the slug logic.
    $this->organization->update([
        'plan_id' => Plan::where('slug', 'starter')->firstOrFail()->id,
    ]);

    foreach (['Alpha Court', 'Beta Court'] as $name) {
        $this->actingAs($this->owner)
            ->post(unitUrl($this->organization, '/properties'), [
                'name' => $name,
                'units' => [[
                    'name' => 'A1',
                    'photos' => [UploadedFile::fake()->image('front.jpg')],
                ]],
            ]);
    }

    $units = Unit::with('property')->get();

    expect($units)->toHaveCount(2);

    $paths = $units
        ->flatMap(fn ($unit) => $unit->getMedia('photos')->map->getPathRelativeToRoot())
        ->all();

    expect($paths)->toHaveCount(2);

    foreach ($paths as $path) {
        expect($path)->toEndWith('/a1/photos/front.jpg');
    }

    // The two must not collide, which is why the property slug is in the path.
    expect($paths[0])->not->toBe($paths[1]);
});

test('two unit names that slug identically still get distinct slugs', function () {
    // (property_id, name) is already unique, so a repeated name cannot happen.
    // What CAN happen is two different names collapsing to the same slug —
    // "Bar / Pub" and "Bar-Pub" both slugify to bar-pub — and the
    // (property_id, slug) index would reject the second as a 500.
    $property = Property::factory()->create([
        'organization_id' => $this->organization->id,
        'slug' => 'sunset-heights',
    ]);

    $first = $property->units()->create(['name' => 'Bar / Pub']);
    $second = $property->units()->create(['name' => 'Bar-Pub']);

    expect($first->slug)->toBe('bar-pub')
        ->and($second->slug)->toBe('bar-pub-2');
});

test('a unit rejects a non-image upload', function () {
    $this->actingAs($this->owner)
        ->post(unitUrl($this->organization, '/properties'), [
            'name' => 'Sunset Heights',
            'units' => [[
                'name' => 'A1',
                'photos' => [
                    UploadedFile::fake()->create('payload.php', 4, 'application/x-php'),
                ],
            ]],
        ])
        ->assertSessionHasErrors('units.0.photos.0');

    expect(Property::where('name', 'Sunset Heights')->exists())->toBeFalse();
});

test('a unit rejects more photos than the cap', function () {
    $this->actingAs($this->owner)
        ->post(unitUrl($this->organization, '/properties'), [
            'name' => 'Sunset Heights',
            'units' => [[
                'name' => 'A1',
                'photos' => array_map(
                    fn (int $i) => UploadedFile::fake()->image("photo{$i}.jpg"),
                    range(1, Unit::MAX_PHOTOS + 1),
                ),
            ]],
        ])
        ->assertSessionHasErrors('units.0.photos');
});

test('the units page lists units across properties with their photo counts', function () {
    Storage::fake(config('filesystems.default'));

    $this->actingAs($this->owner)
        ->post(unitUrl($this->organization, '/properties'), [
            'name' => 'Sunset Heights',
            'units' => [[
                'name' => 'A1',
                'photos' => [UploadedFile::fake()->image('front.jpg')],
            ]],
        ]);

    $this->actingAs($this->owner)
        ->get(unitUrl($this->organization, '/units'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/unit/index')
            ->has('units.data', 1)
            ->where('units.data.0.photos_count', 1)
            ->where('units.data.0.property.name', 'Sunset Heights'),
        );
});

test('the unit page shows every photo', function () {
    Storage::fake(config('filesystems.default'));

    $this->actingAs($this->owner)
        ->post(unitUrl($this->organization, '/properties'), [
            'name' => 'Sunset Heights',
            'units' => [[
                'name' => 'A1',
                'photos' => [
                    UploadedFile::fake()->image('front.jpg'),
                    UploadedFile::fake()->image('kitchen.jpg'),
                ],
            ]],
        ]);

    $unit = Unit::firstOrFail();

    $this->actingAs($this->owner)
        ->get(unitUrl($this->organization, '/units/'.$unit->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/unit/show')
            ->has('unit.photos', 2)
            ->where('unit.property.name', 'Sunset Heights'),
        );
});

test('one organization cannot read another organizations unit', function () {
    $other = app(TenantService::class)->createOrganization(
        owner: User::factory()->create(['onboarded_at' => now()]),
        name: 'Other Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
    $theirProperty = Property::factory()->create(['organization_id' => $other->id]);
    $theirUnit = $theirProperty->units()->create(['name' => 'Secret']);

    $this->actingAs($this->owner)
        ->get(unitUrl($this->organization, '/units/'.$theirUnit->id))
        ->assertNotFound();
});
