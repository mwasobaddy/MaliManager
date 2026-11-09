<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The Plan & trial funnel step: confirm the plan (moved out of step 1 so
 * the choice meets the user after they have committed) and pick how
 * billing reminders should arrive. SMS needs a reachable number, so a
 * phone is required with that channel and saved to the user.
 */
class ChoosePlanRequest extends FormRequest
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
            'plan_slug' => ['required', 'string', Rule::exists('plans', 'slug')->where('is_active', true)],
            'notification_channel' => ['required', 'string', Rule::in(['email', 'sms'])],
            'phone' => ['nullable', 'string', 'max:20', 'required_if:notification_channel,sms'],
        ];
    }
}
