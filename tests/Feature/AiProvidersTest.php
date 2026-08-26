<?php

use App\Enums\AiFeature;
use App\Models\AiSetting;
use App\Models\Plan;
use App\Models\User;
use App\Services\TenantService;
use App\Support\Ai\AiGateway;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Facade;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();

    Facade::clearResolvedInstance('prism');
});

test('resolves all five supported providers with correct drivers and endpoints', function () {
    $gateway = new AiGateway;
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Acme Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );

    $cases = [
        'openai' => ['driver' => 'openai', 'url' => null, 'model' => 'gpt-4o'],
        'anthropic' => ['driver' => 'anthropic', 'url' => null, 'model' => 'claude-sonnet-4-5'],
        'deepseek' => ['driver' => 'deepseek', 'url' => null, 'model' => 'deepseek-chat'],
        // OpenRouter is a native Prism driver; models are vendor-namespaced.
        'openrouter' => ['driver' => 'openrouter', 'url' => null, 'model' => 'anthropic/claude-sonnet-4.5'],
        // NVIDIA NIM has no native Prism driver — routed through the
        // OpenAI driver with the NIM endpoint override.
        'nvidia' => ['driver' => 'openai', 'url' => 'https://integrate.api.nvidia.com/v1', 'model' => 'meta/llama-3.3-70b-instruct'],
    ];

    foreach ($cases as $provider => $expected) {
        Facade::clearResolvedInstance('prism');

        $setting = new AiSetting([
            'provider' => $provider,
            'model' => $expected['model'],
            'api_key' => 'sk-key-for-'.$provider,
            'allow_all_members' => true,
        ]);
        $setting->owner()->associate($organization);
        $setting->save();

        $resolved = $gateway->resolve($owner, AiFeature::AskData);

        expect($resolved)->not->toBeNull()
            ->and($resolved->provider)->toBe($provider)
            ->and($resolved->driver())->toBe($expected['driver'])
            ->and($resolved->requestConfig()['api_key'])->toBe('sk-key-for-'.$provider);

        if ($expected['url'] !== null) {
            expect($resolved->requestConfig()['url'] ?? null)->toBe($expected['url']);
        }

        $setting->delete();
    }
});

test('credential base_url overrides the provider default endpoint', function () {
    $gateway = new AiGateway;
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Acme Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );

    $setting = new AiSetting([
        'provider' => 'openai',
        'model' => 'gpt-4o',
        'base_url' => 'https://my-proxy.example.com/v1',
        'api_key' => 'sk-org-key-1234567890',
        'allow_all_members' => true,
    ]);
    $setting->owner()->associate($organization);
    $setting->save();

    $resolved = $gateway->resolve($owner, AiFeature::AskData);

    expect($resolved->requestConfig()['url'])->toBe('https://my-proxy.example.com/v1');
});
