<?php

namespace App\Http\Requests\Organizations;

use App\Enums\OrganizationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationStatusRequest extends FormRequest
{
    /**
     * Authorization is handled by the `can:editOrganizations` route middleware.
     */
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
            'status' => ['required', Rule::enum(OrganizationStatus::class)],
        ];
    }
}
