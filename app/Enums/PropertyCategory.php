<?php

namespace App\Enums;

/**
 * Whether a property type is a home or a business.
 *
 * The split is fixed and binary — an enum rather than a lookup table, so every
 * property list can filter on it with a plain column and no join.
 */
enum PropertyCategory: string
{
    case Residential = 'residential';
    case Commercial = 'commercial';

    public function label(): string
    {
        return match ($this) {
            self::Residential => 'Residential',
            self::Commercial => 'Commercial',
        };
    }
}
