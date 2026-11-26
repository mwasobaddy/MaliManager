<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Data\Deposits\DecideDepositRefundData;
use App\Enums\DepositRefundStatus;
use App\Models\DepositRefund;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateDepositRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var DepositRefund|null $refund */
        $refund = $this->route('refund');

        return $refund !== null && $this->user()->can('decide', $refund);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * An already-decided refund is not an invalid field, it is a stale page.
     * Rejecting it here means the user sees why rather than the action silently
     * doing nothing.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var DepositRefund|null $refund */
                $refund = $this->route('refund');

                if ($refund !== null && $refund->status === DepositRefundStatus::Paid) {
                    $validator->errors()->add(
                        'decision',
                        'This refund has already been paid.',
                    );
                } elseif ($refund !== null && $refund->status !== DepositRefundStatus::Pending) {
                    $validator->errors()->add(
                        'decision',
                        'Only a pending refund can be decided. Inspections and reviews finish first.',
                    );
                }
            },
        ];
    }

    public function toData(): DecideDepositRefundData
    {
        return new DecideDepositRefundData(
            approve: $this->input('decision') === 'approve',
            note: $this->filled('note') ? trim((string) $this->input('note')) : null,
        );
    }
}
