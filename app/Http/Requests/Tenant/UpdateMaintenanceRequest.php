<?php

namespace App\Http\Requests\Tenant;

use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaintenanceRequest extends FormRequest
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
            'status' => ['nullable', Rule::in(MaintenanceRequest::STATUSES)],
            'priority' => ['nullable', Rule::in(MaintenanceRequest::PRIORITIES)],
            'assigned_to' => ['nullable', Rule::exists(User::class, 'id')],
            'resolution_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
