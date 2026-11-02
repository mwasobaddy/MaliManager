<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Ai\SaveAiSettings;
use App\Enums\AiFeature;
use App\Enums\AiProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PersonalAiRequest;
use App\Http\Requests\Settings\UpdatePersonalAiSettingsRequest;
use App\Models\AiSetting;
use App\Models\AiUsageLog;
use App\Support\Ai\AiGateway;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A user's personal AI credential. It takes precedence over any
 * organization key when resolving AI features.
 */
class PersonalAiController extends Controller
{
    public function __construct(private AiGateway $gateway) {}

    public function edit(PersonalAiRequest $request): Response
    {
        $setting = AiSetting::query()
            ->whereMorphedTo('owner', $request->user())
            ->first();

        return Inertia::render('settings/personal-ai', [
            'setting' => [
                'provider' => $setting?->provider,
                'model' => $setting?->model,
                'base_url' => $setting?->base_url,
                'has_key' => $setting !== null,
                'masked_key' => $this->maskKey($setting?->api_key),
                'features' => $setting?->features ?? [],
            ],
            'providers' => AiProvider::options(),
            'models' => collect(AiProvider::cases())
                ->mapWithKeys(fn (AiProvider $p) => [$p->value => $p->suggestedModels()])
                ->all(),
            'defaultBaseUrls' => collect(AiProvider::cases())
                ->mapWithKeys(fn (AiProvider $p) => [$p->value => $p->baseUrl()])
                ->all(),
            'modelGuidance' => collect(AiProvider::cases())
                ->mapWithKeys(fn (AiProvider $p) => [$p->value => [
                    'tested' => $p->testedModels(),
                    'note' => $p->testedModelsNote(),
                ]])
                ->all(),
            'features' => AiFeature::options(),
            'usage' => [
                'calls' => AiUsageLog::query()
                    ->where('user_id', $request->user()->id)
                    ->whereYear('created_at', now()->year)
                    ->whereMonth('created_at', now()->month)
                    ->count(),
                'tokens' => (int) (AiUsageLog::query()
                    ->where('user_id', $request->user()->id)
                    ->whereYear('created_at', now()->year)
                    ->whereMonth('created_at', now()->month)
                    ->sum('prompt_tokens')
                + AiUsageLog::query()
                    ->where('user_id', $request->user()->id)
                    ->whereYear('created_at', now()->year)
                    ->whereMonth('created_at', now()->month)
                    ->sum('completion_tokens')),
            ],
        ]);
    }

    public function update(UpdatePersonalAiSettingsRequest $request, SaveAiSettings $action): RedirectResponse
    {
        $action->handle($request->user(), $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Personal AI settings saved.']);

        return back();
    }

    public function destroyKey(PersonalAiRequest $request): RedirectResponse
    {
        AiSetting::query()
            ->whereMorphedTo('owner', $request->user())
            ->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Personal API key removed.']);

        return back();
    }

    private function maskKey(?string $key): ?string
    {
        if (! $key) {
            return null;
        }

        return str_repeat('•', max(4, strlen($key) - 4)).substr($key, -4);
    }
}
