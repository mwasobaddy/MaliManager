<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\DepositDeduction;
use App\Models\DepositRefund;
use App\Services\StaffService;
use App\Support\TenancyContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Guards adding a damage line item to a refund under inspection.
 * Authorization mirrors the request gate (lease.manage on a delegated
 * property): the inspector documents, the reviewer approves.
 */
class StoreDepositDeductionRequest extends FormRequest
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
        return [
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'photos' => ['required', 'array', 'min:1', 'max:'.DepositDeduction::MAX_PHOTOS],
            'photos.*' => ['file', 'image', 'max:8192'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photos.required' => 'Attach at least one photo as proof.',
        ];
    }
}
