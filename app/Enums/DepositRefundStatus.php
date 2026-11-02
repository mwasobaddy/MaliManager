<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a deposit refund.
 *
 * Modelled as an enum rather than free strings because the transitions are
 * enforced in `DecideDepositRefund`: only `Pending` can move. Anything else
 * would let a paid refund be re-approved and paid twice.
 */
enum DepositRefundStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Paid => 'Paid',
        };
    }

    public function isDecided(): bool
    {
        return $this !== self::Pending;
    }

    /** Status badge variant, per the status taxonomy in the design system. */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::Pending => 'neutral',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Paid => 'info',
        };
    }
}
