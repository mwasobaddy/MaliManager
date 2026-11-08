<?php

use App\Enums\AiFeature;
use App\Enums\AiProvider;
use App\Models\AiSetting;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
});

/**
 * `env()` reads `$_SERVER` first, so tests must set and clear every layer —
 * the `.env` value loaded at boot would otherwise win over `putenv`.
 */
function withOpenAiKey(?string $key, callable $run): void
{
    $hadServer = array_key_exists('OPENCODE_API_KEY', $_SERVER);
    $hadEnv = array_key_exists('OPENCODE_API_KEY', $_ENV);
    $oldServer = $_SERVER['OPENCODE_API_KEY'] ?? null;
    $oldEnv = $_ENV['OPENCODE_API_KEY'] ?? null;

    if ($key === null) {
        unset($_SERVER['OPENCODE_API_KEY'], $_ENV['OPENCODE_API_KEY']);
        putenv('OPENCODE_API_KEY');
    } else {
        $_SERVER['OPENCODE_API_KEY'] = $key;
        $_ENV['OPENCODE_API_KEY'] = $key;
        putenv("OPENCODE_API_KEY={$key}");
    }

    try {
        $run();
    } finally {
        if ($hadServer) {
            $_SERVER['OPENCODE_API_KEY'] = $oldServer;
        } else {
            unset($_SERVER['OPENCODE_API_KEY']);
        }

        if ($hadEnv) {
            $_ENV['OPENCODE_API_KEY'] = $oldEnv;
        } else {
            unset($_ENV['OPENCODE_API_KEY']);
        }

        if ($oldServer !== null || $oldEnv !== null) {
            putenv('OPENCODE_API_KEY='.($oldServer ?? $oldEnv));
        } else {
            putenv('OPENCODE_API_KEY');
        }
    }
}

test('the admin seeder stores the opencode key from the environment', function () {
    withOpenAiKey('oc_sk_test_key_0123456789abcdef', fn () => $this->seed(AdminSeeder::class));

    $admin = User::where('email', 'kelvinramsiel@gmail.com')->firstOrFail();
    $setting = AiSetting::query()
        ->where('owner_type', $admin->getMorphClass())
        ->where('owner_id', $admin->id)
        ->firstOrFail();

    expect($setting->provider)->toBe('opencode')
        ->and($setting->model)->toBe('muse-spark-1.3')
        ->and($setting->api_key)->toBe('oc_sk_test_key_0123456789abcdef')
        ->and($setting->supportsFeature(AiFeature::AskData))->toBeTrue();

    // The settings screen renders the model as a strict dropdown of the
    // provider's suggestions: an unlisted model would display blank.
    expect(AiProvider::OpenCode->suggestedModels())->toContain('muse-spark-1.3');
});

test('the admin seeder skips the ai key when the environment variable is missing', function () {
    withOpenAiKey(null, fn () => $this->seed(AdminSeeder::class));

    expect(AiSetting::query()->count())->toBe(0);
});

test('the admin seeder never overwrites an existing personal key', function () {
    withOpenAiKey('oc_sk_test_key_0123456789abcdef', fn () => $this->seed(AdminSeeder::class));

    $admin = User::where('email', 'kelvinramsiel@gmail.com')->firstOrFail();
    $setting = AiSetting::query()
        ->where('owner_type', $admin->getMorphClass())
        ->where('owner_id', $admin->id)
        ->firstOrFail();
    $setting->update(['api_key' => 'oc_sk_rotated_key_9876543210fedcba']);

    withOpenAiKey('oc_sk_test_key_0123456789abcdef', fn () => $this->seed(AdminSeeder::class));

    expect($setting->refresh()->api_key)->toBe('oc_sk_rotated_key_9876543210fedcba');
});
