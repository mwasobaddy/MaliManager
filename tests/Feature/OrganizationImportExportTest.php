<?php

use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationManagementService;
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
        ->get(route('organizations.export'))
        ->assertRedirect();
    assertErrorToast();
});

test('export streams a csv with a header row', function () {
    Organization::factory()->create(['name' => 'Acme Ltd']);

    $response = $this->actingAs($this->admin)->get(route('organizations.export'));

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

    $csv = $this->actingAs($this->admin)->get(route('organizations.export'))->streamedContent();

    // Regression: `status` is cast to an enum and fputcsv cannot stringify it.
    expect($csv)->toContain('suspended')
        ->and($csv)->not->toContain('OrganizationStatus');
});

test('export respects the active status filter', function () {
    Organization::factory()->create(['name' => 'Active Co', 'status' => 'active']);
    Organization::factory()->create(['name' => 'Suspended Co', 'status' => 'suspended']);

    $csv = $this->actingAs($this->admin)
        ->get(route('organizations.export', ['status' => 'suspended']))
        ->streamedContent();

    expect($csv)->toContain('Suspended Co')
        ->and($csv)->not->toContain('Active Co');
});

test('export can be limited to the current page', function () {
    Organization::factory()->count(20)->create();

    $all = $this->actingAs($this->admin)
        ->get(route('organizations.export', ['scope' => 'all']))
        ->streamedContent();
    $page = $this->actingAs($this->admin)
        ->get(route('organizations.export', ['scope' => 'page', 'page' => 1]))
        ->streamedContent();

    expect(substr_count($all, "\n"))->toBeGreaterThan(substr_count($page, "\n"));
});

test('the import route requires the import permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('organizations.import'), ['file' => csvUpload("Name\nAcme\n")])
        ->assertRedirect();
    assertErrorToast();
});

test('import rejects a non-csv file', function () {
    $this->actingAs($this->admin)
        ->post(route('organizations.import'), [
            'file' => UploadedFile::fake()->create('orgs.pdf', 10, 'application/pdf'),
        ])
        ->assertSessionHasErrors('file');
});

test('import creates organizations from a csv', function () {
    $csv = "Name,Owner Email,Email,Phone,Plan\n"
        ."River Holdings,owner@river.test,contact@river.test,0700000001,Free\n"
        ."Hillside Co,owner2@hillside.test,contact@hillside.test,0700000002,Free\n";

    $response = $this->actingAs($this->admin)
        ->post(route('organizations.import'), ['file' => csvUpload($csv)]);

    $response->assertRedirect(route('organizations.index'));

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
        ->post(route('organizations.import'), ['file' => csvUpload($csv)])
        ->assertRedirect(route('organizations.index'));

    $owner = User::where('email', 'newowner@river.test')->firstOrFail();

    expect($owner->onboarded_at)->not->toBeNull()
        ->and($owner->hasRole('organization-owner'))->toBeTrue();
});

test('import reuses an existing owner account', function () {
    $existing = User::factory()->create(['email' => 'known@river.test']);

    $csv = "Name,Owner Email\nRiver Holdings,known@river.test\n";

    $this->actingAs($this->admin)
        ->post(route('organizations.import'), ['file' => csvUpload($csv)]);

    expect(User::where('email', 'known@river.test')->count())->toBe(1)
        ->and(Organization::first()->users()->whereKey($existing->id)->exists())->toBeTrue();
});

test('import reports bad rows and still imports the good ones', function () {
    $csv = "Name,Owner Email\n"
        ."Good Co,good@river.test\n"
        .",missing-name@river.test\n"
        ."Bad Email Co,not-an-email\n";

    $this->actingAs($this->admin)
        ->post(route('organizations.import'), ['file' => csvUpload($csv)]);

    expect(Organization::count())->toBe(1)
        ->and(Organization::first()->name)->toBe('Good Co');
});

test('a partially failed import flashes the per-row report', function () {
    $csv = "Name,Owner Email\n"
        ."Good Co,good@river.test\n"
        .",missing-name@river.test\n"
        ."Bad Email Co,not-an-email\n";

    $response = $this->actingAs($this->admin)
        ->post(route('organizations.import'), ['file' => csvUpload($csv)]);

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
        ->post(route('organizations.import'), ['file' => csvUpload('')])
        ->assertSessionHasErrors('file');
});

test('import tolerates blank lines and mixed case headers', function () {
    $csv = "name,OWNER EMAIL\n"
        ."\n"
        ."River Holdings,owner@river.test\n"
        ."\n";

    $this->actingAs($this->admin)
        ->post(route('organizations.import'), ['file' => csvUpload($csv)])
        ->assertRedirect(route('organizations.index'));

    expect(Organization::count())->toBe(1);
});

test('imported organizations appear in the index', function () {
    $csv = "Name,Owner Email\nRiver Holdings,owner@river.test\n";

    $this->actingAs($this->admin)
        ->post(route('organizations.import'), ['file' => csvUpload($csv)]);

    $this->actingAs($this->admin)
        ->get(route('organizations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organizations/index')
            ->where('organizations.total', 1));
});

test('import does not create an organization when the file is missing', function () {
    $this->actingAs($this->admin)
        ->post(route('organizations.import'))
        ->assertSessionHasErrors('file');

    expect(Organization::count())->toBe(0);
    expect(DB::table('tenants')->count())->toBe(0);
});
