<?php

use App\Models\AgreementTemplate;
use App\Models\Delegation;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Plan;
use App\Models\Property;
use App\Models\SubPermission;
use App\Models\SubRole;
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

function templateOrganization(User $owner): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Acme Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
}

function propertyOf(Organization $organization): Property
{
    return $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
    ]);
}

function templateTenantUrl(Organization $organization, string $path): string
{
    $domain = $organization->tenant->domains()->firstOrCreate(
        ['domain' => $organization->slug.'.template.test'],
        ['domain' => $organization->slug.'.template.test'],
    );

    return "http://{$domain->domain}{$path}";
}

test('owners can create, list, and delete organization-wide templates', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = templateOrganization($owner);
    propertyOf($organization);
    templateTenantUrl($organization, '');

    $response = $this->actingAs($owner)
        ->postJson(templateTenantUrl($organization, '/agreement-templates'), [
            'name' => 'Standard Residential',
            'body_html' => '<p>{{occupant_name}} agrees.</p><script>x()</script>',
        ]);
    fwrite(STDERR, 'STATUS: '.$response->getStatusCode().' LOC: '.$response->headers->get('Location').PHP_EOL);

    $template = AgreementTemplate::first();
    expect($template)->not->toBeNull()
        ->and($template->organization_id)->toBe($organization->id)
        ->and($template->property_id)->toBeNull()
        ->and($template->body_html)->not->toContain('<script>');

    // The grouped picker exposes it under the organization scope.
    $this->actingAs($owner)
        ->getJson(templateTenantUrl($organization, '/sunset-heights/agreement-templates/picker'))
        ->assertOk()
        ->assertJsonCount(1, 'organization')
        ->assertJsonCount(0, 'property');

    // The management page lists it.
    $this->actingAs($owner)
        ->get(templateTenantUrl($organization, '/agreement-templates'))
        ->assertOk();

    $this->actingAs($owner)
        ->deleteJson(templateTenantUrl($organization, "/agreement-templates/{$template->id}"))
        ->assertRedirect();

    expect(AgreementTemplate::count())->toBe(0);
});

test('rejects duplicate template names within the same scope', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = templateOrganization($owner);
    $property = propertyOf($organization);
    $domain = templateTenantUrl($organization, '');

    AgreementTemplate::create([
        'organization_id' => $organization->id,
        'name' => 'Standard Residential',
        'body_html' => '<p>Body</p>',
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->postJson("{$domain}/agreement-templates", [
            'name' => 'Standard Residential',
            'body_html' => '<p>Other</p>',
        ])->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

test('delegated staff can read the picker but cannot manage templates', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = templateOrganization($owner);
    $property = propertyOf($organization);
    $domain = templateTenantUrl($organization, '');

    AgreementTemplate::create([
        'organization_id' => $organization->id,
        'name' => 'Owner Template',
        'body_html' => '<p>Body</p>',
        'created_by' => $owner->id,
    ]);

    $subRole = SubRole::factory()->create(['organization_id' => $organization->id]);
    $occupantEdit = SubPermission::where('key', 'occupant.edit')->first();
    $subRole->subPermissions()->sync([$occupantEdit->id]);

    $staff = User::factory()->create(['onboarded_at' => now()]);
    $organization->users()->attach($staff->id, ['is_owner' => false, 'sub_role_id' => $subRole->id, 'status' => 'active']);
    $membership = OrganizationUser::where('organization_id', $organization->id)
        ->where('user_id', $staff->id)
        ->first();

    // Not delegated yet: cannot even read.
    $this->actingAs($staff)
        ->getJson("{$domain}/sunset-heights/agreement-templates/picker")
        ->assertForbidden();

    Delegation::create([
        'organization_id' => $organization->id,
        'organization_user_id' => $membership->id,
        'delegatable_type' => Property::class,
        'delegatable_id' => $property->id,
        'created_by' => $owner->id,
    ]);

    // Delegated staff can read the grouped picker...
    $this->actingAs($staff)
        ->getJson("{$domain}/sunset-heights/agreement-templates/picker")
        ->assertOk()
        ->assertJsonCount(1, 'organization');

    // ...but management pages/endpoints stay blocked.
    // The global exception handler turns 403s into redirects + toast.
    $this->actingAs($staff)
        ->get(templateTenantUrl($organization, '/agreement-templates'))
        ->assertRedirect();

    $this->actingAs($staff)
        ->postJson("{$domain}/agreement-templates", [
            'name' => 'Staff Template',
            'body_html' => '<p>Nope</p>',
        ])->assertForbidden();

    expect(AgreementTemplate::count())->toBe(1);
});

test('property-scoped templates are separate from organization-wide ones', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = templateOrganization($owner);
    $property = propertyOf($organization);
    $domain = templateTenantUrl($organization, '');

    // Org-wide template via the org endpoints.
    $this->actingAs($owner)
        ->post(templateTenantUrl($organization, '/agreement-templates'), [
            'name' => 'Company Wide',
            'body_html' => '<p>Org body</p>',
        ])->assertRedirect();

    // Same name is allowed in a different scope.
    $this->actingAs($owner)
        ->post("{$domain}/sunset-heights/agreement-templates/manage", [
            'name' => 'Company Wide',
            'body_html' => '<p>Property body</p>',
        ])->assertRedirect();

    expect(AgreementTemplate::count())->toBe(2);

    // Picker groups them separately.
    $this->actingAs($owner)
        ->getJson("{$domain}/sunset-heights/agreement-templates/picker")
        ->assertOk()
        ->assertJsonCount(1, 'property')
        ->assertJsonCount(1, 'organization')
        ->assertJsonPath('property.0.body_html', '<p>Property body</p>')
        ->assertJsonPath('organization.0.body_html', '<p>Org body</p>');

    // The same name within one scope is still rejected.
    $this->actingAs($owner)
        ->post("{$domain}/sunset-heights/agreement-templates/manage", [
            'name' => 'Company Wide',
            'body_html' => '<p>Dupe</p>',
        ])->assertSessionHasErrors('name');

    // The property management page shows only its own templates.
    $this->actingAs($owner)
        ->get("{$domain}/sunset-heights/agreement-templates/manage")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant/agreement-templates/index')
            ->where('scope', 'property')
            ->where('templates', fn ($rows) => count($rows) === 1));
});
