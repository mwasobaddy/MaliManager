<?php

use App\Models\AgreementTemplate;
use App\Models\Lease;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Services\OccupantService;
use App\Services\TenantService;
use App\Support\StorageLayout;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
    Storage::fake(StorageLayout::disk());
});

function layoutOrganization(User $owner): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Acme Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
}

test('creating an organization and property builds the folder skeleton on the default disk', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = layoutOrganization($owner);
    $property = $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $disk = Storage::disk(StorageLayout::disk());

    expect($disk->exists('acme-estates/templates/lease/.keep'))->toBeTrue()
        ->and($disk->exists('acme-estates/sunset-heights/lease/.keep'))->toBeTrue()
        ->and($property->slug)->toBe('sunset-heights');
});

test('uploaded lease agreements are stored inside the organization and property folders', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = layoutOrganization($owner);
    $property = $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $unit = $property->units()->create(['name' => 'A1', 'status' => 'vacant', 'created_by' => $owner->id]);

    app(OccupantService::class)->create($organization, $property, $owner, [
        'first_name' => 'Jane',
        'email' => 'jane@acme.test',
        'status' => 'active',
        'unit_ids' => [$unit->id],
    ]);

    $lease = Lease::where('unit_id', $unit->id)->first();
    $lease->addMedia(File::fake()->createWithContent(
        'agreement.pdf',
        '%PDF-1.4 lease content',
    ))->toMediaCollection('agreement');

    $media = $lease->getFirstMedia('agreement');

    expect($media->getPathRelativeToRoot())->toStartWith('acme-estates/sunset-heights/lease/');
});

test('template documents are stored under the organization templates folder', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = layoutOrganization($owner);

    $template = AgreementTemplate::create([
        'organization_id' => $organization->id,
        'name' => 'Standard Residential',
        'body_html' => '<p>Body</p>',
        'created_by' => $owner->id,
    ]);

    $template->addMedia(File::fake()->createWithContent(
        'template.pdf',
        '%PDF-1.4 template content',
    ))->toMediaCollection('document');

    $media = $template->getFirstMedia('document');

    expect($media->getPathRelativeToRoot())->toStartWith('acme-estates/templates/lease/');
});
