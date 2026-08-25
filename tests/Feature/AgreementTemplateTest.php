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
        'created_by' => 1,
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

test('owners can create, list, and delete agreement templates inline', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = templateOrganization($owner);
    $property = propertyOf($organization);
    $property->update(['created_by' => $owner->id]);
    templateTenantUrl($organization, '');

    $this->actingAs($owner)
        ->postJson(templateTenantUrl($organization, '/sunset-heights/agreement-templates'), [
            'name' => 'Standard Residential',
            'body_html' => '<p>{{occupant_name}} agrees.</p><script>x()</script>',
        ])->assertCreated()
        ->assertJsonPath('template.name', 'Standard Residential')
        ->assertJsonPath('template.body_html', fn (string $html) => ! str_contains($html, '<script>'));

    expect(AgreementTemplate::where('organization_id', $organization->id)->count())->toBe(1);

    $template = AgreementTemplate::first();

    $this->actingAs($owner)
        ->getJson(templateTenantUrl($organization, '/sunset-heights/agreement-templates'))
        ->assertOk()
        ->assertJsonCount(1, 'templates');

    $this->actingAs($owner)
        ->deleteJson(templateTenantUrl($organization, "/sunset-heights/agreement-templates/{$template->id}"))
        ->assertOk();

    expect(AgreementTemplate::count())->toBe(0);
});

test('rejects duplicate template names within the same organization', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = templateOrganization($owner);
    $property = propertyOf($organization);
    $property->update(['created_by' => $owner->id]);

    AgreementTemplate::create([
        'organization_id' => $organization->id,
        'name' => 'Standard Residential',
        'body_html' => '<p>Body</p>',
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->postJson(templateTenantUrl($organization, '/sunset-heights/agreement-templates'), [
            'name' => 'Standard Residential',
            'body_html' => '<p>Other</p>',
        ])->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

test('staff can list templates for delegated properties but cannot manage them', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = templateOrganization($owner);
    $property = propertyOf($organization);
    $property->update(['created_by' => $owner->id]);
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

    // Not delegated yet: cannot even read (property access fails first).
    $this->actingAs($staff)
        ->getJson("{$domain}/sunset-heights/agreement-templates")
        ->assertForbidden();

    Delegation::create([
        'organization_id' => $organization->id,
        'organization_user_id' => $membership->id,
        'delegatable_type' => Property::class,
        'delegatable_id' => $property->id,
        'created_by' => $owner->id,
    ]);

    // Delegated staff can read...
    $this->actingAs($staff)
        ->getJson("{$domain}/sunset-heights/agreement-templates")
        ->assertOk()
        ->assertJsonCount(1, 'templates');

    // ...but management stays blocked without lease.manage_templates.
    $this->actingAs($staff)
        ->postJson("{$domain}/sunset-heights/agreement-templates", [
            'name' => 'Staff Template',
            'body_html' => '<p>Nope</p>',
        ])->assertForbidden();

    expect(AgreementTemplate::count())->toBe(1);
});
