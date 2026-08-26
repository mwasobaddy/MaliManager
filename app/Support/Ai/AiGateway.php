<?php

namespace App\Support\Ai;

use App\Enums\AiFeature;
use App\Models\AiSetting;
use App\Models\AiUsageLog;
use App\Models\Organization;
use App\Models\User;

/**
 * Resolves the effective AI credential for a user + feature (personal key
 * first, then the organization's shared key subject to the owner's
 * allow-list) and logs every dispatch.
 *
 * R0 exposes resolution + logging only; feature code (R1+) calls Prism
 * through here so usage capture can never be forgotten.
 */
class AiGateway
{
    /**
     * The effective credential for this user and feature, or null when AI
     * is unavailable (no keys, feature disabled, or user not allowed).
     */
    public function resolve(User $user, AiFeature $feature, ?Organization $organization = null): ?ResolvedAiCredential
    {
        // 1. Personal key always wins.
        $personal = AiSetting::query()
            ->whereMorphedTo('owner', $user)
            ->first();

        if ($personal && $this->usable($personal, $feature)) {
            return $this->credential($personal, source: 'personal');
        }

        // 2. Organization key, if the user may use it.
        $orgs = $this->organizationsOf($user, $organization);
        foreach ($orgs as $organizationModel) {
            $org = AiSetting::query()
                ->whereMorphedTo('owner', $organizationModel)
                ->first();

            if ($org && $this->usable($org, $feature) && $this->memberAllowed($org, $user)) {
                return $this->credential($org, source: 'organization');
            }
        }

        // Occupant-style users hold no membership rows: scan organization
        // credentials whose allow-list names them directly (by user id or
        // role).
        if ($organization === null) {
            $scan = AiSetting::query()
                ->where('owner_type', (new Organization)->getMorphClass())
                ->get()
                ->first(fn (AiSetting $setting) => $this->usable($setting, $feature)
                    && $this->memberAllowed($setting, $user));

            if ($scan) {
                return $this->credential($scan, source: 'organization');
            }
        }

        return null;
    }

    public function canUse(User $user, AiFeature $feature, ?Organization $organization = null): bool
    {
        return $this->resolve($user, $feature, $organization) !== null;
    }

    /**
     * Persist one usage record per dispatch.
     */
    public function log(
        ResolvedAiCredential $credential,
        User $user,
        AiFeature $feature,
        int $promptTokens = 0,
        int $completionTokens = 0,
        int $durationMs = 0,
        string $status = 'success',
        ?string $error = null,
    ): void {
        AiUsageLog::create([
            'organization_id' => $credential->setting->owner instanceof Organization
                ? $credential->setting->owner->id
                : null,
            'user_id' => $user->id,
            'feature' => $feature->value,
            'provider' => $credential->provider,
            'model' => $credential->model,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'duration_ms' => $durationMs,
            'status' => $status,
            'error_message' => $error,
        ]);
    }

    private function usable(AiSetting $setting, AiFeature $feature): bool
    {
        return $setting->supportsFeature($feature)
            && $this->withinMonthlyLimit($setting);
    }

    /**
     * Optional soft cap: total tokens this calendar month across the
     * credential's dispatches. 0 = unlimited.
     */
    private function withinMonthlyLimit(AiSetting $setting): bool
    {
        $limit = $setting->monthly_token_limit;

        if ($limit <= 0) {
            return true;
        }

        $query = AiUsageLog::query()
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month);

        if ($setting->owner_type === (new Organization)->getMorphClass()) {
            $query->where('organization_id', $setting->owner_id);
        } else {
            $query->where('user_id', $setting->owner_id);
        }

        $used = (int) $query->sum('prompt_tokens') + (int) $query->sum('completion_tokens');

        return $used < $limit;
    }

    /**
     * Organization-scope allow-list: all members, explicit users, or any of
     * the user's platform roles / sub-role slugs matching the list.
     */
    private function memberAllowed(AiSetting $setting, User $user): bool
    {
        if ($setting->allow_all_members) {
            return true;
        }

        $allowedUsers = $setting->allowed_user_ids ?? [];

        if (in_array($user->id, $allowedUsers, true)) {
            return true;
        }

        $allowedRoles = $setting->allowed_roles ?? [];
        $userRoles = $user->getRoleNames()
            ->map(fn (string $name) => strtolower($name))
            ->all();

        foreach ($allowedRoles as $role) {
            if (in_array(strtolower($role), $userRoles, true)) {
                return true;
            }
        }

        return false;
    }

    private function credential(AiSetting $setting, string $source): ResolvedAiCredential
    {
        return new ResolvedAiCredential(
            setting: $setting,
            provider: $setting->provider,
            model: $setting->model,
            apiKey: $setting->api_key,
            source: $source,
        );
    }

    // Note: ResolvedAiCredential::requestConfig() carries the optional
    // base_url from ai_settings for endpoint overrides (NVIDIA/proxies).

    /**
     * @return iterable<Organization>
     */
    private function organizationsOf(User $user, ?Organization $only): array
    {
        if ($only) {
            return [$only];
        }

        return $user->organizations()->get()->all();
    }
}
