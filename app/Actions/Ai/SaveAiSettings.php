<?php

declare(strict_types=1);

namespace App\Actions\Ai;

use App\Data\Ai\AiSettingsData;
use App\Models\AiSetting;
use App\Services\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates the AI setting for a morph owner — an organization for
 * tenant AI, or a user for personal AI.
 *
 * The "API key is required" case throws ValidationException rather than calling
 * back()->withErrors(), so the action stays free of HTTP. Laravel turns it into
 * the same redirect-back with the same field error.
 */
class SaveAiSettings extends Service
{
    public function handle(Model $owner, AiSettingsData $data): AiSetting
    {
        $setting = AiSetting::query()
            ->whereMorphedTo('owner', $owner)
            ->first();

        $attributes = [
            'provider' => $data->provider,
            'model' => $data->model,
            'base_url' => $data->baseUrl,
            'features' => array_values($data->features),
        ];

        if ($data->allowAllMembers !== null) {
            $attributes['allow_all_members'] = $data->allowAllMembers;
        }

        if ($data->allowedUserIds !== null) {
            $attributes['allowed_user_ids'] = array_map(intval(...), $data->allowedUserIds);
        }

        if ($data->apiKey) {
            $attributes['api_key'] = $data->apiKey;
        }

        if ($setting) {
            if (! $data->apiKey && ! $setting->api_key) {
                throw ValidationException::withMessages(['api_key' => 'An API key is required.']);
            }

            $setting->update($attributes);

            return $setting;
        }

        if (! $data->apiKey) {
            throw ValidationException::withMessages(['api_key' => 'An API key is required.']);
        }

        $setting = new AiSetting($attributes + ['api_key' => $data->apiKey]);
        $setting->owner()->associate($owner);
        $setting->save();

        return $setting;
    }
}
