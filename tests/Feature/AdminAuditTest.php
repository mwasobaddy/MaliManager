<?php

use App\Enums\PlatformPermissionKey;
use App\Models\Audit;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Services\TenantService;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Role;

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
        ->get(route('platform.audit.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('platform/audit/index')
            ->where('audits.total', 2));
});

test('non-admin users are forbidden from the platform audit log', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);

    $this->actingAs($user)
        ->get(route('platform.audit.index'))
        ->assertRedirect();
});

test('a user with only the view audit permission cannot export', function () {
    $viewer = User::factory()->create(['onboarded_at' => now()]);
    $role = Role::create(['name' => 'auditor', 'guard_name' => 'web']);
    $role->givePermissionTo(PlatformPermissionKey::ViewAudit->value);
    $viewer->assignRole($role);

    $this->actingAs($viewer)
        ->get(route('platform.audit.export'))
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
        ->get(route('platform.audit.export'));

    $response->assertDownload('audit-log.csv');
    expect($response->streamedContent())->toContain('Created Exported');
});

/**
 * `created_at` is guarded on the Audit model, so a backdated entry cannot be
 * created in one call — passing it to create() is silently dropped and the row
 * gets now(), which quietly makes any ordering assertion meaningless.
 */
function auditAt(string $description, string $event, string $timestamp): Audit
{
    $audit = Audit::create(['event' => $event, 'description' => $description]);

    $audit->forceFill(['created_at' => $timestamp, 'updated_at' => $timestamp])->saveQuietly();

    return $audit;
}

test('the audit index exposes the sort state and ignores a crafted sort column', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    auditAt('Alpha', 'created', now()->subDays(3)->toDateTimeString());
    auditAt('Beta', 'updated', now()->subDay()->toDateTimeString());

    $this->actingAs($admin)
        ->get(route('platform.audit.index', ['sort' => 'event', 'direction' => 'asc']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('sort.column', 'event')
            ->where('sort.direction', 'asc')
            ->where('audits.data.0.description', 'Alpha')
            ->where('audits.data.1.description', 'Beta'),
        );

    // Not in the allowlist, so it must not reach the ORDER BY.
    $this->actingAs($admin)
        ->get(route('platform.audit.index', ['sort' => 'description; drop table audits']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('sort.column', null));
});

test('the audit index defaults to newest first', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    auditAt('Older', 'created', now()->subDays(3)->toDateTimeString());
    auditAt('Newer', 'created', now()->subDay()->toDateTimeString());

    $this->actingAs($admin)
        ->get(route('platform.audit.index'))
        ->assertInertia(fn ($page) => $page
            ->where('audits.data.0.description', 'Newer')
            ->where('audits.data.1.description', 'Older'),
        );
});

test('the organization list is not sent unless the filters overlay asks', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    Audit::create(['event' => 'created', 'description' => 'Anything']);

    // Eagerly this is one row per organization and unbounded, while only the
    // filters overlay reads it — sending it on every keystroke and sort would be
    // the single heaviest thing on the page.
    $this->actingAs($admin)
        ->get(route('platform.audit.index'))
        ->assertInertia(fn ($page) => $page
            ->missing('options.organizations')
            // The small distinct lists over the log's own vocabulary stay eager.
            ->has('options.events')
            ->has('options.subjectTypes'),
        );

    $this->actingAs($admin)
        ->get(route('platform.audit.index'), [
            'X-Inertia-Partial-Component' => 'platform/audit/index',
            'X-Inertia-Partial-Data' => 'options',
        ], [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('options.organizations'));
});

test('an off-page audit id in the url resolves the row for the overlay', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    foreach (range(1, 15) as $index) {
        auditAt("Filler {$index}", 'created', now()->toDateTimeString());
    }
    $offPage = auditAt('OFF-PAGE', 'updated', now()->subYear()->toDateTimeString());

    $this->actingAs($admin)
        ->get(route('platform.audit.index', ['audit' => $offPage->id]))
        ->assertInertia(fn ($page) => $page
            ->component('platform/audit/index')
            ->where('focusedAudit.id', $offPage->id)
            ->where('focusedAudit.description', 'OFF-PAGE'),
        );

    // A row that is already on the page is not sent twice.
    $this->actingAs($admin)
        ->get(route('platform.audit.index', ['audit' => 1]))
        ->assertInertia(fn ($page) => $page->where('focusedAudit', null));
});

test('the audit export honours the requested sort', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    auditAt('Alpha', 'created', now()->subDays(2)->toDateTimeString());
    auditAt('Beta', 'created', now()->subDay()->toDateTimeString());

    $response = $this->actingAs($admin)
        ->get(route('platform.audit.export', ['sort' => 'created_at', 'direction' => 'asc']));

    $response->assertDownload('audit-log.csv');

    $csv = $response->streamedContent();

    // Ascending puts the older row first, so its line has to precede the newer.
    expect(strpos($csv, 'Alpha'))->toBeLessThan(strpos($csv, 'Beta'));
});
