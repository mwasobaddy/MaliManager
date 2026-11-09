<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Nudging one overdue lease takes no input: property and lease come
 * from the route, permission from lease.manage, and throttling plus
 * balance checks live in the action. The class exists so the write
 * endpoint is never on a bare Request.
 */
class RemindLeaseRequest extends FormRequest
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
        return [];
    }
}
