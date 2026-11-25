<?php

use App\Enums\PlatformRole;
use App\Models\Delegation;
use App\Models\Lease;
use App\Models\LeaseAdditionalCharge;
use App\Models\Occupant;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Payment;
use App\Models\Person;
use App\Models\Plan;
use App\Models\Property;
use App\Models\SubPermission;
use App\Models\User;
use App\Services\OccupantService;
use App\Services\TenantService;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Testing\File;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

function createOccupantOrganization(User $owner, string $name = 'Acme Estates'): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $owner,
        name: $name,
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
}

function occupantTenantUrl(Organization $organization, string $path = ''): string
{
    $domain = $organization->tenant->domains()->value('domain');

    return "http://{$domain}{$path}";
}

function addMember(User $actor, Organization $organization, string $slug, ?array $properties = null): User
{
    $member = User::factory()->create(['onboarded_at' => now(), 'password' => 'secret-pass']);

    $membership = $organization->users()->attach($member->id, [
        'sub_role_id' => $organization->subRoles()->where('slug', $slug)->first()->id,
        'status' => 'active',
        'created_by' => $actor->id,
    ]);

    if ($properties !== null) {
        $membership = OrganizationUser::where('organization_id', $organization->id)
            ->where('user_id', $member->id)
            ->firstOrFail();

        foreach ($properties as $propertyId) {
            Delegation::create([
                'organization_id' => $organization->id,
                'organization_user_id' => $membership->id,
                'delegatable_type' => Property::class,
                'delegatable_id' => $propertyId,
                'created_by' => $actor->id,
            ]);
        }
    }

    return $member;
}

function addOccupant(Organization $organization, User $actor, Property $property, array $attributes = [], array $unitIds = []): Occupant
{
    return app(OccupantService::class)->create($organization, $property, $actor, array_merge([
        'first_name' => 'Jane',
        'last_name' => 'Wanjiru',
        'email' => 'jane@acme.test',
        'status' => 'active',
        'lease' => ['rent_amount' => 25000, 'rent_frequency' => 'monthly', 'currency' => 'KES'],
    ], $attributes, [
        'unit_ids' => $unitIds,
    ]));
}

function createProperty(Organization $organization, User $owner, string $slug): Property
{
    return $organization->properties()->create([
        'name' => ucfirst(str_replace('-', ' ', $slug)),
        'slug' => $slug,
        'status' => 'active',
        'created_by' => $owner->id,
    ]);
}

test('occupants index requires the occupant.manage sub-permission', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);

    createProperty($organization, $owner, 'sunset-heights');

    $member = User::factory()->create(['onboarded_at' => now()]);

    $role = $organization->subRoles()->create([
        'name' => 'Viewer',
        'slug' => 'viewer',
        'description' => 'Read-only role without occupant access.',
        'created_by' => $owner->id,
    ]);
    $role->subPermissions()->sync(
        SubPermission::where('key', 'property.manage')->pluck('id')
    );

    $organization->users()->attach($member->id, [
        'sub_role_id' => $role->id,
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $this->actingAs($member)
        ->get(occupantTenantUrl($organization, '/sunset-heights/occupants'))
        ->assertRedirect();
    assertErrorToast();
});

test('occupants index is scoped to the property and lists occupants with their units', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);

    $property = $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $unit = $property->units()->create([
        'name' => 'Unit A1',
        'status' => 'occupied',
        'type' => '1 Bedroom',
        'rent_amount' => 25000, 'rent_frequency' => 'monthly',
        'deposit' => 25000,
        'created_by' => $owner->id,
    ]);

    addOccupant($organization, $owner, $property, ['email' => 'sam@acme.test'], [$unit->id]);

    $caretaker = addMember($owner, $organization, 'caretaker', [$property->id]);

    $this->actingAs($caretaker)
        ->get(occupantTenantUrl($organization, '/sunset-heights/occupants'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/occupants/index')
            ->has('occupants.data', 1)
            ->where('occupants.data.0.email', 'sam@acme.test')
            ->where('occupants.data.0.units.0.id', $unit->id));
});

test('owners bypass sub-permission checks on the occupants index', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);

    createProperty($organization, $owner, 'sunset-heights');

    $this->actingAs($owner)
        ->get(occupantTenantUrl($organization, '/sunset-heights/occupants'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('organisation/occupants/index'));
});

