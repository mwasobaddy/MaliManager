<?php

use App\Enums\AiFeature;
use App\Enums\PlatformPermissionKey;
use App\Enums\PlatformRole;
use App\Enums\SubPermissionKey;
use App\Jobs\TriageMaintenanceRequest;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiSetting;
use App\Models\AiUsageLog;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Property;
use App\Models\SubPermission;
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
use Prism\Prism\ValueObjects\ToolCall;
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

test('toolPayload unwraps the named wrapper key the model emits', function () {
    // Prism invokes a tool with the full arguments object (the model emits
    // {"query": {...}}), so the handler must receive the inner spec or it
    // would throw on the missing top-level 'entity' key.
    $service = app(\App\Support\Ai\AssistantService::class);
    $ref = new ReflectionMethod($service, 'toolPayload');
    $ref->setAccessible(true);

    expect($ref->invoke($service, ['query' => ['entity' => 'properties']], 'query'))
        ->toBe(['entity' => 'properties']);
    expect($ref->invoke($service, ['entity' => 'units'], 'query'))
        ->toBe(['entity' => 'units']);
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

test('tenant ask endpoint is forbidden for members without the ai.use permission', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = aiOrg($owner);
    aiProperty($organization, $owner);
    orgKey($organization);

    $member = User::factory()->create(['onboarded_at' => now()]);
    $role = $organization->subRoles()->create([
        'name' => 'Limited',
        'slug' => 'limited',
        'description' => 'No AI access',
    ]);
    $role->subPermissions()->sync(
        SubPermission::where('key', SubPermissionKey::PropertyManage->value)->pluck('id')
    );
    $organization->users()->attach($member->id, [
        'sub_role_id' => $role->id,
        'is_owner' => false,
        'status' => 'active',
    ]);

    $this->actingAs($member)
        ->postJson(expenseUrlHelper($organization, '/assistant/ask'), [
            'question' => 'Anything?',
        ])->assertForbidden();
});

test('searcher ask endpoint is forbidden without the platform ai.use permission', function () {
    $searcher = User::factory()->create(['onboarded_at' => now()]);
    $searcher->assignRole(PlatformRole::Searcher->value);
    $searcher->revokePermissionTo(PlatformPermissionKey::AiUse->value);

    $this->actingAs($searcher)
        ->postJson('/searcher/assistant/ask', [
            'question' => 'Anything?',
        ])->assertForbidden();
});

test('platform admin can use the assistant inside an organization without an org sub-role', function () {
    Prism::fake([
        TextResponseFake::make()->withText('Across this org, 2 units are vacant.')->withUsage(new Usage(10, 5)),
    ]);

    $admin = User::factory()->create(['onboarded_at' => now()]);
    $admin->assignRole(PlatformRole::Admin->value);

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = aiOrg($owner);
    aiProperty($organization, $owner);
    orgKey($organization);

    $this->actingAs($admin)
        ->postJson(expenseUrlHelper($organization, '/assistant/ask'), [
            'question' => 'How many units are vacant?',
        ])
        ->assertOk()
        ->assertJsonPath('answer', fn ($answer) => str_contains($answer, 'vacant'));
});

test('central admin assistant falls back to an organization credential when no platform credential exists', function () {
    Prism::fake([
        TextResponseFake::make()->withText('3 orgs are active platform-wide.')->withUsage(new Usage(10, 5)),
    ]);

    $admin = User::factory()->create(['onboarded_at' => now()]);
    $admin->assignRole(PlatformRole::Admin->value);

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = aiOrg($owner);
    aiProperty($organization, $owner);

    $setting = new AiSetting([
        'provider' => 'openai',
        'model' => 'gpt-4o',
        'api_key' => 'sk-test-key-1234567890',
        'allow_all_members' => true,
        'features' => [AiFeature::AskData->value],
    ]);
    $setting->owner()->associate($organization);
    $setting->save();

    $this->actingAs($admin)
        ->postJson('/admin/assistant/ask', ['question' => 'How many orgs are active?'])
        ->assertOk()
        ->assertJsonPath('answer', fn ($answer) => str_contains($answer, 'orgs'));
});

test('ask endpoint persists the conversation and resumes it across turns', function () {
    Prism::fake([
        TextResponseFake::make()->withText('Answer one.')->withUsage(new Usage(10, 5)),
        TextResponseFake::make()->withText('Answer two.')->withUsage(new Usage(12, 6)),
    ]);

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = aiOrg($owner);
    aiProperty($organization, $owner);
    orgKey($organization);

    $url = expenseUrlHelper($organization, '/assistant/ask');

    $first = $this->actingAs($owner)
        ->postJson($url, ['question' => 'First question?'])
        ->assertOk();

    $conversationId = $first->json('conversation_id');
    expect($conversationId)->toBeInt();
    expect(AiConversation::count())->toBe(1);
    expect(AiMessage::count())->toBe(2);

    $second = $this->actingAs($owner)
        ->postJson($url, ['question' => 'Second question?', 'conversation_id' => $conversationId])
        ->assertOk();

    expect($second->json('conversation_id'))->toBe($conversationId);
    expect(AiMessage::count())->toBe(4);

    $conversation = AiConversation::find($conversationId);
    expect($conversation->messages()->count())->toBe(4);
    expect($conversation->messages()->oldest()->first()->role)->toBe('user');
    expect($conversation->messages()->latest('id')->first()->role)->toBe('assistant');
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
