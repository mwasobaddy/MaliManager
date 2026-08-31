<?php

use App\Models\EditorImage;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
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

function editorImageOrganization(User $owner): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Acme Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
}

function editorImageTenantUrl(Organization $organization, string $path): string
{
    $domain = $organization->tenant->domains()->firstOrCreate(
        ['domain' => $organization->slug.'.images.test'],
        ['domain' => $organization->slug.'.images.test'],
    );

    return "http://{$domain->domain}{$path}";
}

test('authenticated users can upload an image for the agreement editor', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = editorImageOrganization($owner);

    $response = $this->actingAs($owner)
        ->post(editorImageTenantUrl($organization, '/editor-images'), [
            'image' => File::image('diagram.png')->size(512),
        ]);

    $response->assertOk()->assertJsonStructure(['url']);

    $image = EditorImage::first();
    $media = $image?->getFirstMedia('image');

    expect($image)->not->toBeNull()
        ->and($image->organization_id)->toBe($organization->id)
        ->and($media)->not->toBeNull()
        ->and($media->getPathRelativeToRoot())->toStartWith('acme-estates/agreement-images/')
        ->and($response['url'])->toBe($media?->getUrl());
});

test('upload rejects non-image files', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = editorImageOrganization($owner);

    $this->actingAs($owner)
        ->post(editorImageTenantUrl($organization, '/editor-images'), [
            'image' => File::fake()->createWithContent('notes.txt', 'plain text'),
        ])
        ->assertSessionHasErrors('image');

    expect(EditorImage::count())->toBe(0);
});

test('upload endpoint is gated behind authentication', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = editorImageOrganization($owner);

    $this->post(editorImageTenantUrl($organization, '/editor-images'), [
        'image' => File::image('diagram.png')->size(512),
    ])->assertRedirect(route('login'));

    expect(EditorImage::count())->toBe(0);
});
