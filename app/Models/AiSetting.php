<?php

namespace App\Models;

use App\Enums\AiFeature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Bring-your-own-key AI credential. Owner is either an Organization
 * (shared, allow-listed members) or a User (personal key). The API key is
 * encrypted at rest and never exposed to the client after save.
 */
class AiSetting extends Model
{
    protected $fillable = [
        'owner_type',
        'owner_id',
        'provider',
        'model',
        'base_url',
        'api_key',
        'features',
        'allow_all_members',
        'allowed_user_ids',
        'allowed_roles',
        'monthly_token_limit',
    ];

    protected $hidden = [
        'api_key',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'features' => 'array',
            'allow_all_members' => 'boolean',
            'allowed_user_ids' => 'array',
            'allowed_roles' => 'array',
            'monthly_token_limit' => 'integer',
        ];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Whether this credential may power the given feature.
     */
    public function supportsFeature(AiFeature $feature): bool
    {
        return $this->features === null || in_array($feature->value, $this->features, true);
    }
}
