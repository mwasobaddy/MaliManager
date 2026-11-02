<?php

declare(strict_types=1);

namespace App\Actions\PropertyTypes;

use App\Models\PropertyType;
use App\Services\Service;

/**
 * Applies validated changes to an existing property type. Status transitions are
 * not allowed here — activation goes through the dedicated endpoint so a normal
 * edit cannot silently reactivate a type.
 */
class UpdatePropertyType extends Service
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(PropertyType $propertyType, array $data): PropertyType
    {
        $propertyType->update($data);

        return $propertyType;
    }
}
