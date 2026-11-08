<?php

namespace App\Models;

use App\Enums\OrganizationStatus;
use App\Support\Search;
use App\Support\StorageLayout;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $name
 * @property string $slug
 * @property string|null $email
 * @property string|null $phone
 * @property OrganizationStatus $status
 * @property int|null $plan_id
 * @property array|null $settings
 * @property CarbonImmutable|null $trial_ends_at
 * @property CarbonImmutable|null $subscription_ends_at
 * @property int|null $created_by
 */
class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'email',
        'phone',
        'status',
        'plan_id',
        'settings',
        'trial_ends_at',
        'subscription_ends_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'status' => OrganizationStatus::class,
            'trial_ends_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Every new organization gets its document folder skeleton on the
        // configured disk (local in development, S3 in production).
        static::created(function (Organization $organization): void {
            StorageLayout::bootstrapOrganization($organization);
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Whether the attached paid plan is currently usable: inside the
     * one-month free trial or inside a paid subscription period.
     */
    public function onTrial(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscription_ends_at !== null && $this->subscription_ends_at->isFuture();
    }

    public function hasPaidAccess(): bool
    {
        return $this->plan !== null && $this->plan->price > 0
            && ($this->onTrial() || $this->hasActiveSubscription());
    }

    /**
     * The plan whose limits apply right now: the attached paid plan while
     * trial or subscription is active, otherwise the free tier. Limit
     * enforcement must read this, never `plan` directly, or an expired
     * trial keeps paid limits.
     */
    public function effectivePlan(): ?Plan
    {
        if ($this->hasPaidAccess()) {
            return $this->plan;
        }

        return Plan::free();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The single owner, eager-loadable.
     *
     * Exists so a list can show or hide an "impersonate" control per row
     * without a query per row. `first()` on a relation returns null when the
     * organization has no owner yet, which is a legitimate state.
     */
    public function owner(): BelongsToMany
    {
        return $this->users()->wherePivot('is_owner', true);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_user')
            ->using(OrganizationUser::class)
            ->withPivot('sub_role_id', 'is_owner', 'status')
            ->withTimestamps();
    }

    public function subRoles(): HasMany
    {
        return $this->hasMany(SubRole::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function landParcels(): HasMany
    {
        return $this->hasMany(LandParcel::class);
    }

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function occupants(): HasMany
    {
        return $this->hasMany(Occupant::class);
    }

    /**
     * The admin list predicate: search across name, slug and email, plus an
     * exact status match. Shared by the index and the CSV export so "export
     * all" can never disagree with the table.
     *
     * @param  array{search?: string, status?: string}  $filters
     */
    public function scopeFiltered(Builder $query, array $filters): Builder
    {
        return $query
            ->leftJoin('plans', 'plans.id', '=', 'organizations.plan_id')
            ->when(
                ($filters['search'] ?? '') !== '',
                // `when()` hands the *condition* to the callback, not the term,
                // so the term is closed over rather than taken as a parameter.
                fn (Builder $q) => Search::apply($q, $filters['search'], ['organizations.name', 'organizations.slug', 'organizations.email']),
            )
            ->when(
                ($filters['status'] ?? '') !== '',
                fn (Builder $q) => $q->where('organizations.status', $filters['status']),
            );
    }
}
