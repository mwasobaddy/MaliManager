<?php

use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestNote;
use App\Models\Organization;
use App\Models\Plan;
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

function noteOrganization(User $owner, string $name = 'Note Estates'): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $owner,
        name: $name,
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
}

function noteRequest(Organization $organization, User $owner): MaintenanceRequest
{
    $property = $organization->properties()->create([
        'name' => 'Note Court',
        'slug' => 'note-court-'.str()->random(6),
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    return MaintenanceRequest::create([
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'title' => 'Leaking tap',
        'description' => 'The kitchen tap drips overnight.',
        'status' => 'opened',
        'priority' => 'medium',
        'raised_by' => $owner->id,
        'created_by' => $owner->id,
    ]);
}

function noteUrl(Organization $organization, string $path): string
{
    $domain = $organization->tenant->domains()->firstOrCreate(
        ['domain' => $organization->slug.'.note.test'],
        ['domain' => $organization->slug.'.note.test'],
    );

    return "http://{$domain->domain}{$path}";
}

test('an owner adds an internal note to a maintenance request', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = noteOrganization($owner);
    $request = noteRequest($organization, $owner);

    $this->actingAs($owner)
        ->post(noteUrl($organization, "/maintenance/{$request->id}/notes"), [
            'body' => 'Called the plumber; arriving Thursday.',
            'visibility' => 'internal',
        ])
        ->assertRedirect();

    $note = MaintenanceRequestNote::firstOrFail();

    expect($note->maintenance_request_id)->toBe($request->id)
        ->and($note->author_id)->toBe($owner->id)
        ->and($note->body)->toBe('Called the plumber; arriving Thursday.')
        ->and($note->visibility->value)->toBe('internal');
});

test('a shared note is stored with its visibility', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = noteOrganization($owner);
    $request = noteRequest($organization, $owner);

    $this->actingAs($owner)
        ->post(noteUrl($organization, "/maintenance/{$request->id}/notes"), [
            'body' => 'We will be there at 9am.',
            'visibility' => 'shared',
        ])
        ->assertRedirect();

    expect(MaintenanceRequestNote::firstOrFail()->visibility->value)->toBe('shared');
});

test('an empty note is rejected', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = noteOrganization($owner);
    $request = noteRequest($organization, $owner);

    $this->actingAs($owner)
        ->post(noteUrl($organization, "/maintenance/{$request->id}/notes"), [
            'body' => '',
            'visibility' => 'internal',
        ])
        ->assertSessionHasErrors('body');

    expect(MaintenanceRequestNote::count())->toBe(0);
});

test('an unknown visibility is rejected', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = noteOrganization($owner);
    $request = noteRequest($organization, $owner);

    $this->actingAs($owner)
        ->post(noteUrl($organization, "/maintenance/{$request->id}/notes"), [
            'body' => 'Valid text.',
            'visibility' => 'public',
        ])
        ->assertSessionHasErrors('visibility');
});

test('the notes page lists the request history', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = noteOrganization($owner);
    $request = noteRequest($organization, $owner);

    MaintenanceRequestNote::create([
        'maintenance_request_id' => $request->id,
        'author_id' => $owner->id,
        'body' => 'First note.',
        'visibility' => 'internal',
    ]);

    $this->actingAs($owner)
        ->get(noteUrl($organization, "/maintenance/{$request->id}/notes"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/maintenance/notes')
            ->where('request.id', $request->id)
            ->has('notes', 1)
            ->where('notes.0.body', 'First note.')
            ->has('visibilities', 2)
        );
});

test('an owner from another organization cannot add a note', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $intruder = User::factory()->create(['onboarded_at' => now()]);

    $organization = noteOrganization($owner, 'Note Owner Org');
    noteOrganization($intruder, 'Note Intruder Org');

    $request = noteRequest($organization, $owner);

    /*
     * This app turns a permission denial into a redirect plus an error toast
     * rather than a 403 page (see `tests/Pest.php::assertErrorToast`). The
     * skill's reference test asserts `assertForbidden()`; here the contract is
     * the toast, so that is what is asserted. What matters either way is that
     * no row is written.
     */
    $this->actingAs($intruder)
        ->post(noteUrl($organization, "/maintenance/{$request->id}/notes"), [
            'body' => 'Should not land.',
            'visibility' => 'internal',
        ])
        ->assertRedirect();

    assertErrorToast();

    expect(MaintenanceRequestNote::count())->toBe(0);
});
