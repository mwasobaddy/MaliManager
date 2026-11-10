<?php

use App\Enums\PlatformRole;
use App\Models\Delegation;
use App\Models\Lease;
use App\Models\Occupant;
use App\Models\Organization;
use App\Models\OrganizationUser;
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
        'monthly_rent' => 25000,
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
            ->has('occupants', 1)
            ->where('occupants.0.email', 'sam@acme.test')
            ->where('occupants.0.units.0.id', $unit->id));
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
                'rent_amount' => 25000,
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
        'monthly_rent' => 25000,
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
        'monthly_rent' => 25000,
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
        'monthly_rent' => 25000,
        'deposit' => 25000,
        'created_by' => $owner->id,
    ]);

    $otherUnit = $otherProperty->units()->create([
        'name' => 'Unit B1',
        'status' => 'vacant',
        'type' => '1 Bedroom',
        'monthly_rent' => 25000,
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
        'monthly_rent' => 25000,
        'deposit' => 25000,
        'created_by' => $owner->id,
    ]);

    $unitB = $property->units()->create([
        'name' => 'Unit A2',
        'status' => 'vacant',
        'type' => '1 Bedroom',
        'monthly_rent' => 25000,
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
        'monthly_rent' => 25000,
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
        'monthly_rent' => 25000,
        'deposit' => 25000,
        'created_by' => $owner->id,
    ]);

    addOccupant($organization, $owner, $sunset, ['email' => 'sam@acme.test'], [$unit->id]);

    $this->actingAs($owner)
        ->get(occupantTenantUrl($organization, '/sunset-heights/occupants'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('occupants', 1));

    $this->actingAs($owner)
        ->get(occupantTenantUrl($organization, '/ocean-view/occupants'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('occupants', 0));
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
                'rent_amount' => 25000,
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
        'rent_amount' => 25000,
        'currency' => 'KES',
        'starts_at' => '2026-01-01',
        'agreement_text' => '<p>{{occupant_name}} rents {{unit_name}} at {{property_name}} for {{rent_amount}} {{currency}} from {{start_date}}. Contact {{landlord_unknown_token}}.</p>',
    ]);

    $this->actingAs($owner)
        ->get(occupantTenantUrl($organization, "/sunset-heights/occupants/{$lease->occupant_id}/agreement"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/occupants/agreement')
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
