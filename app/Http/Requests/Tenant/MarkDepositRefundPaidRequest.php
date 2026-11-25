<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Data\Deposits\MarkDepositRefundPaidData;
use App\Enums\DepositRefundStatus;
use App\Models\DepositRefund;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class MarkDepositRefundPaidRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var DepositRefund|null $refund */
        $refund = $this->route('refund');

        return $refund !== null && $this->user()->can('pay', $refund);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var DepositRefund|null $refund */
                $refund = $this->route('refund');

                if ($refund !== null && $refund->status !== DepositRefundStatus::Approved) {
                    $validator->errors()->add(
                        'reference',
                        'Only an approved refund can be marked paid.'
                    );
                }
            },
        ];
    }

    public function toData(): MarkDepositRefundPaidData
    {
        return new MarkDepositRefundPaidData(
            reference: $this->filled('reference') ? trim((string) $this->input('reference')) : null,
        );
    }
}
