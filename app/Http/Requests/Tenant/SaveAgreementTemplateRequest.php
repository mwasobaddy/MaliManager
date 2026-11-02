<?php

namespace App\Http\Requests\Tenant;

use App\Models\AgreementTemplate;
use App\Models\Property;
use App\Support\TenancyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Guards the four agreement-template write endpoints: organization store and
 * update, and their property-scoped equivalents.
 *
 * The uniqueness scope differs per endpoint, so it is derived from the bound
 * route models rather than passed in: a property route scopes the name to that
 * property, an update ignores the template being edited, and the organization
 * route scopes to the organization with `property_id` null.
 *
 * The cross-organization guards stay in the controller. They abort with a 403
 * and the existing tests assert exactly that; moving them here would turn the
 * response into the app's redirect-and-toast denial instead.
 */
class SaveAgreementTemplateRequest extends FormRequest
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
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('agreement_templates', 'name')
                    ->where('organization_id', $this->organizationId())
                    ->where('property_id', $this->propertyId())
                    ->ignore($this->ignoreId()),
            ],
            'body_html' => ['required', 'string'],
            'agreement_document' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
            'remove_agreement_document' => ['nullable', 'boolean'],
        ];
    }

    private function organizationId(): ?int
    {
        $property = $this->route('property');

        if ($property instanceof Property) {
            return $property->organization_id;
        }

        $template = $this->route('template');

        if ($template instanceof AgreementTemplate) {
            return $template->organization_id;
        }

        return TenancyContext::organization()?->id;
    }

    private function propertyId(): ?int
    {
        $property = $this->route('property');

        return $property instanceof Property ? $property->id : null;
    }

    private function ignoreId(): ?int
    {
        $template = $this->route('template');

        return $template instanceof AgreementTemplate ? $template->id : null;
    }
}