test('occupants index searches across names, email and units', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);
    $property = createProperty($organization, $owner, 'sunset-heights');

    $unit = $property->units()->create([
        'name' => 'Unit B2',
        'status' => 'occupied',
        'type' => '2 Bedroom',
        'rent_amount' => 35000, 'rent_frequency' => 'monthly',
        'deposit' => 35000,
        'created_by' => $owner->id,
    ]);

    addOccupant($organization, $owner, $property, [
        'first_name' => 'Zawadi',
        'last_name' => 'Mwangi',
        'email' => 'zawadi@example.test',
    ], [$unit->id]);
    addOccupant($organization, $owner, $property, [
        'first_name' => 'Amina',
        'last_name' => 'Diop',
        'email' => 'amina@example.test',
    ], [$unit->id]);

    $this->actingAs($owner)
        ->get(occupantTenantUrl($organization, '/sunset-heights/occupants?search=zawadi'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('occupants.data', 1)
            ->where('occupants.data.0.email', 'zawadi@example.test'));

    $this->actingAs($owner)
        ->get(occupantTenantUrl($organization, '/sunset-heights/occupants?search=Unit+B2'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('occupants.data', 2));

    // Multiple words narrow (AND): the full name matches, a mixed pair does not.
    $this->actingAs($owner)
        ->get(occupantTenantUrl($organization, '/sunset-heights/occupants?search=Zawadi+Mwangi'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('occupants.data', 1)
            ->where('occupants.data.0.email', 'zawadi@example.test'));

    $this->actingAs($owner)
        ->get(occupantTenantUrl($organization, '/sunset-heights/occupants?search=Zawadi+Diop'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('occupants.data', 0));
});

test('occupants index filters by status', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);
    $property = createProperty($organization, $owner, 'sunset-heights');

    $unit = $property->units()->create([
        'name' => 'Unit C3',
        'status' => 'occupied',
        'type' => '1 Bedroom',
        'rent_amount' => 25000, 'rent_frequency' => 'monthly',
        'deposit' => 25000,
        'created_by' => $owner->id,
    ]);

    $active = addOccupant($organization, $owner, $property, ['email' => 'active@example.test'], [$unit->id]);
    $moved = addOccupant($organization, $owner, $property, ['email' => 'moved@example.test'], [$unit->id]);
    $moved->update(['status' => 'moved_out']);

    $this->actingAs($owner)
        ->get(occupantTenantUrl($organization, '/sunset-heights/occupants?status=active'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('occupants.data', 1)
            ->where('occupants.data.0.email', 'active@example.test'));

    $this->actingAs($owner)
        ->get(occupantTenantUrl($organization, '/sunset-heights/occupants?status=moved_out'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('occupants.data', 1)
            ->where('occupants.data.0.email', 'moved@example.test'));

    expect($active->fresh()->trashed())->toBeFalse();
});

test('occupants index sorts by name and falls back on unknown keys', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);
    $property = createProperty($organization, $owner, 'sunset-heights');

    $unit = $property->units()->create([
        'name' => 'Unit D4',
        'status' => 'occupied',
        'type' => '1 Bedroom',
        'rent_amount' => 25000, 'rent_frequency' => 'monthly',
        'deposit' => 25000,
        'created_by' => $owner->id,
    ]);

    addOccupant($organization, $owner, $property, [
        'first_name' => 'Zawadi',
        'last_name' => 'Mwangi',
        'email' => 'zawadi-sort@example.test',
    ], [$unit->id]);
    addOccupant($organization, $owner, $property, [
        'first_name' => 'Amina',
        'last_name' => 'Diop',
        'email' => 'amina-sort@example.test',
    ], [$unit->id]);

    $this->actingAs($owner)
        ->get(occupantTenantUrl($organization, '/sunset-heights/occupants?sort=name&direction=asc'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('occupants.data.0.email', 'amina-sort@example.test')
            ->where('occupants.data.1.email', 'zawadi-sort@example.test'));

    $this->actingAs($owner)
        ->get(occupantTenantUrl($organization, '/sunset-heights/occupants?sort=bogus'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('occupants.data', 2));
});

test('occupants index paginates with metadata', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);
    $property = createProperty($organization, $owner, 'sunset-heights');

    $unit = $property->units()->create([
        'name' => 'Unit E5',
        'status' => 'occupied',
        'type' => '1 Bedroom',
        'rent_amount' => 25000, 'rent_frequency' => 'monthly',
        'deposit' => 25000,
        'created_by' => $owner->id,
    ]);

    addOccupant($organization, $owner, $property, ['email' => 'page-one@example.test'], [$unit->id]);

    $this->actingAs($owner)
        ->get(occupantTenantUrl($organization, '/sunset-heights/occupants'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('occupants.current_page', 1)
            ->where('occupants.total', 1)
            ->has('occupants.data', 1));
});

test('the record url opens the view sheet on the index instead of a page', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);

    $property = $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $unit = $property->units()->create([
        'name' => 'Unit A1',
        'status' => 'occupied',
        'created_by' => $owner->id,
    ]);

    $occupant = addOccupant($organization, $owner, $property, [], [$unit->id]);

    $this->actingAs($owner)
        ->get(occupantTenantUrl($organization, "/sunset-heights/occupants/{$occupant->id}"))
        ->assertRedirect(occupantTenantUrl(
            $organization,
            "/sunset-heights/occupants?mode=view&occupant={$occupant->id}",
        ));
});

test('the view sheet resolves the occupant with their units and lease', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);

    $property = $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $unit = $property->units()->create([
        'name' => 'Unit A1',
        'status' => 'vacant',
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(occupantTenantUrl($organization, '/sunset-heights/occupants'), [
            'first_name' => 'Jane',
            'email' => 'jane.lease@acme.test',
            'status' => 'active',
            'unit_ids' => [$unit->id],
            'lease' => [
                'rent_amount' => 25000, 'rent_frequency' => 'monthly',
                'currency' => 'KES',
                'agreement_text' => '<p>Rent is due</p>',
            ],
        ])->assertRedirect(occupantTenantUrl($organization, '/sunset-heights/occupants'));

    $occupant = Occupant::where('organization_id', $organization->id)->firstOrFail();

    $this->actingAs($owner)
        ->get(occupantTenantUrl(
            $organization,
            "/sunset-heights/occupants?mode=view&occupant={$occupant->id}",
        ))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/occupants/index')
            ->where('focusedOccupant.email', 'jane.lease@acme.test')
            ->where('focusedOccupant.units.0.id', $unit->id)
            ->where('focusedOccupant.lease.currency', 'KES')
            // The edit sheet loads the agreement into its editor, so the full
            // text travels with the focused record.
            ->where('focusedOccupant.lease.agreement_text', '<p>Rent is due</p>'));
});

