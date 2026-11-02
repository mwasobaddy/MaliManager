<?php

declare(strict_types=1);

namespace App\Actions\PropertyTypes;

use App\Models\PropertyType;
use App\Services\Service;

/**
 * Removes a PropertyType. Authorization lives in the Form Request and the policy; this
 * owns the destructive call so no controller deletes directly.
 */
class DeletePropertyType extends Service
{
    public function handle(PropertyType $propertyType): void
    {
        $propertyType->delete();
    }
}
