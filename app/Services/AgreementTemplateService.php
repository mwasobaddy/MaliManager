<?php

namespace App\Services;

use App\Models\AgreementTemplate;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use App\Support\LeaseAgreementTemplate;
use Illuminate\Validation\Rule;

/**
 * Creates, updates and deletes agreement templates for both scopes:
 * organization-wide (property_id null) and property-scoped.
 * Bodies are sanitized to the shared formatting allowlist; uniqueness of
 * names is enforced per scope by the callers' validation.
 */
class AgreementTemplateService extends Service
{
    /**
     * Validation rules shared by every controller entry point. The unique
     * rule is scoped to one organization + property (propertyId null =
     * organization-wide template).
     *
     * @return array<string, mixed>
     */
    public static function rules(?int $organizationId, ?int $propertyId, ?int $ignoreId = null): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('agreement_templates', 'name')
                    ->where('organization_id', $organizationId)
                    ->where('property_id', $propertyId)
                    ->ignore($ignoreId),
            ],
            'body_html' => ['required', 'string'],
        ];
    }

    public function create(User $actor, int $organizationId, ?int $propertyId, string $name, string $bodyHtml): AgreementTemplate
    {
        return $this->transaction(function () use ($actor, $organizationId, $propertyId, $name, $bodyHtml) {
            $template = new AgreementTemplate([
                'organization_id' => $organizationId,
                'property_id' => $propertyId,
                'name' => $name,
                'body_html' => LeaseAgreementTemplate::sanitize($bodyHtml),
                'created_by' => $actor->id,
            ]);

            return $this->save($template);
        });
    }

    public function update(AgreementTemplate $template, User $actor, string $name, string $bodyHtml): AgreementTemplate
    {
        return $this->transaction(function () use ($template, $name, $bodyHtml) {
            $template->update([
                'name' => $name,
                'body_html' => LeaseAgreementTemplate::sanitize($bodyHtml),
            ]);

            return $template->refresh();
        });
    }

    public function remove(AgreementTemplate $template): void
    {
        $this->transaction(function () use ($template): void {
            $this->delete($template);
        });
    }
}