test('another property occupant is not resolved into the view sheet', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);

    $sunset = $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $organization->properties()->create([
        'name' => 'Harbour View',
        'slug' => 'harbour-view',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $unit = $sunset->units()->create([
        'name' => 'Unit A1',
        'status' => 'occupied',
        'created_by' => $owner->id,
    ]);

    $occupant = addOccupant($organization, $owner, $sunset, [], [$unit->id]);

    $this->actingAs($owner)
        ->get(occupantTenantUrl(
            $organization,
            "/harbour-view/occupants?mode=view&occupant={$occupant->id}",
        ))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/occupants/index')
            ->where('focusedOccupant', null));
});

test('opening an occupant record requires the occupant.manage sub-permission', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);

    $property = $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $unit = $property->units()->create([
        'name' => 'Unit A1',
        'status' => 'occupied',
        'created_by' => $owner->id,
    ]);

    $occupant = addOccupant($organization, $owner, $property, [], [$unit->id]);

    $member = User::factory()->create(['onboarded_at' => now()]);

    $role = $organization->subRoles()->create([
        'name' => 'Viewer',
        'slug' => 'viewer',
        'description' => 'Read-only role without occupant access.',
        'created_by' => $owner->id,
    ]);
    $role->subPermissions()->sync(
        SubPermission::where('key', 'property.manage')->pluck('id')
    );

    $organization->users()->attach($member->id, [
        'sub_role_id' => $role->id,
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $this->actingAs($member)
        ->get(occupantTenantUrl($organization, "/sunset-heights/occupants/{$occupant->id}"))
        ->assertRedirect();
    assertErrorToast();
});

test('staff without delegation cannot open an occupant record', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);

    $property = $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $unit = $property->units()->create([
        'name' => 'Unit A1',
        'status' => 'occupied',
        'created_by' => $owner->id,
    ]);

    $occupant = addOccupant($organization, $owner, $property, [], [$unit->id]);

    $caretaker = addMember($owner, $organization, 'caretaker');

    $this->actingAs($caretaker)
        ->get(occupantTenantUrl($organization, "/sunset-heights/occupants/{$occupant->id}"))
        ->assertRedirect();
    assertErrorToast();
});

test('staff without delegation cannot view occupants of a property', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);

    $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $caretaker = addMember($owner, $organization, 'caretaker');

    $this->actingAs($caretaker)
        ->get(occupantTenantUrl($organization, '/sunset-heights/occupants'))
        ->assertRedirect();
    assertErrorToast();
});

test('creating an occupant requires the occupant.create sub-permission', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);

    createProperty($organization, $owner, 'sunset-heights');

    $agent = addMember($owner, $organization, 'agent');

    $this->actingAs($agent)
        ->post(occupantTenantUrl($organization, '/sunset-heights/occupants'), [
            'first_name' => 'Jane',
            'email' => 'jane@acme.test',
            'status' => 'active',
            'unit_ids' => [],
        ])->assertRedirect();
    assertErrorToast();
});

test('owner can add an occupant assigned to a unit', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);

    $property = $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $unit = $property->units()->create([
        'name' => 'Unit A1',
        'status' => 'vacant',
        'type' => '1 Bedroom',
        'rent_amount' => 25000, 'rent_frequency' => 'monthly',
        'deposit' => 25000,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(occupantTenantUrl($organization, '/sunset-heights/occupants'), [
            'first_name' => 'Jane',
            'last_name' => 'Wanjiru',
            'email' => 'jane@acme.test',
            'phone' => '+254700000000',
            'national_id' => '12345678',
            'status' => 'active',
            'unit_ids' => [$unit->id],
            'lease' => [
                'rent_amount' => 25000, 'rent_frequency' => 'monthly',
                'currency' => 'KES',
            ],
        ])->assertRedirect(occupantTenantUrl($organization, '/sunset-heights/occupants'));

    $occupant = Occupant::where('organization_id', $organization->id)->first();
    expect($occupant)->not->toBeNull()
        ->and($occupant->status)->toBe('active')
        ->and($occupant->person->first_name)->toBe('Jane')
        ->and($occupant->person->email)->toBe('jane@acme.test')
        ->and($occupant->units->pluck('id'))->toContain($unit->id);

    $user = User::where('email', 'jane@acme.test')->first();
    expect($user)->not->toBeNull()
        ->and($user->hasRole(PlatformRole::Occupant->value))->toBeTrue()
        ->and($user->onboarded_at)->not->toBeNull()
        ->and($user->person_id)->toBe($occupant->person_id);
});

test('occupant gender is optional on create and update but constrained when given', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);
    $property = createProperty($organization, $owner, 'sunset-heights');

    $unit = $property->units()->create([
        'name' => 'Unit A1',
        'status' => 'vacant',
        'type' => '1 Bedroom',
        'rent_amount' => 25000, 'rent_frequency' => 'monthly',
        'deposit' => 25000,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(occupantTenantUrl($organization, '/sunset-heights/occupants'), [
            'first_name' => 'Zawadi',
            'email' => 'zawadi.gender@acme.test',
            'status' => 'active',
            'gender' => 'female',
            'unit_ids' => [$unit->id],
            'lease' => [
                'rent_amount' => 25000, 'rent_frequency' => 'monthly',
                'currency' => 'KES',
            ],
        ])->assertRedirect();

    $occupant = Occupant::where('organization_id', $organization->id)
        ->whereHas('person', fn ($query) => $query->where('email', 'zawadi.gender@acme.test'))
        ->firstOrFail();

    expect($occupant->person->gender)->toBe('female');

    $this->actingAs($owner)
        ->put(occupantTenantUrl($organization, "/sunset-heights/occupants/{$occupant->id}"), [
            'first_name' => 'Zawadi',
            'email' => 'zawadi.gender@acme.test',
            'status' => 'active',
            'gender' => 'male',
            'unit_ids' => [$unit->id],
        ])->assertRedirect();

    expect($occupant->fresh()->person->gender)->toBe('male');

    $this->actingAs($owner)
        ->put(occupantTenantUrl($organization, "/sunset-heights/occupants/{$occupant->id}"), [
            'first_name' => 'Zawadi',
            'email' => 'zawadi.gender@acme.test',
            'status' => 'active',
            'gender' => 'other',
            'unit_ids' => [$unit->id],
        ])->assertSessionHasErrors('gender');
});

