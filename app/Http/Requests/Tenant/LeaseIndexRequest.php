<?php

namespace App\Http\Requests\Tenant;

use App\Support\TenancyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Lease list filters for one property.
 *
 * `status` is limited to the two values the list understands; anything else used
 * to be ignored silently, which made a mistyped filter look like "no results"
 * rather than a bad link.
 */
class LeaseIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = TenancyContext::organization();
        $user = $this->user();

        if ($organization === null || $user === null) {
            return false;
        }

        return $user->isOwnerOf($organization)
            || $user->membershipFor($organization) !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['active', 'ended'])],
            'expiring' => ['nullable', 'boolean'],
            'overdue' => ['nullable', 'boolean'],
        ];
    }
}
