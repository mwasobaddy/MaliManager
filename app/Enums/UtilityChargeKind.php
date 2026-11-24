<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a utility charge bills.
 *
 * - metered: consumption since the last billed reading × rate.
 * - fixed: a flat amount per unit every period (garbage, security).
 * - one_time: a single amount against the next invoice, then done.
 */
enum UtilityChargeKind: string
{
    case Metered = 'metered';
    case Fixed = 'fixed';
    case OneTime = 'one_time';

    public function label(): string
    {
        return match ($this) {
            self::Metered => 'Metered',
            self::Fixed => 'Fixed recurring',
            self::OneTime => 'One-time',
        };
    }
}
