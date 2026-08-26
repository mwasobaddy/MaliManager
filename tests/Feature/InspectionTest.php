<?php

use App\Enums\AiFeature;
use App\Models\AiSetting;
use App\Models\AiUsageLog;
use App\Models\Inspection;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Property;
use App\Models\User;
use App\Services\TenantService;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Facade;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\StructuredResponseFake;
use Prism\Prism\ValueObjects\Usage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Facade::clearResolvedInstance('prism');
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

function inspectionOrg(User $owner): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Acme Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
}

function inspectionProperty(Organization $organization, User $owner): Property
{
    return $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);
}

function inspectionUrl(Organization $organization, string $path): string
{
    $domain = $organization->tenant->domains()->firstOrCreate([
        'domain' => $organization->slug.'.inspect.test',
    ]);

    return "http://{$domain->domain}{$path}";
}

test('owners create an inspection with photos stored in the tenant layout', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = inspectionOrg($owner);
    $property = inspectionProperty($organization, $owner);
    $unit = $property->units()->create(['name' => 'A1', 'created_by' => $owner->id]);

    $photos = [
        File::fake()->image('kitchen.png'),
        File::fake()->image('bathroom.jpg'),
    ];

    $this->actingAs($owner)
        ->post(inspectionUrl($organization, '/sunset-heights/inspections'), [
            'title' => 'Move-out walkthrough',
            'unit_id' => $unit->id,
            'inspection_date' => '2026-08-26',
            'notes' => 'Small scuff on living room wall.',
            'photos' => $photos,
        ])->assertRedirect();

    $inspection = Inspection::first();

    expect($inspection)->not->toBeNull()
        ->and($inspection->unit_id)->toBe($unit->id)
        ->and($inspection->getMedia('photos'))->toHaveCount(2)
        ->and($inspection->getMedia('photos')->first()->getPathRelativeToRoot())
        ->toStartWith('acme-estates/sunset-heights/inspections/');
});

test('generate report stores the structured AI analysis and logs usage', function () {
    Queue::fake();

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = inspectionOrg($owner);
    $property = inspectionProperty($organization, $owner);
    $unit = $property->units()->create(['name' => 'A1', 'created_by' => $owner->id]);

    AiSetting::create([
        'owner_type' => $organization->getMorphClass(),
        'owner_id' => $organization->id,
        'provider' => 'openai',
        'model' => 'gpt-4o',
        'api_key' => 'sk-inspect-key-1234567890',
        'features' => [AiFeature::InspectionReports->value],
        'allow_all_members' => true,
    ]);

    Inspection::create([
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'unit_id' => $unit->id,
        'title' => 'Move-out walkthrough',
        'inspection_date' => '2026-08-26',
        'notes' => 'Scuff on wall.',
        'created_by' => $owner->id,
    ]);

    $inspection = Inspection::first();
    $inspection->addMedia(File::fake()->image('living-room.png'))->toMediaCollection('photos');

    Prism::fake([
        StructuredResponseFake::make()
            ->withStructured([
                'overall_condition' => 'good',
                'areas' => [
                    ['area' => 'Living room', 'condition' => 'good', 'issues' => 'Minor scuff on wall'],
                ],
                'recommendations' => ['Repaint scuffed wall before re-listing'],
            ])
            ->withUsage(new Usage(400, 200)),
    ]);

    $this->actingAs($owner)
        ->post(inspectionUrl($organization, "/sunset-heights/inspections/{$inspection->id}/report"))
        ->assertRedirect();

    fwrite(STDERR, 'FLASH: '.json_encode(session('inertia.flash_data')).PHP_EOL);

    $inspection->refresh();

    expect($inspection->ai_report['overall_condition'])->toBe('good')
        ->and($inspection->ai_report['areas'][0]['issues'])->toContain('scuff')
        ->and($inspection->report_generated_at)->not->toBeNull()
        ->and(AiUsageLog::where('feature', 'inspection_reports')->count())->toBe(1);
});

test('staff without inspection.manage cannot create inspections', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = inspectionOrg($owner);
    inspectionProperty($organization, $owner);

    $staff = User::factory()->create(['onboarded_at' => now()]);
    $organization->users()->attach($staff->id, ['is_owner' => false, 'status' => 'active']);

    // Global handler converts 403 into redirect + toast.
    $this->actingAs($staff)
        ->post(inspectionUrl($organization, '/sunset-heights/inspections'), [
            'title' => 'Nope',
            'unit_id' => 1,
            'inspection_date' => '2026-08-26',
        ])->assertRedirect();

    expect(Inspection::count())->toBe(0);
});
