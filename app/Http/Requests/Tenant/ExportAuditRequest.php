<?php

namespace App\Http\Requests\Tenant;

use App\Data\Audit\ExportAuditData;
use App\Enums\SubPermissionKey;
use App\Support\TenancyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Organization audit-log CSV export.
 *
 * The tenant is taken from the tenancy context, never from the query string, so
 * a member cannot widen the export to another organization's log. Filters and
 * sort match the index.
 */
class ExportAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = TenancyContext::organization();
        $user = $this->user();

        if ($organization === null || $user === null) {
            return false;
        }

        return $user->isOwnerOf($organization)
            || $user->hasSubPermission($organization, SubPermissionKey::AuditView);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'actor' => ['nullable', 'string', 'max:191'],
            'event' => ['nullable', 'string', 'max:191'],
            'log_name' => ['nullable', 'string', 'max:191'],
            'subject_type' => ['nullable', 'string', 'max:191'],
            'search' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort' => ['nullable', 'string', 'max:64'],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }

    public function toData(): ExportAuditData
    {
        return new ExportAuditData(
            filters: $this->only([
                'actor', 'event', 'log_name', 'subject_type',
                'search', 'date_from', 'date_to',
            ]),
            sortColumn: $this->string('sort')->toString(),
            sortDirection: $this->string('direction')->toString(),
            tenantId: TenancyContext::tenantId(),
        );
    }
}
