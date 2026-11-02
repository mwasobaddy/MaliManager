<?php

use App\Actions\Ai\SaveAiSettings;
use App\Data\Ai\AiSettingsData;
use App\Models\Plan;
use App\Models\User;
use App\Services\TenantService;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/*
 * The "API key is required" branch used to be `back()->withErrors()` inside the
 * controller. It now throws ValidationException from SaveAiSettings. The personal
 * endpoint proves Laravel maps that to the same redirect-back with a field error;
 * the organization case is covered at the action, because its route sits behind
 * `has-property` and a fresh org has no property to reach it with.
 */

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

function aiSettingsData(?string $apiKey = null): AiSettingsData
{
    return new AiSettingsData(
        provider: 'openai',
        model: 'gpt-4o',
        baseUrl: null,
        features: ['ask_data'],
        apiKey: $apiKey,
        allowAllMembers: false,
        allowedUserIds: [],
    );
}

test('creating personal AI settings without a key redirects back with an error', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);

    $this->actingAs($user)
        ->put(route('settings.personal-ai.update'), [
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'features' => ['ask_data'],
        ])
        ->assertSessionHasErrors('api_key');

    $this->assertDatabaseCount('ai_settings', 0);
});

test('creating organization AI settings without a key is rejected and writes nothing', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Acme Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );

    expect(fn () => app(SaveAiSettings::class)->handle($organization, aiSettingsData()))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('ai_settings', 0);
});
