<?php

use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Services\TenantService;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

function createAdminAuditOrganization(User $user, string $name = 'Acme Estates'): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $user,
        name: $name,
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
}

function adminTenantUrl(Organization $organization, string $path = ''): string
{
    $domain = $organization->tenant->domains()->value('domain');

    return "http://{$domain}{$path}";
}

test('platform admin can view audit entries across all organizations', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $ownerA = User::factory()->create(['onboarded_at' => now()]);
    $orgA = createAdminAuditOrganization($ownerA, 'Org A');
    $ownerB = User::factory()->create(['onboarded_at' => now()]);
    $orgB = createAdminAuditOrganization($ownerB, 'Org B');

    $this->actingAs($ownerA)
        ->post(adminTenantUrl($orgA, '/properties'), ['name' => 'Property A', 'city' => 'A', 'address' => 'A'])
        ->assertRedirect();
    $this->actingAs($ownerB)
        ->post(adminTenantUrl($orgB, '/properties'), ['name' => 'Property B', 'city' => 'B', 'address' => 'B'])
        ->assertRedirect();

    $this->actingAs($admin)
        ->get(route('admin.audit.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/audit/index')
            ->where('audits.total', 2));
});

test('non-admin users are forbidden from the admin audit log', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);

    $this->actingAs($user)
        ->get(route('admin.audit.index'))
        ->assertRedirect();
});

test('admin audit export downloads a csv with every organization entry', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $ownerA = User::factory()->create(['onboarded_at' => now()]);
    $orgA = createAdminAuditOrganization($ownerA, 'Org A');

    $this->actingAs($ownerA)
        ->post(adminTenantUrl($orgA, '/properties'), ['name' => 'Exported', 'city' => 'D', 'address' => 'D'])
        ->assertRedirect();

    $response = $this->actingAs($admin)
        ->get(route('admin.audit.export'));

    $response->assertDownload('audit-log.csv');
    expect($response->streamedContent())->toContain('Created Exported');
});
