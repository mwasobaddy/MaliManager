<?php

use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Services\OrganizationManagementService;
use App\Services\TenantService;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

function csvUpload(string $contents, string $name = 'organizations.csv'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'orgs').'.csv';
    file_put_contents($path, $contents);

    return new UploadedFile($path, $name, 'text/csv', null, true);
}

test('the export route requires the export permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('platform.organizations.export'))
        ->assertRedirect();
    assertErrorToast();
});

test('export streams a csv with a header row', function () {
    Organization::factory()->create(['name' => 'Acme Ltd']);

    $response = $this->actingAs($this->admin)->get(route('platform.organizations.export'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toStartWith('text/csv');

    $csv = $response->streamedContent();
    // fputcsv quotes any field containing a space, so "Created at" is quoted.
    expect($csv)->toContain('Name,Slug,Email,Phone,Status,Plan,Domain,Users')
        ->and($csv)->toContain('Created at')
        ->and($csv)->toContain('Acme Ltd');
});

test('export writes the status as a string not an enum', function () {
    Organization::factory()->create(['name' => 'Suspended Co', 'status' => 'suspended']);

    $csv = $this->actingAs($this->admin)->get(route('platform.organizations.export'))->streamedContent();

    // Regression: `status` is cast to an enum and fputcsv cannot stringify it.
    expect($csv)->toContain('suspended')
        ->and($csv)->not->toContain('OrganizationStatus');
});

test('export respects the active status filter', function () {
    Organization::factory()->create(['name' => 'Active Co', 'status' => 'active']);
    Organization::factory()->create(['name' => 'Suspended Co', 'status' => 'suspended']);

    $csv = $this->actingAs($this->admin)
        ->get(route('platform.organizations.export', ['status' => 'suspended']))
        ->streamedContent();

    expect($csv)->toContain('Suspended Co')
        ->and($csv)->not->toContain('Active Co');
});

test('export can be limited to the current page', function () {
    Organization::factory()->count(20)->create();

    $all = $this->actingAs($this->admin)
        ->get(route('platform.organizations.export', ['scope' => 'all']))
        ->streamedContent();
    $page = $this->actingAs($this->admin)
        ->get(route('platform.organizations.export', ['scope' => 'page', 'page' => 1]))
        ->streamedContent();

    expect(substr_count($all, "\n"))->toBeGreaterThan(substr_count($page, "\n"));
});

test('the import route requires the import permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('platform.organizations.import'), ['file' => csvUpload("Name\nAcme\n")])
        ->assertRedirect();
    assertErrorToast();
});

test('import rejects a non-csv file', function () {
    $this->actingAs($this->admin)
        ->post(route('platform.organizations.import'), [
            'file' => UploadedFile::fake()->create('orgs.pdf', 10, 'application/pdf'),
        ])
        ->assertSessionHasErrors('file');
});

test('import creates organizations from a csv', function () {
    $csv = "Name,Owner Email,Email,Phone,Plan\n"
        ."River Holdings,owner@river.test,contact@river.test,0700000001,Free\n"
        ."Hillside Co,owner2@hillside.test,contact@hillside.test,0700000002,Free\n";

    $response = $this->actingAs($this->admin)
        ->post(route('platform.organizations.import'), ['file' => csvUpload($csv)]);

    $response->assertRedirect(route('platform.organizations.index'));

    expect(Organization::count())->toBe(2);

    $river = Organization::where('name', 'River Holdings')->firstOrFail();

    // Provisioned exactly like a hand-created organization.
    expect($river->tenant)->not->toBeNull()
        ->and($river->tenant->domains()->value('domain'))->toBe('river-holdings.malimanager.test')
        ->and($river->subRoles()->count())->toBe(3)
        ->and($river->users()->wherePivot('is_owner', true)->count())->toBe(1);
});

test('import find-or-creates the owner and marks them onboarded', function () {
    $csv = "Name,Owner Email\nRiver Holdings,newowner@river.test\n";

    $this->actingAs($this->admin)
        ->post(route('platform.organizations.import'), ['file' => csvUpload($csv)])
        ->assertRedirect(route('platform.organizations.index'));

    $owner = User::where('email', 'newowner@river.test')->firstOrFail();

    expect($owner->onboarded_at)->not->toBeNull()
        ->and($owner->hasRole('organization-owner'))->toBeTrue();
});

test('import reuses an existing owner account', function () {
    $existing = User::factory()->create(['email' => 'known@river.test']);

    $csv = "Name,Owner Email\nRiver Holdings,known@river.test\n";

    $this->actingAs($this->admin)
        ->post(route('platform.organizations.import'), ['file' => csvUpload($csv)]);

    expect(User::where('email', 'known@river.test')->count())->toBe(1)
        ->and(Organization::first()->users()->whereKey($existing->id)->exists())->toBeTrue();
});

test('import reports bad rows and still imports the good ones', function () {
    $csv = "Name,Owner Email\n"
        ."Good Co,good@river.test\n"
        .",missing-name@river.test\n"
        ."Bad Email Co,not-an-email\n";

    $this->actingAs($this->admin)
        ->post(route('platform.organizations.import'), ['file' => csvUpload($csv)]);

    expect(Organization::count())->toBe(1)
        ->and(Organization::first()->name)->toBe('Good Co');
});

test('a partially failed import flashes the per-row report', function () {
    $csv = "Name,Owner Email\n"
        ."Good Co,good@river.test\n"
        .",missing-name@river.test\n"
        ."Bad Email Co,not-an-email\n";

    $response = $this->actingAs($this->admin)
        ->post(route('platform.organizations.import'), ['file' => csvUpload($csv)]);

    $response->assertSessionHas('inertia.flash_data');

    $errors = collect(session('inertia.flash_data')['importErrors'] ?? [])->all();

    expect($errors)->toHaveCount(2)
        ->and(collect($errors)->pluck('row')->all())->toEqualCanonicalizing([3, 4])
        ->and(collect($errors)->pluck('message')->implode(' '))->toContain('Name is required');
});

test('a csv whose header is unreadable is reported as a failure, not a success', function () {
    $result = app(OrganizationManagementService::class)->import(
        csvUpload(''),
        null,
        $this->admin,
    );

    expect($result['imported'])->toBe(0)
        ->and($result['failed'])->toBe(1)
        ->and($result['errors'])->not->toBeEmpty();
});

test('import rejects a csv with no header row', function () {
    $this->actingAs($this->admin)
        ->post(route('platform.organizations.import'), ['file' => csvUpload('')])
        ->assertSessionHasErrors('file');
});

test('import tolerates blank lines and mixed case headers', function () {
    $csv = "name,OWNER EMAIL\n"
        ."\n"
        ."River Holdings,owner@river.test\n"
        ."\n";

    $this->actingAs($this->admin)
        ->post(route('platform.organizations.import'), ['file' => csvUpload($csv)])
        ->assertRedirect(route('platform.organizations.index'));

    expect(Organization::count())->toBe(1);
});

test('imported organizations appear in the index', function () {
    $csv = "Name,Owner Email\nRiver Holdings,owner@river.test\n";

    $this->actingAs($this->admin)
        ->post(route('platform.organizations.import'), ['file' => csvUpload($csv)]);

    $this->actingAs($this->admin)
        ->get(route('platform.organizations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('platform/organizations/index')
            ->where('organizations.total', 1));
});

test('import does not create an organization when the file is missing', function () {
    $this->actingAs($this->admin)
        ->post(route('platform.organizations.import'))
        ->assertSessionHasErrors('file');

    expect(Organization::count())->toBe(0);
    expect(DB::table('tenants')->count())->toBe(0);
});

test('an off-page organization id in the url resolves the row for the overlay', function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $plan = Plan::where('slug', 'free')->firstOrFail();
    $owners = User::factory()->count(20)->create();

    $created = [];
    $target = null;

    foreach ($owners as $index => $owner) {
        $org = app(TenantService::class)->createOrganization(
            owner: $owner,
            name: 'Deep Link Org '.$index,
            plan: $plan,
        );

        $created[] = $org;

        if ($index === 19) {
            $target = $org;
        }
    }

    // The default order is `created_at DESC, id ASC`. Twenty organizations
    // created in a tight loop can straddle a second boundary, which leaves a
    // tie on `created_at` and lets the target drift onto page 1 — the test then
    // fails not because the fallback is broken but because the row was never
    // off-page to begin with. Pin the timestamps so the ordering is certain:
    // index 0 is the newest, so the target (index 19) is the oldest and lands
    // on page 2.
    foreach ($created as $index => $org) {
        $org->forceFill(['created_at' => now()->subMinutes($index)])->saveQuietly();
    }

    $this->actingAs($admin)
        ->get(route('platform.organizations.index', ['organization' => $target->id, 'mode' => 'view']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('platform/organizations/index')
            ->where('focusedOrganization.id', $target->id)
            ->where('focusedOrganization.name', 'Deep Link Org 19'));
});

test('a row already on the page is not re-sent as the focused row', function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $plan = Plan::where('slug', 'free')->firstOrFail();
    $org = app(TenantService::class)->createOrganization(
        owner: User::factory()->create(),
        name: 'On Page Org',
        plan: $plan,
    );

    $this->actingAs($admin)
        ->get(route('platform.organizations.index', ['organization' => $org->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('platform/organizations/index')
            ->where('focusedOrganization', null));
});

test('the legacy create and edit pages redirect to the overlay urls', function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $org = app(TenantService::class)->createOrganization(
        owner: User::factory()->create(),
        name: 'Redirect Co',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );

    $this->actingAs($admin)
        ->get(route('platform.organizations.create'))
        ->assertRedirect(route('platform.organizations.index', ['create' => 1]));

    $this->actingAs($admin)
        ->get(route('platform.organizations.edit', $org))
        ->assertRedirect(route('platform.organizations.index', [
            'mode' => 'edit',
            'organization' => $org->id,
        ]));
});

test('owner candidates exclude accounts holding a conflicting platform role', function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $eligible = User::factory()->create(['email' => 'eligible@owner.test']);
    $alreadyOwner = User::factory()->create(['email' => 'already@owner.test']);
    $alreadyOwner->assignRole('organization-owner');
    $searcher = User::factory()->create(['email' => 'nobody@searcher.test']);
    $searcher->assignRole('searcher');
    $occupant = User::factory()->create(['email' => 'nobody@occupant.test']);
    $occupant->assignRole('occupant');

    $this->actingAs($admin)
        ->get(route('platform.organizations.index'), [
            'X-Inertia-Partial-Component' => 'platform/organizations/index',
            'X-Inertia-Partial-Data' => 'owners',
        ], [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('platform/organizations/index')
            // Assert inclusion/exclusion rather than the whole list: other
            // tests seed their own users, and the owner pool is every account
            // without a conflicting platform role.
            ->where('owners', function ($owners) use ($eligible, $alreadyOwner, $searcher, $occupant, $admin) {
                $emails = collect($owners)->pluck('email');

                expect($emails)
                    ->toContain($eligible->email)
                    ->toContain($alreadyOwner->email)
                    ->not->toContain($searcher->email)
                    ->not->toContain($occupant->email)
                    ->not->toContain($admin->email);

                return true;
            }));
});
