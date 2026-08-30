<?php

namespace App\Http\Controllers\Settings;

use App\Enums\AiFeature;
use App\Enums\AiProvider;
use App\Http\Controllers\Controller;
use App\Models\AiSetting;
use App\Models\AiUsageLog;
use App\Support\Ai\AiGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A user's personal AI credential. It takes precedence over any
 * organization key when resolving AI features.
 */
class PersonalAiController extends Controller
{
    public function __construct(private AiGateway $gateway) {}

    public function edit(Request $request): Response
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

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'provider' => ['required', Rule::in(array_column(AiProvider::cases(), 'value'))],
            'model' => ['required', 'string', 'max:100'],
            'base_url' => ['nullable', 'url', 'max:255'],
            'api_key' => ['nullable', 'string', 'min:20'],
            'features' => ['nullable', 'array'],
            'features.*' => [Rule::in(array_column(AiFeature::cases(), 'value'))],
        ]);

        /** @var AiSetting|null $setting */
        $setting = AiSetting::query()
            ->whereMorphedTo('owner', $request->user())
            ->first();

        $attributes = [
            'provider' => $validated['provider'],
            'model' => $validated['model'],
            'base_url' => $validated['base_url'] ?? null,
            'features' => array_values($validated['features'] ?? []),
        ];

        if (! empty($validated['api_key'])) {
            $attributes['api_key'] = $validated['api_key'];
        }

        if ($setting) {
            if (empty($validated['api_key']) && ! $setting->api_key) {
                return back()->withErrors(['api_key' => 'An API key is required.']);
            }

            $setting->update($attributes);
        } else {
            if (empty($validated['api_key'])) {
                return back()->withErrors(['api_key' => 'An API key is required.']);
            }

            $setting = new AiSetting($attributes + ['api_key' => $validated['api_key']]);
            $setting->owner()->associate($request->user());
            $setting->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Personal AI settings saved.']);

        return back();
    }

    public function destroyKey(Request $request): RedirectResponse
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
