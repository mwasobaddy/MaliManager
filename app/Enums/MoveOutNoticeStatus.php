<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a tenant's move-out notice. Pending until the landlord
 * acknowledges it or the tenant withdraws it; acknowledged and withdrawn
 * are terminal, and an ended lease makes its notices moot.
 */
enum MoveOutNoticeStatus: string
{
    case Pending = 'pending';
    case Acknowledged = 'acknowledged';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending review',
            self::Acknowledged => 'Acknowledged',
            self::Withdrawn => 'Withdrawn',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Acknowledged => 'success',
            self::Withdrawn => 'neutral',
        };
    }
}