test('adding an occupant who already has an account reuses the user', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);

    $property = $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $unit = $property->units()->create([
        'name' => 'Unit A1',
        'status' => 'vacant',
        'type' => '1 Bedroom',
        'rent_amount' => 25000, 'rent_frequency' => 'monthly',
        'deposit' => 25000,
        'created_by' => $owner->id,
    ]);

    $existing = User::factory()->create(['email' => 'exists@acme.test']);

    $this->actingAs($owner)
        ->post(occupantTenantUrl($organization, '/sunset-heights/occupants'), [
            'first_name' => 'Renamed',
            'last_name' => 'Person',
            'email' => 'exists@acme.test',
            'status' => 'active',
            'unit_ids' => [$unit->id],
            'lease' => [
                'rent_amount' => 25000, 'rent_frequency' => 'monthly',
                'currency' => 'KES',
            ],
        ])->assertRedirect(occupantTenantUrl($organization, '/sunset-heights/occupants'));

    expect(User::where('email', 'exists@acme.test')->count())->toBe(1)
        ->and($existing->refresh()->name)->toBe('Renamed Person')
        ->and($existing->hasRole(PlatformRole::Occupant->value))->toBeTrue()
        ->and(Occupant::where('organization_id', $organization->id)
            ->whereHas('person', fn ($query) => $query->where('email', 'exists@acme.test'))
            ->exists())->toBeTrue();
});

test('unit assignment rejects units from another property', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);

    $property = createProperty($organization, $owner, 'sunset-heights');
    $otherProperty = createProperty($organization, $owner, 'ocean-view');

    $unit = $property->units()->create([
        'name' => 'Unit A1',
        'status' => 'vacant',
        'type' => '1 Bedroom',
        'rent_amount' => 25000, 'rent_frequency' => 'monthly',
        'deposit' => 25000,
        'created_by' => $owner->id,
    ]);

    $otherUnit = $otherProperty->units()->create([
        'name' => 'Unit B1',
        'status' => 'vacant',
        'type' => '1 Bedroom',
        'rent_amount' => 25000, 'rent_frequency' => 'monthly',
        'deposit' => 25000,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(occupantTenantUrl($organization, '/sunset-heights/occupants'), [
            'first_name' => 'Jane',
            'email' => 'jane@acme.test',
            'status' => 'active',
            'unit_ids' => [$unit->id, $otherUnit->id],
        ])->assertSessionHasErrors('unit_ids.1');

    expect(Occupant::where('organization_id', $organization->id)->count())->toBe(0);
});

test('editing an occupant requires the occupant.edit sub-permission', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);

    $property = $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $occupant = addOccupant($organization, $owner, $property);

    $agent = addMember($owner, $organization, 'agent');

    $this->actingAs($agent)
        ->get(occupantTenantUrl($organization, "/sunset-heights/occupants/{$occupant->id}/edit"))
        ->assertRedirect();
    assertErrorToast();
});

test('owner can update occupant details and unit assignments', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);

    $property = $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $unitA = $property->units()->create([
        'name' => 'Unit A1',
        'status' => 'vacant',
        'type' => '1 Bedroom',
        'rent_amount' => 25000, 'rent_frequency' => 'monthly',
        'deposit' => 25000,
        'created_by' => $owner->id,
    ]);

    $unitB = $property->units()->create([
        'name' => 'Unit A2',
        'status' => 'vacant',
        'type' => '1 Bedroom',
        'rent_amount' => 25000, 'rent_frequency' => 'monthly',
        'deposit' => 25000,
        'created_by' => $owner->id,
    ]);

    $occupant = addOccupant($organization, $owner, $property, [], [$unitA->id]);

    $this->actingAs($owner)
        ->put(occupantTenantUrl($organization, "/sunset-heights/occupants/{$occupant->id}"), [
            'first_name' => 'Janet',
            'last_name' => 'Otieno',
            'email' => 'jane@acme.test',
            'status' => 'inactive',
            'unit_ids' => [$unitB->id],
        ])->assertRedirect(occupantTenantUrl($organization, '/sunset-heights/occupants'));

    $occupant->refresh();
    expect($occupant->status)->toBe('inactive')
        ->and($occupant->person->first_name)->toBe('Janet')
        ->and($occupant->person->last_name)->toBe('Otieno')
        ->and($occupant->units->pluck('id'))->toContain($unitB->id)
        ->and($occupant->units->pluck('id'))->not->toContain($unitA->id);
});

test('removing an occupant requires the occupant.delete sub-permission', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);

    $property = $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $occupant = addOccupant($organization, $owner, $property);

    $caretaker = addMember($owner, $organization, 'caretaker', [$property->id]);

    $this->actingAs($caretaker)
        ->delete(occupantTenantUrl($organization, "/sunset-heights/occupants/{$occupant->id}"), [
            'password' => 'secret-pass',
        ])->assertRedirect();
    assertErrorToast();
});

