<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Data\Deposits\RequestDepositRefundData;
use App\Models\DepositRefund;
use App\Models\Lease;
use App\Support\TenancyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDepositRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', DepositRefund::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organizationId = TenancyContext::organization()?->id;

        return [
            'lease_id' => [
                'required',
                'integer',
                // Scoped to the organization: another org's lease id is still a
                // valid integer and would pass a bare `exists`.
                Rule::exists('leases', 'id')
                    ->where('organization_id', $organizationId),
            ],
            // Money arrives in currency units and is converted to cents at the
            // boundary; the column is an integer.
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }

    /**
     * A refund cannot exceed the deposit it is refunding, and a lease should not
     * have two open refunds at once — either would let the same money be
     * promised twice.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $lease = Lease::find($this->integer('lease_id'));

                if ($lease === null) {
                    return;
                }

                $amountCents = (int) round(((float) $this->input('amount')) * 100);
                $depositCents = (int) round(((float) ($lease->deposit ?? 0)) * 100);

                if ($amountCents > $depositCents) {
                    $validator->errors()->add(
                        'amount',
                        'The refund cannot be more than the deposit held for this lease.',
                    );
                }

                $hasOpenRefund = DepositRefund::query()
                    ->where('lease_id', $lease->id)
                    ->pending()
                    ->exists();

                if ($hasOpenRefund) {
                    $validator->errors()->add(
                        'lease_id',
                        'This lease already has a refund awaiting a decision.',
                    );
                }
            },
        ];
    }

    public function toData(): RequestDepositRefundData
    {
        return new RequestDepositRefundData(
            organizationId: (int) TenancyContext::organization()?->id,
            leaseId: $this->integer('lease_id'),
            amountCents: (int) round(((float) $this->input('amount')) * 100),
            reason: trim((string) $this->input('reason')),
        );
    }
}
