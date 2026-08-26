<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\AiFeature;
use App\Enums\AiProvider;
use App\Http\Controllers\Controller;
use App\Models\AiSetting;
use App\Models\AiUsageLog;
use App\Models\User;
use App\Support\Ai\AiGateway;
use App\Support\TenancyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Organization-level AI configuration: BYO provider/model/key, feature
 * toggles, and the member allow-list. Owner-only (no sub-permission key —
 * paying for the key is an ownership decision).
 */
class AiSettingsController extends Controller
{
    public function __construct(private AiGateway $gateway) {}

    public function edit(Request $request): Response
    {
        $organization = TenancyContext::organization();

        abort_unless($request->user()->isOwnerOf($organization), 403);

        $setting = AiSetting::query()
            ->whereMorphedTo('owner', $organization)
            ->first();

        $members = User::query()
            ->whereHas('organizations', fn ($query) => $query
                ->whereKey($organization->id)
                ->where('organization_user.status', 'active'))
            ->get(['id', 'name', 'email']);

        return Inertia::render('tenant/ai-settings/index', [
            'setting' => [
                'provider' => $setting?->provider,
                'model' => $setting?->model,
                'base_url' => $setting?->base_url,
                'has_key' => $setting !== null,
                'masked_key' => $this->maskKey($setting?->api_key),
                'features' => $setting?->features ?? [],
                'allow_all_members' => $setting?->allow_all_members ?? false,
                'allowed_user_ids' => $setting?->allowed_user_ids ?? [],
            ],
            'providers' => AiProvider::options(),
            'models' => collect(AiProvider::cases())
                ->mapWithKeys(fn (AiProvider $p) => [$p->value => $p->suggestedModels()])
                ->all(),
            'features' => AiFeature::options(),
            'members' => $members->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])->all(),
            'usage' => $this->monthlyUsage($organization?->id),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $organization = TenancyContext::organization();

        abort_unless($request->user()->isOwnerOf($organization), 403);

        $validated = $request->validate([
            'provider' => ['required', Rule::in(array_column(AiProvider::cases(), 'value'))],
            'model' => ['required', 'string', 'max:100'],
            'base_url' => ['nullable', 'url', 'max:255'],
            // Write-only: empty string means "keep existing key".
            'api_key' => ['nullable', 'string', 'min:20'],
            'features' => ['nullable', 'array'],
            'features.*' => [Rule::in(array_column(AiFeature::cases(), 'value'))],
            'allow_all_members' => ['nullable', 'boolean'],
            'allowed_user_ids' => ['nullable', 'array'],
            'allowed_user_ids.*' => ['integer'],
        ]);

        /** @var AiSetting|null $setting */
        $setting = AiSetting::query()
            ->whereMorphedTo('owner', $organization)
            ->first();

        $attributes = [
            'provider' => $validated['provider'],
            'model' => $validated['model'],
            'base_url' => $validated['base_url'] ?? null,
            'features' => array_values($validated['features'] ?? []),
            'allow_all_members' => $request->boolean('allow_all_members'),
            'allowed_user_ids' => array_map(intval(...), $validated['allowed_user_ids'] ?? []),
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
            $setting->owner()->associate($organization);
            $setting->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'AI settings saved.']);

        return back();
    }

    public function destroyKey(Request $request): RedirectResponse
    {
        $organization = TenancyContext::organization();

        abort_unless($request->user()->isOwnerOf($organization), 403);

        AiSetting::query()
            ->whereMorphedTo('owner', $organization)
            ->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Organization API key removed.']);

        return back();
    }

    private function maskKey(?string $key): ?string
    {
        if (! $key) {
            return null;
        }

        return str_repeat('•', max(4, strlen($key) - 4)).substr($key, -4);
    }

    /**
     * This month's token/call totals per user, for the paying owner.
     *
     * @return array<int, array{name: string, calls: int, tokens: int}>
     */
    private function monthlyUsage(?int $organizationId): array
    {
        if (! $organizationId) {
            return [];
        }

        return AiUsageLog::query()
            ->where('organization_id', $organizationId)
            ->join('users', 'users.id', '=', 'ai_usage_logs.user_id')
            ->whereYear('ai_usage_logs.created_at', now()->year)
            ->whereMonth('ai_usage_logs.created_at', now()->month)
            ->groupBy('ai_usage_logs.user_id', 'users.name')
            ->selectRaw('ai_usage_logs.user_id, users.name, count(*) as calls')
            ->selectRaw('coalesce(sum(prompt_tokens + completion_tokens), 0) as tokens')
            ->orderByDesc('tokens')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'calls' => (int) $row->calls,
                'tokens' => (int) $row->tokens,
            ])
            ->all();
    }
}
