<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Acknowledging the cross-organization property picker.
 *
 * The request body is empty by design — the action only writes a session flag so
 * the picker stops force-opening this login. Kept separate from
 * `DashboardRequest` so a stray `?month=99` on this URL cannot 422 a request
 * that never reads it.
 */
class AcknowledgePickerRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authenticated session write; the route is behind `auth`.
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
