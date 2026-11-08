<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Maintenance list filters.
 *
 * Read-only: `status` and `priority` narrow the organization's own requests, so
 * there is no other record to authorize. Authorization is membership, asserted
 * by the surrounding route group.
 */
class MaintenanceIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'max:64'],
            'priority' => ['nullable', 'string', 'max:64'],
        ];
    }
}
