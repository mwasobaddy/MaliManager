<?php

use App\Enums\AiFeature;
use App\Jobs\TriageMaintenanceRequest;
use App\Models\AiSetting;
use App\Models\AiUsageLog;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Property;
use App\Models\User;
use App\Services\OccupantService;
use App\Services\TenantService;
use App\Support\Ai\AiGateway;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Queue;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\StructuredResponseFake;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\ValueObjects\Usage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Facade::clearResolvedInstance('prism');
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

function aiOrg(User $owner): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Acme Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
}

function aiProperty(Organization $organization, User $owner): Property
{
    return $organization->properties()->create([
        'name' => 'Sunset Heights',
        'slug' => 'sunset-heights',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);
}

function orgKey(Organization $organization): AiSetting
{
    $setting = new AiSetting([
        'provider' => 'openai',
        'model' => 'gpt-4o',
        'api_key' => 'sk-test-key-1234567890',
        'allow_all_members' => true,
        'features' => [
            AiFeature::AskData->value,
            AiFeature::MaintenanceTriage->value,
            AiFeature::ContentDrafting->value,
        ],
    ]);
    $setting->owner()->associate($organization);
    $setting->save();

    return $setting;
}

function occupantWithUnit(Organization $organization, Property $property, User $owner): array
{
    $unit = $property->units()->create(['name' => 'A1', 'status' => 'occupied', 'created_by' => $owner->id]);

    app(OccupantService::class)->create($organization, $property, $owner, [
        'first_name' => 'Jane',
        'email' => 'jane@acme.test',
        'status' => 'active',
        'unit_ids' => [$unit->id],
    ]);

    return [User::where('email', 'jane@acme.test')->first(), $unit];
}

test('triage job stores the AI-suggested priority and logs usage', function () {
    Queue::fake([TriageMaintenanceRequest::class]);

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = aiOrg($owner);
    $property = aiProperty($organization, $owner);
    [$occupantUser, $unit] = occupantWithUnit($organization, $property, $owner);
    orgKey($organization);

    $response = $this->actingAs($occupantUser)
        ->post('/searcher/maintenance', [
            'lease_id' => Lease::where('unit_id', $unit->id)->value('id'),
            'title' => 'Water leaking from ceiling',
            'description' => 'Water is dripping through the living room ceiling since last night.',
        ]);

    Queue::assertPushed(TriageMaintenanceRequest::class);

    // Run the job with a faked LLM response.
    Prism::fake([
        StructuredResponseFake::make()
            ->withStructured(['priority' => 'urgent', 'reason' => 'Active water damage.'])
            ->withUsage(new Usage(150, 60)),
    ]);

    $record = MaintenanceRequest::where('unit_id', $unit->id)->first();
    (new TriageMaintenanceRequest($record))->handle(app(AiGateway::class));

    expect($record->refresh()->ai_priority)->toBe('urgent')
        ->and(AiUsageLog::count())->toBe(1)
        ->and(AiUsageLog::first()->feature)->toBe('maintenance_triage');
});

test('ask endpoint answers using insight tools when AI is enabled', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('2 units are vacant at Sunset Heights.')
            ->withUsage(new Usage(100, 40)),
    ]);

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = aiOrg($owner);
    aiProperty($organization, $owner);
    orgKey($organization);

    $this->actingAs($owner)
        ->postJson(expenseUrlHelper($organization, '/assistant/ask'), [
            'question' => 'How many units are vacant?',
        ])->assertOk()
        ->assertJsonPath('answer', fn ($answer) => str_contains($answer, 'vacant'));

    expect(AiUsageLog::where('feature', 'ask_data')->count())->toBe(1);
});

function expenseUrlHelper(Organization $organization, string $path): string
{
    $domain = $organization->tenant->domains()->firstOrCreate([
        'domain' => $organization->slug.'.aitest.test',
    ]);

    return "http://{$domain->domain}{$path}";
}

test('ask endpoint is blocked without a configured credential', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = aiOrg($owner);
    aiProperty($organization, $owner);

    $this->actingAs($owner)
        ->postJson(expenseUrlHelper($organization, '/assistant/ask'), [
            'question' => 'Anything?',
        ])->assertStatus(403);
});

test('drafting studio generates a draft and logs usage', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('Dear Jane, kindly note rent is due on the 5th.')
            ->withUsage(new Usage(90, 50)),
    ]);

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = aiOrg($owner);
    aiProperty($organization, $owner);
    orgKey($organization);

    $this->actingAs($owner)
        ->postJson(expenseUrlHelper($organization, '/drafting/generate'), [
            'type' => 'rent_reminder',
            'tone' => 'friendly',
            'fields' => [
                'tenant_name' => 'Jane',
                'property_name' => 'Sunset Heights',
                'amount_due' => '25,000 KES',
                'due_date' => '2026-09-05',
            ],
        ])->assertOk()
        ->assertJsonPath('draft', fn ($draft) => str_contains($draft, 'rent is due'));

    expect(AiUsageLog::where('feature', 'content_drafting')->count())->toBe(1);
});