test('owner can remove an occupant with their current password', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);

    $property = $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $unit = $property->units()->create([
        'name' => 'Unit A1',
        'status' => 'vacant',
        'type' => '1 Bedroom',
        'rent_amount' => 25000, 'rent_frequency' => 'monthly',
        'deposit' => 25000,
        'created_by' => $owner->id,
    ]);

    $occupant = addOccupant($organization, $owner, $property, [], [$unit->id]);

    $this->actingAs($owner)
        ->delete(occupantTenantUrl($organization, "/sunset-heights/occupants/{$occupant->id}"), [
            'password' => 'password',
        ])->assertRedirect(occupantTenantUrl($organization, '/sunset-heights/occupants'));

    expect($occupant->refresh()->trashed())->toBeTrue()
        ->and($occupant->units()->count())->toBe(0)
        ->and(Person::where('email', 'jane@acme.test')->exists())->toBeTrue();
});

test('removing an occupant with the wrong password is rejected', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);

    $property = $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $occupant = addOccupant($organization, $owner, $property);

    $this->actingAs($owner)
        ->delete(occupantTenantUrl($organization, "/sunset-heights/occupants/{$occupant->id}"), [
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('password')
        ->assertRedirect();

    expect($occupant->refresh()->trashed())->toBeFalse()
        ->and($occupant->units()->count())->toBe(0);
});

test('occupant only appears in the property they are assigned to', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);

    $sunset = $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $ocean = $organization->properties()->create([
        'name' => 'Ocean View',
        'slug' => 'ocean-view',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $unit = $sunset->units()->create([
        'name' => 'Unit A1',
        'status' => 'occupied',
        'type' => '1 Bedroom',
        'rent_amount' => 25000, 'rent_frequency' => 'monthly',
        'deposit' => 25000,
        'created_by' => $owner->id,
    ]);

    addOccupant($organization, $owner, $sunset, ['email' => 'sam@acme.test'], [$unit->id]);

    $this->actingAs($owner)
        ->get(occupantTenantUrl($organization, '/sunset-heights/occupants'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('occupants.data', 1));

    $this->actingAs($owner)
        ->get(occupantTenantUrl($organization, '/ocean-view/occupants'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('occupants.data', 0));
});

test('creating an occupant stores sanitized agreement html and uploads a document', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);

    $property = $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $unit = $property->units()->create([
        'name' => 'Unit A1',
        'status' => 'vacant',
        'created_by' => $owner->id,
    ]);

    $upload = File::fake()->createWithContent(
        'agreement.pdf',
        '%PDF-1.4 test agreement content',
    );

    $this->actingAs($owner)
        ->post(occupantTenantUrl($organization, '/sunset-heights/occupants'), [
            'first_name' => 'Jane',
            'email' => 'jane.lease@acme.test',
            'status' => 'active',
            'unit_ids' => [$unit->id],
            'lease' => [
                'rent_amount' => 25000, 'rent_frequency' => 'monthly',
                'currency' => 'KES',
                'agreement_text' => '<p>Rent is due <b>monthly</b>.</p><script>alert(1)</script><img src="x" onerror="alert(2)">',
                'agreement_document' => $upload,
            ],
        ])->assertRedirect(occupantTenantUrl($organization, '/sunset-heights/occupants'));

    $lease = Lease::where('unit_id', $unit->id)->first();
    $media = $lease->getFirstMedia('agreement');

    expect($lease)->not->toBeNull()
        ->and($lease->agreement_text)->toBe('<p>Rent is due <b>monthly</b>.</p><img src="x" alt="x" />')
        ->and($media)->not->toBeNull()
        ->and($media->file_name)->toEndWith('.pdf');
});

test('updating with remove flag deletes the uploaded agreement document', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);

    $property = $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $unit = $property->units()->create([
        'name' => 'Unit A1',
        'status' => 'vacant',
        'created_by' => $owner->id,
    ]);

    $occupant = addOccupant($organization, $owner, $property, [], [$unit->id]);
    $lease = Lease::where('unit_id', $unit->id)->first();
    $lease->addMedia(File::fake()->createWithContent(
        'agreement.pdf',
        '%PDF-1.4 test agreement content',
    ))->toMediaCollection('agreement');

    expect($lease->getMedia('agreement'))->toHaveCount(1);

    $this->actingAs($owner)
        ->put(occupantTenantUrl($organization, "/sunset-heights/occupants/{$occupant->id}"), [
            'first_name' => 'Jane',
            'email' => 'jane@acme.test',
            'status' => 'active',
            'unit_ids' => [$unit->id],
            'lease' => [
                'remove_agreement_document' => '1',
            ],
        ])->assertRedirect();

    expect($lease->fresh()->getMedia('agreement'))->toHaveCount(0);
});

test('agreement placeholders are merged from lease data on the print page', function () {
    $owner = User::factory()->create(['onboarded_at' => now(), 'phone' => '0712345678']);
    $organization = createOccupantOrganization($owner);

    $property = $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $unit = $property->units()->create([
        'name' => 'Unit A1',
        'status' => 'occupied',
        'created_by' => $owner->id,
    ]);

    addOccupant($organization, $owner, $property, [], [$unit->id]);

    $lease = Lease::where('unit_id', $unit->id)->first();
    $lease->update([
        'rent_amount' => 25000, 'rent_frequency' => 'monthly',
        'currency' => 'KES',
        'starts_at' => '2026-01-01',
        'agreement_text' => '<p>{{occupant_name}} rents {{unit_name}} at {{property_name}} for {{rent_amount}} {{currency}} from {{start_date}}. Contact {{landlord_unknown_token}}.</p>',
    ]);

    $this->actingAs($owner)
        ->get(occupantTenantUrl($organization, "/sunset-heights/occupants/{$lease->occupant_id}/agreement"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/occupants/agreement')
            ->where('terms.unit', 'Unit A1')
            ->where('terms.property', 'Sunset Heights')
            ->where('terms.landlord', 'Acme Estates')
            ->where('terms.rent_amount', 25000)
            ->where('terms.starts_at', '2026-01-01')
            ->where('agreementHtml', fn (string $html) => str_contains($html, 'Jane Wanjiru')
                && str_contains($html, 'Unit A1')
                && str_contains($html, 'Sunset Heights')
                && str_contains($html, '25,000.00 KES')
                && str_contains($html, '2026-01-01')
                // Unknown tokens are left intact so authors spot typos.
                && str_contains($html, '{{landlord_unknown_token}}')));
});

test('assigning a unit marks it occupied', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);
    $property = createProperty($organization, $owner, 'sunset-heights');
    $unit = $property->units()->create([
        'name' => 'A1',
        'status' => 'vacant',
        'created_by' => $owner->id,
    ]);

    addOccupant($organization, $owner, $property, [], [$unit->id]);

    expect($unit->refresh()->status)->toBe('occupied');
});

