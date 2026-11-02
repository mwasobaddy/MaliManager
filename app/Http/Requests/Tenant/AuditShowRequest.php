<?php

namespace App\Http\Requests\Tenant;

use App\Support\TenancyContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One audit entry from the organization log.
 *
 * Asserts membership of the organization in context: owners always, staff
 * through their membership row. The record's own `tenant_id` is still checked
 * in the controller and answers 404 — that is deliberate, because a 403 would
 * tell a prober which ids exist on another tenant.
 */
class AuditShowRequest extends FormRequest
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
        return [];
    }
}
