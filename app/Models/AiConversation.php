<?php

namespace App\Models;

use App\Support\Ai\Scope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A persisted assistant conversation. Conversations are scoped to a user and a
 * context (platform / org / person) so the stored history can never leak
 * across organizations or users.
 */
class AiConversation extends Model
{
    protected $fillable = [
        'organization_id',
        'user_id',
        'scope',
        'title',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class);
    }

    public static function findLatest(User $user, Scope $scope): ?self
    {
        $query = self::where('user_id', $user->id)
            ->where('scope', $scope->type);

        if ($scope->type !== Scope::PLATFORM) {
            $query->where('organization_id', $scope->organizationId);
        }

        return $query->latest('last_message_at')->first();
    }

    public static function resolve(User $user, Scope $scope, ?int $id, bool $fresh): self
    {
        if ($id !== null && ! $fresh) {
            $existing = self::where('user_id', $user->id)
                ->where('scope', $scope->type)
                ->find($id);

            if ($existing !== null) {
                return $existing;
            }
        }

        if ($fresh) {
            return self::create(self::attributes($user, $scope));
        }

        return self::findLatest($user, $scope) ?? self::create(self::attributes($user, $scope));
    }

    /**
     * @return array{user_id: int, scope: string, organization_id: int|null}
     */
    private static function attributes(User $user, Scope $scope): array
    {
        return [
            'user_id' => $user->id,
            'scope' => $scope->type,
            'organization_id' => $scope->type === Scope::PLATFORM ? null : $scope->organizationId,
        ];
    }
}
