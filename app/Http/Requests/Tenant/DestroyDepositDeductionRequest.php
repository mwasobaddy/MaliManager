<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\DepositRefund;
use App\Services\StaffService;
use App\Support\TenancyContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Guards removing a damage line item. Same gate as adding one —
 * lease.manage on a delegated property — since only an inspector's
 * unsubmitted list can change at all.
 */
class DestroyDepositDeductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var DepositRefund|null $refund */
        $refund = $this->route('refund');
        $organization = TenancyContext::organization();

        if ($refund === null || $organization === null) {
            return false;
        }

        if ($refund->organization_id !== $organization->id) {
            return false;
        }

        if ($this->user()->isOwnerOf($organization)) {
            return true;
        }

        return in_array(
            $refund->lease?->property_id,
            app(StaffService::class)->delegatedPropertyIds($this->user()->membershipFor($organization)),
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
