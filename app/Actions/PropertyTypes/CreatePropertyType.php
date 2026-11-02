<?php

declare(strict_types=1);

namespace App\Actions\PropertyTypes;

use App\Models\PropertyType;
use App\Models\User;
use App\Services\Service;

/**
 * Creates a property type. Sort order is derived when the caller does not supply
 * one, and new types are always created active — both invariants live here so a
 * second entry point cannot forget them.
 */
class CreatePropertyType extends Service
{
    /**
     * @param  array<string, mixed>  $data  validated input, including the derived slug
     */
    public function handle(array $data, ?int $sortOrder, ?User $actor): PropertyType
    {
        return PropertyType::create([
            ...$data,
            'sort_order' => $sortOrder ?: $this->nextSortOrder(),
            'status' => 'active',
            'created_by' => $actor?->id,
        ]);
    }

    private function nextSortOrder(): int
    {
        return (int) PropertyType::max('sort_order') + 1;
    }
}
