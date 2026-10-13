<?php

use App\Models\Person;
use App\Models\User;
use App\Services\UserService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

function userCsvUpload(string $contents, string $name = 'users.csv'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'users').'.csv';
    file_put_contents($path, $contents);

    return new UploadedFile($path, $name, 'text/csv', null, true);
}

test('the import route requires the import permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('users.import'), [
            'file' => userCsvUpload("First Name,Email\nAda,ada@example.com\n"),
        ])
        ->assertRedirect();
    assertErrorToast();
});

test('import creates users and their person records', function () {
    $response = $this->actingAs($this->admin)->post(route('users.import'), [
        'file' => userCsvUpload(
            "First Name,Last Name,Email,Phone,Status\nAda,Lovelace,ada@example.com,555-0100,active\nGrace,Hopper,grace@example.com,555-0101,active\n"
        ),
    ]);

    $response->assertRedirect(route('users.index'));

    assertSuccessToast();

    $this->assertSame(2, User::whereIn('email', ['ada@example.com', 'grace@example.com'])->count());
    expect(Person::where('email', 'ada@example.com')->exists())->toBeTrue();
});

test('import assigns pipe separated roles', function () {
    $this->actingAs($this->admin)->post(route('users.import'), [
        'file' => userCsvUpload(
            "First Name,Email,Roles\nAda,ada@example.com,admin|searcher\n"
        ),
    ])->assertRedirect(route('users.index'));

    $imported = User::where('email', 'ada@example.com')->firstOrFail();

    expect($imported->getRoleNames()->sort()->values()->all())
        ->toBe(['admin', 'searcher']);
});

test('import reports per-row failures and keeps the sheet open', function () {
    $this->actingAs($this->admin)->post(route('users.import'), [
        'file' => userCsvUpload(
            "First Name,Email\n,missing@example.com\nAda,not-an-email\nGrace,grace@example.com\n"
        ),
    ])->assertRedirect();

    $this->assertSame(1, User::where('email', 'grace@example.com')->count());

    $errors = collect(session('inertia.flash_data')['importErrors'] ?? [])->all();

    expect($errors)->toHaveCount(2)
        ->and(collect($errors)->pluck('row')->all())->toEqualCanonicalizing([2, 3])
        ->and($errors[0])->toHaveKeys(['row', 'message']);
});

test('import updates an existing user matched by email', function () {
    $existing = User::factory()->create(['email' => 'ada@example.com']);
    Person::factory()->create(['email' => 'ada@example.com', 'first_name' => 'Old']);

    $this->actingAs($this->admin)->post(route('users.import'), [
        'file' => userCsvUpload("First Name,Last Name,Email\nAda,Lovelace,ada@example.com\n"),
    ])->assertRedirect(route('users.index'));

    $this->assertSame(1, User::where('email', 'ada@example.com')->count());
    expect($existing->refresh()->person->first_name)->toBe('Ada');
});

test('a headerless csv fails with a single row error', function () {
    // Called on the service directly: an empty upload is rejected by the
    // `mimes:csv,txt` rule before it ever reaches the importer.
    $result = app(UserService::class)->import(
        userCsvUpload(''),
        $this->admin,
    );

    expect($result['imported'])->toBe(0)
        ->and($result['failed'])->toBe(1)
        ->and($result['errors'])->not->toBeEmpty()
        ->and($result['errors'][0]['message'])->toContain('no header row');
});

test('the export route requires the export permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('users.export'))
        ->assertRedirect();
    assertErrorToast();
});

test('export streams a csv with a header row', function () {
    User::factory()->create(['email' => 'ada@example.com']);

    $response = $this->actingAs($this->admin)->get(route('users.export'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toStartWith('text/csv');

    $csv = $response->streamedContent();
    expect($csv)->toContain('Email,Phone,Status')
        ->and($csv)->toContain('Created at')
        ->and($csv)->toContain('ada@example.com');
});

test('export honours the active filters', function () {
    User::factory()->create(['email' => 'keep@example.com', 'status' => 'active']);
    User::factory()->create(['email' => 'drop@example.com', 'status' => 'suspended']);

    $csv = $this->actingAs($this->admin)
        ->get(route('users.export', ['status' => 'active']))
        ->streamedContent();

    expect($csv)->toContain('keep@example.com')
        ->and($csv)->not->toContain('drop@example.com');
});

test('export can be limited to the current page', function () {
    User::factory()->count(20)->create();

    $all = $this->actingAs($this->admin)
        ->get(route('users.export', ['scope' => 'all']))
        ->streamedContent();

    $page = $this->actingAs($this->admin)
        ->get(route('users.export', ['scope' => 'page', 'page' => 1]))
        ->streamedContent();

    expect(substr_count($all, "\n"))->toBeGreaterThan(substr_count($page, "\n"));
});

test('export writes the status as a string not an enum', function () {
    User::factory()->create(['status' => 'suspended']);

    $csv = $this->actingAs($this->admin)->get(route('users.export'))->streamedContent();

    expect($csv)->toContain('suspended')
        ->and($csv)->not->toContain('UserStatus');
});

test('the legacy create and edit pages redirect to the overlay urls', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)
        ->get(route('users.create'))
        ->assertRedirect(route('users.index', ['create' => 1]));

    $this->actingAs($this->admin)
        ->get(route('users.edit', $user))
        ->assertRedirect(route('users.index', ['mode' => 'edit', 'user' => $user->id]));
});

test('the role filter is exposed to the index for the radio group', function () {
    $this->actingAs($this->admin)
        ->get(route('users.index', ['role' => 'admin']))
        ->assertInertia(fn ($page) => $page
            ->component('users/index')
            ->has('platformRoles')
            ->where('filters.role', 'admin')
            ->where('sort.direction', 'desc'),
        );
});
