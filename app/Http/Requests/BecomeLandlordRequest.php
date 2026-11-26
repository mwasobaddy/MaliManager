<?php

namespace App\Http\Requests;

use App\Data\Onboarding\BecomeLandlordData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Becoming a landlord: the two things signup deliberately did not ask.
 *
 * Everything else — name, phone, password — is already known from onboarding.
 * The organization starts on the free plan; plan choice moved to the Plan &
 * trial funnel step, where it meets the user after they have committed.
 */
class BecomeLandlordRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Self-service upgrade; the org does not exist yet to authorize against.
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organization_name' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'list_publicly' => ['nullable', 'boolean'],
            'mode' => ['required', Rule::in(['simple', 'pro'])],
        ];
    }

    public function toData(): BecomeLandlordData
    {
        return new BecomeLandlordData(
            organizationName: (string) $this->string('organization_name'),
            currency: (string) $this->string('currency'),
            listPublicly: $this->boolean('list_publicly'),
            mode: (string) $this->string('mode', 'simple'),
        );
    }
}
