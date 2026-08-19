<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\SubPermissionKey;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property int|null $person_id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string $status
 * @property string|null $provider
 * @property string|null $provider_id
 * @property Carbon|null $onboarded_at
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'phone', 'password', 'person_id', 'status', 'provider', 'provider_id', 'onboarded_at', 'created_by'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, SoftDeletes, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'onboarded_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_user')
            ->using(OrganizationUser::class)
            ->withPivot('sub_role_id', 'is_owner', 'status')
            ->withTimestamps();
    }

    /**
     * Whether the user is the owner of the given organization.
     */
    public function isOwnerOf(Organization $organization): bool
    {
        return $this->organizations()
            ->where('organizations.id', $organization->id)
            ->wherePivot('is_owner', true)
            ->exists();
    }

    /**
     * The membership pivot for a user in an organization, if any.
     */
    public function membershipFor(Organization $organization): ?OrganizationUser
    {
        return OrganizationUser::where('organization_id', $organization->id)
            ->where('user_id', $this->id)
            ->first();
    }

    /**
     * The organization the user works for as staff (non-owner member
     * with a sub-role), if any.
     */
    public function staffOrganization(): ?Organization
    {
        return $this->organizations()
            ->wherePivot('is_owner', false)
            ->wherePivotNotNull('sub_role_id')
            ->first();
    }

    public function isOnboarded(): bool
    {
        return $this->onboarded_at !== null;
    }

    public function markOnboarded(): void
    {
        $this->onboarded_at = now();
        $this->save();
    }

    /**
     * Whether the user has a given sub-permission inside an organization.
     * Owners bypass sub-permission checks.
     */
    public function hasSubPermission(Organization $organization, SubPermissionKey $key): bool
    {
        $membership = $this->organizations()
            ->where('organizations.id', $organization->id)
            ->withPivot('is_owner', 'sub_role_id')
            ->first();

        if (! $membership) {
            return false;
        }

        if ((bool) $membership->pivot->is_owner) {
            return true;
        }

        if (! $membership->pivot->sub_role_id) {
            return false;
        }

        return SubRole::whereKey($membership->pivot->sub_role_id)
            ->whereHas('subPermissions', fn ($query) => $query->where('key', $key->value))
            ->exists();
    }
}
