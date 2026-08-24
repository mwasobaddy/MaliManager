<?php

use App\Enums\SubPermissionKey;
use App\Models\Audit;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\SubPermission;
use App\Models\User;
use App\Services\TenantService;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Events\Login;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

function createAuditOrganization(User $user, string $name = 'Acme Estates'): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $user,
        name: $name,
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
}

function auditTenantUrl(Organization $organization, string $path = ''): string
{
    $domain = $organization->tenant->domains()->value('domain');

    return "http://{$domain}{$path}";
}

test('organization owner can view the audit log', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $organization = createAuditOrganization($user);

    $this->actingAs($user)
        ->post(auditTenantUrl($organization, '/properties'), [
            'name' => 'Sunset Heights',
            'city' => 'Nairobi',
            'address' => '12 Riverside Drive',
        ])->assertRedirect();

    $this->actingAs($user)
        ->get(auditTenantUrl($organization, '/audit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant/audit/index')
            ->where('audits.total', 1)
            ->where('audits.data.0.description', 'Created Sunset Heights'));
});

test('audit log is scoped to the active organization', function () {
    $ownerA = User::factory()->create(['onboarded_at' => now()]);
    $orgA = createAuditOrganization($ownerA, 'Org A');
    $ownerB = User::factory()->create(['onboarded_at' => now()]);
    $orgB = createAuditOrganization($ownerB, 'Org B');

    $this->actingAs($ownerA)
        ->post(auditTenantUrl($orgA, '/properties'), ['name' => 'Property A', 'city' => 'A', 'address' => 'A'])
        ->assertRedirect();
    $this->actingAs($ownerB)
        ->post(auditTenantUrl($orgB, '/properties'), ['name' => 'Property B', 'city' => 'B', 'address' => 'B'])
        ->assertRedirect();

    $this->actingAs($ownerA)
        ->get(auditTenantUrl($orgA, '/audit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('audits.total', 1)
            ->where('audits.data.0.description', 'Created Property A'));
});

test('owner cannot view another organization audit entry', function () {
    $ownerA = User::factory()->create(['onboarded_at' => now()]);
    $orgA = createAuditOrganization($ownerA, 'Org A');
    $ownerB = User::factory()->create(['onboarded_at' => now()]);
    $orgB = createAuditOrganization($ownerB, 'Org B');

    $this->actingAs($ownerB)
        ->post(auditTenantUrl($orgB, '/properties'), ['name' => 'Property B', 'city' => 'B', 'address' => 'B'])
        ->assertRedirect();

    $otherActivity = Audit::query()->where('description', 'Created Property B')->first();

    $this->actingAs($ownerA)
        ->get(auditTenantUrl($orgA, "/audit/{$otherActivity->id}"))
        ->assertNotFound();
});

test('member without audit.view permission is denied', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createAuditOrganization($owner);
    $member = User::factory()->create(['onboarded_at' => now()]);
    $organization->users()->attach($member->id);

    $this->actingAs($member)
        ->get(auditTenantUrl($organization, '/audit'))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast');

    assertErrorToast();
});

test('member with audit.view permission can view the audit log', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = createAuditOrganization($owner);
    $member = User::factory()->create(['onboarded_at' => now()]);

    $role = $organization->subRoles()->create(['name' => 'Auditor', 'slug' => 'auditor', 'organization_id' => $organization->id]);
    $role->subPermissions()->attach(SubPermission::where('key', SubPermissionKey::AuditView->value)->first());
    $organization->users()->attach($member->id, ['sub_role_id' => $role->id]);

    $this->actingAs($owner)
        ->post(auditTenantUrl($organization, '/properties'), ['name' => 'Logged', 'city' => 'C', 'address' => 'C'])
        ->assertRedirect();

    $this->actingAs($member)
        ->get(auditTenantUrl($organization, '/audit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('tenant/audit/index')->where('audits.total', 1));
});

test('audit log can be exported as csv', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $organization = createAuditOrganization($user);

    $this->actingAs($user)
        ->post(auditTenantUrl($organization, '/properties'), ['name' => 'Exported', 'city' => 'D', 'address' => 'D'])
        ->assertRedirect();

    $response = $this->actingAs($user)
        ->get(auditTenantUrl($organization, '/audit/export'));

    $response->assertDownload('audit-log.csv');
    expect($response->streamedContent())->toContain('Created Exported');
});

test('signing in is recorded in the audit log', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $organization = createAuditOrganization($user);

    event(new Login($user, $user, false));

    expect(Audit::query()->where('event', 'login')->where('causer_id', $user->id)->exists())->toBeTrue();
});

test('property creation is recorded with the organization tenant id', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $organization = createAuditOrganization($user);

    $this->actingAs($user)
        ->post(auditTenantUrl($organization, '/properties'), ['name' => 'Tenant Scoped', 'city' => 'E', 'address' => 'E'])
        ->assertRedirect();

    $activity = Audit::query()->where('description', 'Created Tenant Scoped')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->tenant_id)->toBe($organization->tenant_id);
});
