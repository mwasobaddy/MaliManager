<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a property charge bills. Property templates are recurring only —
 * metered usage, a flat per-unit amount, or a shared master bill. Single
 * fees live on the lease itself as additional charges, where the tenant
 * they belong to is unambiguous.
 *
 * - metered: consumption since the last billed reading × rate.
 * - fixed: a flat amount per unit every period (garbage, security).
 * - shared: a master-meter bill split across the assigned units (RUBS).
 */
enum UtilityChargeKind: string
{
    case Metered = 'metered';
    case Fixed = 'fixed';
    case Shared = 'shared';

    public function label(): string
    {
        return match ($this) {
            self::Metered => 'Metered',
            self::Fixed => 'Every period',
            self::Shared => 'Shared bill',
        };
    }
}