test('moving out marks the unit vacant again', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);
    $property = createProperty($organization, $owner, 'sunset-heights');
    $unit = $property->units()->create([
        'name' => 'A1',
        'status' => 'vacant',
        'created_by' => $owner->id,
    ]);

    $occupant = addOccupant($organization, $owner, $property, [], [$unit->id]);

    app(OccupantService::class)->moveOut($occupant, $property, $owner);

    expect($unit->refresh()->status)->toBe('vacant');
});

test('terminating a lease marks the unit vacant again', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);
    $property = createProperty($organization, $owner, 'sunset-heights');
    $unit = $property->units()->create([
        'name' => 'A1',
        'status' => 'vacant',
        'created_by' => $owner->id,
    ]);

    addOccupant($organization, $owner, $property, [], [$unit->id]);

    $lease = Lease::where('unit_id', $unit->id)->where('status', 'active')->firstOrFail();
    app(OccupantService::class)->terminateLease($lease);

    expect($unit->refresh()->status)->toBe('vacant');
});

test('leasing never overwrites a maintenance flag', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);
    $property = createProperty($organization, $owner, 'sunset-heights');
    $unit = $property->units()->create([
        'name' => 'A1',
        'status' => 'maintenance',
        'created_by' => $owner->id,
    ]);

    $occupant = addOccupant($organization, $owner, $property, [], [$unit->id]);

    expect($unit->refresh()->status)->toBe('maintenance');

    app(OccupantService::class)->moveOut($occupant, $property, $owner);

    expect($unit->refresh()->status)->toBe('maintenance');
});

test('occupant create and edit are overlays addressed by url', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);
    $property = createProperty($organization, $owner, 'sunset-heights');
    $unit = $property->units()->create([
        'name' => 'Unit A1',
        'status' => 'vacant',
        'created_by' => $owner->id,
    ]);
    $occupant = addOccupant($organization, $owner, $property, [], [$unit->id]);

    $this->actingAs($owner)
        ->get(occupantTenantUrl($organization, '/sunset-heights/occupants/create'))
        ->assertRedirect(occupantTenantUrl($organization, '/sunset-heights/occupants?create=1'));

    $this->actingAs($owner)
        ->get(occupantTenantUrl($organization, "/sunset-heights/occupants/{$occupant->id}/edit"))
        ->assertRedirect(occupantTenantUrl(
            $organization,
            "/sunset-heights/occupants?mode=edit&occupant={$occupant->id}",
        ));

    $this->actingAs($owner)
        ->get(occupantTenantUrl($organization, '/sunset-heights/occupants?create=1'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/occupants/index')
            ->missing('units'));

    // The sheet's option lists load on demand, exactly like the platform
    // reference tables.
    $this->actingAs($owner)->get(
        occupantTenantUrl($organization, '/sunset-heights/occupants?create=1'),
        [
            'X-Inertia-Partial-Component' => 'organisation/occupants/index',
            'X-Inertia-Partial-Data' => 'units,templates,availableTokens',
        ],
    )->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('units')
            ->has('templates'));

    $this->actingAs($owner)
        ->get(occupantTenantUrl(
            $organization,
            "/sunset-heights/occupants?mode=edit&occupant={$occupant->id}",
        ))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/occupants/index')
            ->where('focusedOccupant.id', $occupant->id));
});

