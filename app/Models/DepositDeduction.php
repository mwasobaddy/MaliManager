<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * One damage line item against a deposit refund, photographed as proof.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $refund_id
 * @property string $description
 * @property int $amount_cents
 * @property int|null $created_by
 */
class DepositDeduction extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    /** Photo proof is capped: damage is documented, not a gallery. */
    public const MAX_PHOTOS = 8;

    protected $fillable = [
        'organization_id',
        'refund_id',
        'description',
        'amount_cents',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
        ];
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(DepositRefund::class, 'refund_id');
    }

    public function registerMediaCollections(): void
    {
        // Damage proof, stored under the asset's folder in StorageLayout
        // via TenantPathGenerator like every other tenant upload.
        $this->addMediaCollection('photos')->acceptsMimeTypes([
            'image/jpeg',
            'image/png',
            'image/webp',
        ]);
    }

    /** Money out, formatted for display. */
    public function amount(): string
    {
        return number_format($this->amount_cents / 100, 2, '.', ',');
    }
}
