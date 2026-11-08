<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One publicly listed unit.
 *
 * The response contract for both the guest page and the public API: money as
 * decimal strings (never floats), no internal ids beyond the unit itself, no
 * owner or tenant data.
 *
 * @mixin Unit
 */
class ListingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'monthly_rent' => $this->monthly_rent,
            'deposit' => $this->deposit,
            'property_name' => $this->whenLoaded('property', fn () => $this->property?->name),
            'organization_name' => $this->whenLoaded(
                'property',
                fn () => $this->property?->relationLoaded('organization')
                    ? $this->property->organization?->name
                    : $this->property?->organization()->value('name'),
            ),
        ];
    }
}
