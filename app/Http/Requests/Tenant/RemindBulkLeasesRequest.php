<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Bulk rent reminders: the lease ids to nudge. Property scoping is
 * enforced in the action (foreign ids refuse the batch); permission
 * comes from lease.manage on the route.
 */
class RemindBulkLeasesRequest extends FormRequest
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
            'lease_ids' => ['required', 'array', 'min:1'],
            'lease_ids.*' => ['integer', 'exists:leases,id'],
        ];
    }
}
