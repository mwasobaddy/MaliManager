<?php

namespace App\Http\Requests\Tenant;

use App\Support\TenancyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Lease list filters for one property.
 *
 * With no status the list shows the active book — ended leases live in
 * the explicit archive (`status=ended`), and `status=all` restores the
 * unfiltered view. Anything else is rejected rather than silently
 * looking like "no results".
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
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'ended', 'pending', 'all'])],
            'expiring' => ['nullable', 'boolean'],
            'overdue' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'string', 'max:64'],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
