<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Ai\DeleteAiSetting;
use App\Actions\Ai\SaveAiSettings;
use App\Enums\AiFeature;
use App\Enums\AiProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AiSettingsRequest;
use App\Http\Requests\Tenant\UpdateAiSettingsRequest;
use App\Models\AiSetting;
use App\Models\AiUsageLog;
use App\Models\User;
use App\Support\Ai\AiGateway;
use App\Support\TenancyContext;
use Illuminate\Http\RedirectResponse;
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

    public function edit(AiSettingsRequest $request): Response
    {
        $organization = TenancyContext::organization();

        $setting = AiSetting::query()
            ->whereMorphedTo('owner', $organization)
            ->first();

        $members = User::query()
            ->whereHas('organizations', fn ($query) => $query
                ->whereKey($organization->id)
                ->where('organization_user.status', 'active'))
            ->get(['id', 'name', 'email']);

        return Inertia::render('organisation/ai-settings/index', [
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
            'members' => $members->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])->all(),
            'usage' => $this->monthlyUsage($organization?->id),
        ]);
    }

    public function update(UpdateAiSettingsRequest $request, SaveAiSettings $action): RedirectResponse
    {
        $organization = TenancyContext::organization();

        abort_unless($request->user()->isOwnerOf($organization), 403);

        $action->handle($organization, $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'AI settings saved.']);

        return back();
    }

    public function destroyKey(AiSettingsRequest $request, DeleteAiSetting $delete): RedirectResponse
    {
        $delete->handle(TenancyContext::organization());

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
