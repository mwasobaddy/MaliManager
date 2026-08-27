<?php

use App\Models\Delegation;
use App\Models\Organization;
use App\Models\Property;
use App\Models\SubRole;
use App\Models\User;
use App\Services\PropertyAccessService;
use App\Support\AuthLanding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function onboardedUser(): User
{
    return User::factory()->create(['onboarded_at' => now()]);
}

it('lists every property for an organization owner across all their organizations', function () {
    $user = onboardedUser();

    $orgOne = Organization::factory()->create();
    $orgTwo = Organization::factory()->create();

    $user->organizations()->attach($orgOne->id, ['is_owner' => true, 'status' => 'active']);
    $user->organizations()->attach($orgTwo->id, ['is_owner' => true, 'status' => 'active']);

    $propertyOne = Property::factory()->for($orgOne)->create();
    $propertyTwo = Property::factory()->for($orgTwo)->create();

    $result = app(PropertyAccessService::class)->organizationsWithProperties($user);

    expect($result)->toHaveCount(2);
    expect(collect($result)->flatMap(fn ($org) => $org['properties'])->pluck('id'))
        ->toContain($propertyOne->id, $propertyTwo->id);
});

it('restricts staff to only their delegated properties', function () {
    $user = onboardedUser();
    $org = Organization::factory()->create();
    $subRole = SubRole::factory()->create();

    $user->organizations()->attach($org->id, [
        'is_owner' => false,
        'sub_role_id' => $subRole->id,
        'status' => 'active',
    ]);

    $delegated = Property::factory()->for($org)->create();
    $other = Property::factory()->for($org)->create();

    $membership = $user->membershipFor($org);
    Delegation::create([
        'organization_user_id' => $membership->id,
        'organization_id' => $org->id,
        'delegatable_type' => Property::class,
        'delegatable_id' => $delegated->id,
    ]);

    $result = app(PropertyAccessService::class)->organizationsWithProperties($user);

    $propertyIds = collect($result)->flatMap(fn ($organization) => $organization['properties'])->pluck('id');

    expect($propertyIds)->toContain($delegated->id);
    expect($propertyIds)->not->toContain($other->id);
});

it('redirects to the central dashboard when the user has multiple properties', function () {
    $user = onboardedUser();

    $orgOne = Organization::factory()->create();
    $orgTwo = Organization::factory()->create();

    $user->organizations()->attach($orgOne->id, ['is_owner' => true, 'status' => 'active']);
    $user->organizations()->attach($orgTwo->id, ['is_owner' => true, 'status' => 'active']);

    Property::factory()->for($orgOne)->create();
    Property::factory()->for($orgTwo)->create();

    $landing = AuthLanding::for($user, Request::create('/'));

    expect(Str::contains($landing, 'dashboard'))->toBeTrue();
});

it('lands on the central dashboard with the picker when there is a single property', function () {
    $user = onboardedUser();
    $org = Organization::factory()->create();

    $user->organizations()->attach($org->id, ['is_owner' => true, 'status' => 'active']);

    Property::factory()->for($org)->create();

    $landing = AuthLanding::for($user, Request::create('/'));

    expect(route('dashboard'))->toBe($landing);
});

it('auto-opens the picker on the dashboard for any accessible property', function () {
    $user = onboardedUser();
    $org = Organization::factory()->create();

    $user->organizations()->attach($org->id, ['is_owner' => true, 'status' => 'active']);
    Property::factory()->for($org)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->component('dashboard')
            ->where('autoOpenPropertyPicker', true));
});

it('does not auto-open the picker without accessible properties', function () {
    $user = onboardedUser();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->component('dashboard')
            ->where('autoOpenPropertyPicker', false));
});

it('records the picker acknowledgement so it stays closed', function () {
    $user = onboardedUser();

    $this->actingAs($user)
        ->post(route('property-picker.acknowledge'))
        ->assertNoContent();

    expect(session('property_picker_acknowledged'))->toBeTrue();
});

it('does not auto-open the picker once acknowledged this session', function () {
    $user = onboardedUser();
    $org = Organization::factory()->create();

    $user->organizations()->attach($org->id, ['is_owner' => true, 'status' => 'active']);
    Property::factory()->for($org)->create();

    $this->actingAs($user)
        ->post(route('property-picker.acknowledge'))
        ->assertNoContent();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->component('dashboard')
            ->where('autoOpenPropertyPicker', false));
});

it('returns the dashboard when the user has no accessible properties', function () {
    $user = onboardedUser();

    $landing = AuthLanding::for($user, Request::create('/'));

    expect(Str::contains($landing, 'dashboard'))->toBeTrue();
});

it('resolves property access with a bounded query count regardless of organization count', function () {
    $owner = onboardedUser();
    $staff = onboardedUser();

    $organizations = collect([
        Organization::factory()->create(),
        Organization::factory()->create(),
        Organization::factory()->create(),
        Organization::factory()->create(),
        Organization::factory()->create(),
    ]);

    foreach ($organizations as $index => $organization) {
        $organization->tenant->domains()->create(['domain' => $organization->slug.'.malimanager.test']);
        $organization->properties()->createMany(collect(range(1, 3))->map(fn (int $i) => [
            'name' => "Property {$i}",
            'slug' => "property-{$i}",
            'status' => 'active',
            'created_by' => $owner->id,
        ])->all());

        if ($index === 0) {
            $organization->users()->attach($owner->id, ['is_owner' => true, 'status' => 'active']);

            continue;
        }

        $organization->users()->attach($staff->id, ['is_owner' => false, 'status' => 'active']);
    }

    // The staff member is delegated to one property per staff organization.
    foreach ($organizations->skip(1) as $organization) {
        Delegation::create([
            'organization_user_id' => $staff->membershipFor($organization)->id,
            'organization_id' => $organization->id,
            'delegatable_type' => Property::class,
            'delegatable_id' => $organization->properties()->first()->id,
        ]);
    }

    DB::enableQueryLog();
    app(PropertyAccessService::class)->organizationsWithProperties($owner);
    app(PropertyAccessService::class)->organizationsWithProperties($staff);
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Bounded regardless of N: orgs+domains, owner properties, delegations,
    // delegated properties. Grows O(1), not O(N).
    expect($queryCount)->toBeLessThan(12);
});
