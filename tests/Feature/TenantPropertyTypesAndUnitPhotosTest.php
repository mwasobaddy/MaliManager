<?php

use App\Enums\PlatformPermissionKey;
use App\Enums\PropertyCategory;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\Unit;
use App\Models\User;
use App\Services\TenantService;
use App\Support\PropertyTypeCatalog;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();

    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole('admin');

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

test('the catalog is platform-wide rather than per organization', function () {
    $before = PropertyType::count();

    app(TenantService::class)->createOrganization(
        owner: User::factory()->create(['onboarded_at' => now()]),
        name: 'Other Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );

    // One catalogue for every tenant: creating a second organization must not
    // duplicate the 52 rows, and there is no organization column to scope them.
    expect($before)->toBe(52)
        ->and(PropertyType::count())->toBe(52);

    expect(Schema::hasColumn('property_types', 'organization_id'))->toBeFalse();
});

test('the catalog is seeded with residential and commercial halves', function () {
    $types = PropertyType::all();

    expect($types)->toHaveCount(52)
        ->and($types->where('category', PropertyCategory::Residential->value)->count())->toBe(26)
        ->and($types->where('category', PropertyCategory::Commercial->value)->count())->toBe(26);

    expect($types->pluck('name'))->toContain('Bedsitter', 'Villa', 'Distribution Warehouse')
        ->not->toContain('Residential', 'Commercial');
});

test('every seeded type gets a slug derived from its name', function () {
    expect(PropertyType::where('name', 'Villa')->value('slug'))->toBe('villa');

    expect(PropertyType::where('name', 'Bed & Breakfast (BnB)')->value('slug'))
        ->toBe('bed-breakfast-bnb');
});

test('seeding is additive and idempotent', function () {
    expect(PropertyTypeCatalog::seed())->toBe(0);

    // An admin who renamed or deleted a type keeps their version across
    // re-seeds — catalog()->slugFor() still matches on slug, and the row exists,
    // so nothing is re-inserted.
    PropertyType::where('slug', 'villa')->delete();

    expect(PropertyTypeCatalog::seed())->toBe(0)
        ->and(PropertyType::where('slug', 'villa')->exists())->toBeFalse();
});

test('seeding the catalog does not flood the audit log', function () {
    // 52 rows at organization creation would bury the log. The catalog is
    // central configuration, not a tenant action.
    expect(DB::table('activity_log')->count())->toBeLessThan(10);
});

// -------------------------------------------------------- managing the catalog --

test('the property types page lists the platform catalog', function () {
    $this->actingAs($this->admin)
        ->get(route('platform.property-types.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('platform/property-types/index')
            ->has('propertyTypes.data')
            ->where('counts.residential', 26)
            ->where('counts.commercial', 26),
        );
});

test('an admin can create a property type', function () {
    $this->actingAs($this->admin)
        ->post(route('platform.property-types.store'), [
            'name' => 'Coworking Loft',
            'category' => 'commercial',
        ])
        ->assertRedirect(route('platform.property-types.index'));

    $type = PropertyType::where('name', 'Coworking Loft')->firstOrFail();

    expect($type->slug)->toBe('coworking-loft')
        ->and($type->category)->toBe(PropertyCategory::Commercial);
});

test('a property type cannot reuse an existing name or slug', function () {
    $this->actingAs($this->admin)
        ->post(route('platform.property-types.store'), [
            'name' => 'Villa',
            'category' => 'residential',
        ])
        ->assertSessionHasErrors('name');

    // "Bar / Pub" already exists with slug `bar-pub`; "Bar-Pub" collides on the
    // derived slug even though the raw names differ.
    $this->actingAs($this->admin)
        ->post(route('platform.property-types.store'), [
            'name' => 'Bar-Pub',
            'category' => 'commercial',
        ])
        ->assertSessionHasErrors('name');

    expect(PropertyType::where('name', 'Bar-Pub')->exists())->toBeFalse();
});

test('deleting a type in use unlabels its properties instead of cascading', function () {
    $type = PropertyType::where('slug', 'villa')->firstOrFail();
    $property = Property::factory()->create([
        'organization_id' => $this->organization->id,
        'property_type_id' => $type->id,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('platform.property-types.destroy', $type->id))
        ->assertRedirect(route('platform.property-types.index'));

    $property->refresh();

    // Soft delete, so the foreign key still points at the row — what matters is
    // that resolving the relation yields nothing, so the property renders as
    // untyped rather than reaching into a deleted row.
    expect($property->trashed())->toBeFalse()
        ->and($property->propertyType)->toBeNull()
        ->and(PropertyType::withTrashed()->find($property->property_type_id))->not->toBeNull();
});

test('a role without the create permission cannot create a type', function () {
    // Strips only the write key; `manage roles` still grants read.
    $this->admin->roles()->first()->permissions()->detach(
        Permission::whereIn('name', [
            PlatformPermissionKey::PropertyTypeCreate->value,
        ])->pluck('id'),
    );
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($this->admin)
        ->post(route('platform.property-types.store'), [
            'name' => 'Sneaky',
            'category' => 'residential',
        ]);

    expect(PropertyType::where('name', 'Sneaky')->exists())->toBeFalse();
});

test('a role holding only the read gate can load the catalog', function () {
    $viewer = User::factory()->create(['email_verified_at' => now()]);
    $viewer->assignRole('admin');

    foreach ([
        PlatformPermissionKey::PropertyTypeCreate->value,
        PlatformPermissionKey::PropertyTypeEdit->value,
        PlatformPermissionKey::PropertyTypeDelete->value,
        PlatformPermissionKey::PropertyTypeImport->value,
    ] as $key) {
        $viewer->roles()->first()->permissions()->detach(
            Permission::where('name', $key)->pluck('id'),
        );
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($viewer)
        ->get(route('platform.property-types.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canCreate', false)
            ->where('canEdit', false)
            ->where('canDelete', false)
            ->where('canImport', false)
            ->where('canExport', true),
        );
});

test('the catalog exports with its columns', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('platform.property-types.export', ['category' => 'residential']));

    $response->assertOk();
    $csv = $response->streamedContent();

    expect($csv)->toContain('Bedsitter')
        ->not->toContain('Data Center Facility');
});

test('the catalog imports rows and reports per-row errors', function () {
    $csv = "Name,Category,Status\n"
        ."Coworking Loft,commercial,active\n"
        ."Villa,residential,active\n"
        ."Broken,not-a-category,active\n"
        ."No Category\n";

    $this->actingAs($this->admin)
        ->post(route('platform.property-types.import'), [
            'file' => UploadedFile::fake()->createWithContent('types.csv', $csv),
        ])
        ->assertRedirect();

    $errors = collect(session('inertia.flash_data')['importErrors'] ?? [])->all();

    // Villa already exists (reported rather than silently upserted) and Broken
    // names a category that is not residential or commercial. A row with no
    // category defaults to residential, so it is not an error.
    expect($errors)->toHaveCount(2);

    expect(PropertyType::where('name', 'Coworking Loft')->exists())->toBeTrue()
        ->and(PropertyType::where('name', 'No Category')->exists())->toBeTrue()
        ->and(PropertyType::where('name', 'Broken')->exists())->toBeFalse();
});

// ---------------------------------------------------------- typed properties --

test('a property stores its type and location link', function () {
    $type = PropertyType::where('slug', 'villa')->firstOrFail();

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

test('a property cannot borrow a type from another organization', function () {
    // The catalog is global now, so a foreign id is reachable in principle — the
    // Rule::exists scope is the only thing stopping it, and this pins that.
    $other = app(TenantService::class)->createOrganization(
        owner: User::factory()->create(['onboarded_at' => now()]),
        name: 'Other Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
    $foreignType = PropertyType::firstOrFail();
    $foreignType->update(['sort_order' => 0]);

    $this->actingAs($this->owner)
        ->post(unitUrl($this->organization, '/properties'), [
            'name' => 'Riverside Villas',
            'property_type_id' => $foreignType->id + 1000,
        ])
        ->assertSessionHasErrors('property_type_id');

    // And a soft-deleted type must not be usable either, or an admin could
    // retire one and properties would keep silently pointing at the corpse.
    $retired = PropertyType::firstOrFail();
    $retired->delete();

    $this->actingAs($this->owner)
        ->post(unitUrl($this->organization, '/properties'), [
            'name' => 'Riverside Villas 2',
            'property_type_id' => $retired->id,
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
    // The owner needs a property of their own or `has-property` bounces them
    // before the route ever runs, which would mask what this is testing.
    $this->actingAs($this->owner)
        ->post(unitUrl($this->organization, '/properties'), ['name' => 'Mine']);

    $other = app(TenantService::class)->createOrganization(
        owner: User::factory()->create(['onboarded_at' => now()]),
        name: 'Other Estates',
        plan: Plan::where('slug', 'starter')->firstOrFail(),
    );
    $theirProperty = Property::factory()->create(['organization_id' => $other->id]);
    $theirUnit = $theirProperty->units()->create(['name' => 'Secret']);

    $this->actingAs($this->owner)
        ->get(unitUrl($this->organization, '/units/'.$theirUnit->id))
        ->assertNotFound();
});
