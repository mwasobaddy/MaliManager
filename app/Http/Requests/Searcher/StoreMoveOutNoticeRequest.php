<?php

declare(strict_types=1);

namespace App\Http\Requests\Searcher;

use App\Data\MoveOut\SubmitMoveOutNoticeData;
use App\Models\Lease;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A tenant gives 30 days notice on one of their own active leases. The
 * lease must belong to the tenant's person and be active; anything else
 * is a 404-shaped refusal, not a field error, since it is about identity
 * rather than input.
 */
class StoreMoveOutNoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->person_id !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $personId = $this->user()?->person_id;

        return [
            'lease_id' => [
                'required',
                'integer',
                Rule::exists('leases', 'id')
                    ->where('person_id', $personId)
                    ->where('status', 'active'),
            ],
            'move_out_date' => ['required', 'date', 'after_or_equal:'.SubmitMoveOutNoticeData::minimumDate()],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'move_out_date.after_or_equal' => 'Notice needs at least 30 days — pick a date on or after '.SubmitMoveOutNoticeData::minimumDate().'.',
        ];
    }
}
