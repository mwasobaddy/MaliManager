<?php

use App\Enums\AiFeature;
use App\Enums\PlatformRole;
use App\Models\AiSetting;
use App\Models\AiUsageLog;
use App\Models\Plan;
use App\Models\User;
use App\Services\TenantService;
use App\Support\Ai\AiGateway;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

function makeOrgKey(App\Models\Organization $organization): AiSetting
{
    $setting = new AiSetting([
        'provider' => 'openai',
        'model' => 'gpt-4o',
        'api_key' => 'sk-org-key-1234567890',
    ]);
    $setting->owner()->associate($organization);
    $setting->save();

    return $setting;
}

function aiOrganization(User $owner): App\Models\Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Acme Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
}

test('organization keys are encrypted at rest and never serialized', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = aiOrganization($owner);

    AiSetting::create([
        'owner_type' => Organization::class,
        'owner_id' => $organization->id,
        'provider' => 'openai',
        'model' => 'gpt-4o',
        'api_key' => 'sk-super-secret-value-123456',
        'allow_all_members' => true,
    ]);

    // Raw DB value is NOT the plaintext.
    $raw = DB::table('ai_settings')->value('api_key');
    expect($raw)->not->toBe('sk-super-secret-value-123456')
        ->and(Crypt::decryptString($raw))->toBe('sk-super-secret-value-123456');

    // Model serialization hides it.
    expect(AiSetting::first()->toArray())->not->toContain('sk-super-secret-value-123456');
});

test('personal key takes precedence over the organization key', function () {
    $gateway = new AiGateway;

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = aiOrganization($owner);

    makeOrgKey($organization);

    AiSetting::create([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->id,
        'provider' => 'anthropic',
        'model' => 'claude-sonnet-4-5',
        'api_key' => 'sk-personal-key-1234567',
    ]);

    $resolved = $gateway->resolve($owner, AiFeature::AskData);

    expect($resolved?->source)->toBe('personal')
        ->and($resolved->provider)->toBe('anthropic');
});

test('org key applies only to allowed members and features', function () {
    $gateway = new AiGateway;

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = aiOrganization($owner);

    $allowedStaff = User::factory()->create(['onboarded_at' => now()]);
    $blockedStaff = User::factory()->create(['onboarded_at' => now()]);

    foreach ([$allowedStaff, $blockedStaff] as $member) {
        $organization->users()->attach($member->id, ['is_owner' => false, 'status' => 'active']);
    }

    makeOrgKey($organization)->update([
        'features' => [AiFeature::AskData->value],
        'allow_all_members' => false,
        'allowed_user_ids' => [$allowedStaff->id],
    ]);

    expect($gateway->canUse($allowedStaff, AiFeature::AskData, $organization))->toBeTrue()
        ->and($gateway->canUse($blockedStaff, AiFeature::AskData, $organization))->toBeFalse()
        ->and($gateway->canUse($allowedStaff, AiFeature::ContentDrafting, $organization))->toBeFalse();
});

test('role-based allow-list grants access by platform role name', function () {
    $gateway = new AiGateway;

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = aiOrganization($owner);

    $searcher = User::factory()->create(['onboarded_at' => now()]);
    $searcher->assignRole(PlatformRole::Searcher->value);

    makeOrgKey($organization)->update([
        'allow_all_members' => false,
        'allowed_roles' => ['searcher'],
    ]);

    expect($gateway->canUse($searcher, AiFeature::AskData))->toBeTrue();
});

test('every dispatch is logged for the credential owner', function () {
    $gateway = new AiGateway;

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = aiOrganization($owner);

    makeOrgKey($organization)->update(['allow_all_members' => true]);

    $credential = $gateway->resolve($owner, AiFeature::AskData);
    $gateway->log($credential, $owner, AiFeature::AskData, promptTokens: 120, completionTokens: 80, durationMs: 900);

    $log = AiUsageLog::first();

    expect($log)->not->toBeNull()
        ->and($log->organization_id)->toBe($organization->id)
        ->and($log->user_id)->toBe($owner->id)
        ->and($log->feature)->toBe('ask_data')
        ->and($log->prompt_tokens)->toBe(120)
        ->and($log->status)->toBe('success');
});
