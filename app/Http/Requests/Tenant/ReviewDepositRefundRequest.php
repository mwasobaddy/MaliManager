<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\DepositRefund;
use App\Services\StaffService;
use App\Support\TenancyContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Guards submitting an inspected refund for review, and the single
 * review decision itself. Same gate as adding deductions: lease.manage
 * on a delegated property. The payout decision stays behind
 * deposit.manage on the pay endpoint.
 */
class ReviewDepositRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var DepositRefund|null $refund */
        $refund = $this->route('refund');

        if ($refund === null || $refund->organization_id !== TenancyContext::organization()?->id) {
            return false;
        }

        return $this->user()->isOwnerOf(TenancyContext::organization())
            || in_array(
                $refund->lease?->property_id,
                app(StaffService::class)->delegatedPropertyIds($this->user()->membershipFor(TenancyContext::organization())),
                true,
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
