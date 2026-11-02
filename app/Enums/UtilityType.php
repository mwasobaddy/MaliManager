<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The utilities a unit can be metered for.
 *
 * A backed enum rather than a string constant list: it casts on the model
 * (`utility => UtilityType::class`), it can be validated with `Rule::enum()`,
 * and a typo becomes a type error instead of a row nobody can query.
 */
enum UtilityType: string
{
    case Electricity = 'electricity';
    case Water = 'water';
    case Gas = 'gas';

    public function label(): string
    {
        return match ($this) {
            self::Electricity => 'Electricity',
            self::Water => 'Water',
            self::Gas => 'Gas',
        };
    }

    /** Units as they are printed next to the number. */
    public function unitOfMeasure(): string
    {
        return match ($this) {
            self::Electricity => 'kWh',
            self::Water => 'm³',
            self::Gas => 'm³',
        };
    }
}