test('a booking creates an inactive occupant with a pending lease and no payments', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);
    $property = createProperty($organization, $owner, 'sunset-heights');

    $unit = $property->units()->create([
        'name' => 'Unit A1',
        'status' => 'vacant',
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(occupantTenantUrl($organization, '/sunset-heights/occupants'), [
            'first_name' => 'Zawadi',
            'email' => 'zawadi.booking@acme.test',
            'status' => 'active',
            'unit_ids' => [$unit->id],
            'move_in_now' => false,
            'lease' => [
                'rent_amount' => 25000, 'rent_frequency' => 'monthly',
                'currency' => 'KES', 'deposit' => 25000,
            ],
        ])->assertRedirect(occupantTenantUrl($organization, '/sunset-heights/occupants'));

    $occupant = Occupant::where('organization_id', $organization->id)->firstOrFail();

    // The requested status is overridden: a booking is always inactive.
    expect($occupant->status)->toBe('inactive');

    $lease = Lease::where('person_id', $occupant->person_id)->firstOrFail();
    expect($lease->status)->toBe('pending');

    // Nothing collected yet, and the unit stays vacant.
    expect(Payment::where('organization_id', $organization->id)->count())->toBe(0)
        ->and($unit->fresh()->status)->toBe('vacant');

    // The view sheet carries the pending leases the move-in step collects.
    $this->actingAs($owner)
        ->get(occupantTenantUrl($organization, "/sunset-heights/occupants?mode=view&occupant={$occupant->id}"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('focusedOccupant.status', 'inactive')
            ->has('focusedOccupant.pending_leases', 1)
            ->where('focusedOccupant.pending_leases.0.unit_name', 'Unit A1')
            ->where('focusedOccupant.pending_leases.0.deposit', 25000));
});

test('move-in activates the booking and records deposit plus first rent', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);
    $property = createProperty($organization, $owner, 'sunset-heights');

    $unit = $property->units()->create([
        'name' => 'Unit A1',
        'status' => 'vacant',
        'created_by' => $owner->id,
    ]);

    $occupant = app(OccupantService::class)->create($organization, $property, $owner, [
        'first_name' => 'Zawadi',
        'email' => 'zawadi.movein@acme.test',
        'status' => 'inactive',
        'unit_ids' => [$unit->id],
        'move_in_now' => false,
        'lease' => [
            'rent_amount' => 25000, 'rent_frequency' => 'monthly',
            'currency' => 'KES', 'deposit' => 25000,
        ],
    ]);

    $this->actingAs($owner)
        ->post(occupantTenantUrl($organization, "/sunset-heights/occupants/{$occupant->id}/move-in"), [
            'move_in_date' => '2026-02-01',
            'payment_method' => 'mpesa',
            'payment_reference' => 'MP123',
        ])->assertRedirect(occupantTenantUrl($organization, '/sunset-heights/occupants'));

    expect($occupant->refresh()->status)->toBe('active');

    $lease = Lease::where('person_id', $occupant->person_id)->firstOrFail();
    expect($lease->status)->toBe('active')
        ->and($lease->starts_at->toDateString())->toBe('2026-02-01')
        ->and($unit->fresh()->status)->toBe('occupied');

    $payments = Payment::where('lease_id', $lease->id)->orderBy('id')->get();
    expect($payments)->toHaveCount(2)
        ->and($payments[0]->type)->toBe('deposit')
        ->and($payments[0]->amount)->toBe('25000.00')
        ->and($payments[1]->type)->toBe('rent')
        ->and($payments[1]->amount)->toBe('25000.00')
        ->and($payments[1]->method)->toBe('mpesa')
        ->and($payments[1]->reference)->toBe('MP123')
        ->and($payments[1]->paid_on->toDateString())->toBe('2026-02-01');
});

test('move-in with nothing booked is rejected', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);
    $property = createProperty($organization, $owner, 'sunset-heights');

    $unit = $property->units()->create([
        'name' => 'Unit A1',
        'status' => 'vacant',
        'created_by' => $owner->id,
    ]);

    // Already moved in through the default path: active lease, nothing pending.
    $occupant = addOccupant($organization, $owner, $property, [], [$unit->id]);
    expect($occupant->refresh()->status)->toBe('active');

    $this->actingAs($owner)
        ->from(occupantTenantUrl($organization, '/sunset-heights/occupants'))
        ->post(occupantTenantUrl($organization, "/sunset-heights/occupants/{$occupant->id}/move-in"), [])
        ->assertRedirect(occupantTenantUrl($organization, '/sunset-heights/occupants'))
        ->assertSessionHas('inertia.flash_data', fn (array $flash) => str_contains(
            $flash['toast']['message'] ?? '',
            'nothing booked',
        ));

    expect($occupant->refresh()->status)->toBe('active');
});

test('manually moving out with live leases is rejected', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);
    $property = createProperty($organization, $owner, 'sunset-heights');

    $unit = $property->units()->create([
        'name' => 'Unit A1',
        'status' => 'vacant',
        'created_by' => $owner->id,
    ]);

    $occupant = addOccupant($organization, $owner, $property, [], [$unit->id]);

    $this->actingAs($owner)
        ->from(occupantTenantUrl($organization, '/sunset-heights/occupants'))
        ->put(occupantTenantUrl($organization, "/sunset-heights/occupants/{$occupant->id}"), [
            'first_name' => 'Jane',
            'email' => 'jane@acme.test',
            'status' => 'moved_out',
            'unit_ids' => [$unit->id],
        ])
        ->assertRedirect(occupantTenantUrl($organization, '/sunset-heights/occupants'))
        ->assertSessionHas('inertia.flash_data', fn (array $flash) => str_contains(
            $flash['toast']['message'] ?? '',
            'still holds active leases',
        ));

    expect($occupant->refresh()->status)->toBe('active');
    expect(Lease::where('person_id', $occupant->person_id)->where('status', 'active')->exists())->toBeTrue();
});

test('manual activation of a booking is rejected — move-in only', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);
    $property = createProperty($organization, $owner, 'sunset-heights');

    $unit = $property->units()->create([
        'name' => 'Unit A1',
        'status' => 'vacant',
        'created_by' => $owner->id,
    ]);

    $occupant = app(OccupantService::class)->create($organization, $property, $owner, [
        'first_name' => 'Jane',
        'email' => 'jane@acme.test',
        'status' => 'inactive',
        'unit_ids' => [$unit->id],
        'move_in_now' => false,
        'lease' => ['rent_amount' => 25000, 'rent_frequency' => 'monthly', 'currency' => 'KES'],
    ]);
    expect($occupant->status)->toBe('inactive');

    $this->actingAs($owner)
        ->from(occupantTenantUrl($organization, '/sunset-heights/occupants'))
        ->put(occupantTenantUrl($organization, "/sunset-heights/occupants/{$occupant->id}"), [
            'first_name' => 'Jane',
            'email' => 'jane@acme.test',
            'status' => 'active',
            'unit_ids' => [$unit->id],
        ])
        ->assertRedirect(occupantTenantUrl($organization, '/sunset-heights/occupants'))
        ->assertSessionHas('inertia.flash_data', fn (array $flash) => str_contains(
            $flash['toast']['message'] ?? '',
            'move the occupant in first',
        ));

    expect($occupant->refresh()->status)->toBe('inactive');
    expect(Lease::where('person_id', $occupant->person_id)->where('status', 'active')->exists())->toBeFalse();
});

