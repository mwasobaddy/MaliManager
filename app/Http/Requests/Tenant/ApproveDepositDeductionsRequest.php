<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Data\Deposits\ReviewDepositDeductionsData;
use App\Enums\DepositRefundStatus;
use App\Models\DepositRefund;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Guards approving (or sending back) an inspected refund's deductions.
 * Only a deposit.review holder may sign off someone else's numbers; the
 * inspector who documented them cannot.
 */
class ApproveDepositDeductionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var DepositRefund|null $refund */
        $refund = $this->route('refund');

        return $refund !== null && $this->user()->can('review', $refund);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var DepositRefund|null $refund */
                $refund = $this->route('refund');

                if ($refund !== null && $refund->status !== DepositRefundStatus::InReview) {
                    $validator->errors()->add('decision', 'Only a submitted refund can be reviewed.');
                }

                if ($this->input('decision') === 'reject' && trim((string) $this->input('note')) === '') {
                    $validator->errors()->add('note', 'Say what needs re-inspecting so the inspector knows what to fix.');
                }
            },
        ];
    }

    public function toData(): ReviewDepositDeductionsData
    {
        return new ReviewDepositDeductionsData(
            approve: $this->input('decision') === 'approve',
            note: $this->filled('note') ? trim((string) $this->input('note')) : null,
        );
    }
}
