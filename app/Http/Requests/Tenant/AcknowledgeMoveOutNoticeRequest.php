<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\MoveOutNotice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Guards acknowledging a move-out notice. Server-side the gate is the
 * sub-permission middleware; the Form Request only confirms the notice
 * belongs to this organization.
 */
class AcknowledgeMoveOutNoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Optional confirmed move-out day: the landlord and tenant may
            // agree a different day than the notice first named. Unlike the
            // tenant's own 30-day notice, an agreed day only needs to not be
            // in the past.
            'move_out_date' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var MoveOutNotice|null $notice */
                $notice = $this->route('notice');

                if ($notice instanceof MoveOutNotice && $notice->status->value !== 'pending') {
                    $validator->errors()->add('notice', 'Only a pending notice can be acknowledged.');
                }
            },
        ];
    }
}
