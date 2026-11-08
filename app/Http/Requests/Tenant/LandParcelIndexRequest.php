<?php

namespace App\Http\Requests\Tenant;

use App\Support\TenancyContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The land parcel list.
 *
 * Read-only and parameter-free. Staff see only delegated parcels, enforced in
 * the query; this asserts the organization membership that makes the question
 * meaningful at all.
 */
class LandParcelIndexRequest extends FormRequest
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