test('ending the last lease falls the occupant back to inactive', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);
    $property = createProperty($organization, $owner, 'sunset-heights');

    $unit = $property->units()->create([
        'name' => 'Unit A1',
        'status' => 'vacant',
        'created_by' => $owner->id,
    ]);

    $occupant = addOccupant($organization, $owner, $property, [], [$unit->id]);
    $lease = Lease::where('person_id', $occupant->person_id)->firstOrFail();

    $this->actingAs($owner)
        ->post(occupantTenantUrl($organization, "/sunset-heights/leases/{$lease->id}/end"), [
            'password' => 'password',
        ])->assertRedirect();

    expect($lease->refresh()->status)->toBe('ended')
        ->and($occupant->refresh()->status)->toBe('inactive')
        ->and($unit->fresh()->status)->toBe('vacant');
});

test('creating an occupant can attach move-in extras to the lease', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);
    $property = createProperty($organization, $owner, 'sunset-heights');
    $unit = $property->units()->create([
        'name' => 'Unit A1',
        'status' => 'vacant',
        'type' => '1 Bedroom',
        'rent_amount' => 25000,
        'rent_frequency' => 'monthly',
        'deposit' => 25000,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(occupantTenantUrl($organization, '/sunset-heights/occupants'), [
            'first_name' => 'Jane',
            'last_name' => 'Wanjiru',
            'email' => 'jane@acme.test',
            'status' => 'active',
            'unit_ids' => [$unit->id],
            'lease' => [
                'rent_amount' => 25000,
                'rent_frequency' => 'monthly',
                'currency' => 'KES',
                'extras' => [
                    ['name' => 'Parking', 'kind' => 'fixed', 'amount' => '2000'],
                    ['name' => 'Move-in cleaning', 'kind' => 'one_time', 'amount' => '1500'],
                ],
            ],
        ])->assertRedirect();

    $lease = Lease::where('property_id', $property->id)->where('unit_id', $unit->id)->firstOrFail();
    $extras = $lease->additionalCharges()->orderBy('name')->get();

    expect($extras)->toHaveCount(2)
        ->and($extras[0]->name)->toBe('Move-in cleaning')
        ->and($extras[0]->kind)->toBe('one_time')
        ->and((float) $extras[0]->amount)->toBe(1500.0)
        ->and($extras[1]->name)->toBe('Parking')
        ->and($extras[1]->kind)->toBe('fixed')
        ->and((float) $extras[1]->amount)->toBe(2000.0)
        ->and($extras[1]->currency)->toBe('KES');
});

test('move-in extras reject unknown kinds', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);
    $property = createProperty($organization, $owner, 'sunset-heights');
    $unit = $property->units()->create([
        'name' => 'Unit A1',
        'status' => 'vacant',
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->from(occupantTenantUrl($organization, '/sunset-heights/occupants'))
        ->post(occupantTenantUrl($organization, '/sunset-heights/occupants'), [
            'first_name' => 'Jane',
            'email' => 'jane@acme.test',
            'status' => 'active',
            'unit_ids' => [$unit->id],
            'lease' => [
                'rent_amount' => 25000,
                'rent_frequency' => 'monthly',
                'extras' => [
                    ['name' => 'Bogus', 'kind' => 'bogus', 'amount' => '100'],
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('lease.extras.0.kind');

    expect(LeaseAdditionalCharge::count())->toBe(0);
});

test('move-in extras reject zero amounts', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);
    $property = createProperty($organization, $owner, 'sunset-heights');
    $unit = $property->units()->create([
        'name' => 'Unit A1',
        'status' => 'vacant',
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(occupantTenantUrl($organization, '/sunset-heights/occupants'), [
            'first_name' => 'Jane',
            'email' => 'jane@acme.test',
            'status' => 'active',
            'unit_ids' => [$unit->id],
            'lease' => [
                'rent_amount' => 25000,
                'rent_frequency' => 'monthly',
                'extras' => [
                    ['name' => 'Freebie', 'kind' => 'fixed', 'amount' => '0'],
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data', fn (array $flash) => str_contains(
            $flash['toast']['message'] ?? '',
            'above zero',
        ));

    expect(LeaseAdditionalCharge::count())->toBe(0);
});

test('move-in extras land on every lease of a multi-unit move-in', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createOccupantOrganization($owner);
    $property = createProperty($organization, $owner, 'sunset-heights');

    $units = collect(['A1', 'A2'])->map(fn (string $name) => $property->units()->create([
        'name' => "Unit {$name}",
        'status' => 'vacant',
        'created_by' => $owner->id,
    ]));

    $this->actingAs($owner)
        ->post(occupantTenantUrl($organization, '/sunset-heights/occupants'), [
            'first_name' => 'Jane',
            'email' => 'jane@acme.test',
            'status' => 'active',
            'unit_ids' => $units->pluck('id')->all(),
            'lease' => [
                'rent_amount' => 25000,
                'rent_frequency' => 'monthly',
                'extras' => [
                    ['name' => 'Parking', 'kind' => 'fixed', 'amount' => '2000'],
                ],
            ],
        ])->assertRedirect();

    foreach ($units as $unit) {
        $lease = Lease::where('property_id', $property->id)->where('unit_id', $unit->id)->firstOrFail();

        expect($lease->additionalCharges()->where('name', 'Parking')->count())->toBe(1);
    }
});
