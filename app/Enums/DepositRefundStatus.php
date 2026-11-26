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
    case AwaitingInspection = 'awaiting_inspection';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::AwaitingInspection => 'Awaiting inspection',
            self::InReview => 'In review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Paid => 'Paid',
        };
    }

    /**
     * Money is still in play: a new refund must not be opened while one
     * of these is alive, and the dashboard nudge stays quiet.
     */
    public function isOpen(): bool
    {
        return in_array($this, [
            self::Pending,
            self::AwaitingInspection,
            self::InReview,
            self::Approved,
        ], true);
    }

    public function isDecided(): bool
    {
        return in_array($this, [self::Approved, self::Rejected, self::Paid], true);
    }

    /** Status badge variant, per the status taxonomy in the design system. */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::Pending => 'neutral',
            self::AwaitingInspection => 'warning',
            self::InReview => 'neutral',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Paid => 'info',
        };
    }
}
