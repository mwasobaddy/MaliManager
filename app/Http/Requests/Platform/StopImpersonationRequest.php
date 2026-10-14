<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Guards POST impersonation/stop.
 *
 * Takes no input: ending a session reads `impersonated_by` off the session and
 * restores the admin recorded there. This exists to satisfy the
 * write-endpoint convention.
 *
 * Deliberately not authorized by a `can:` gate. The user making this request
 * is, by definition, the impersonated owner — they hold none of the platform
 * permissions — so gating it would make the only way out of an impersonation
 * unreachable. The controller handles the "no session to end" case itself.
 */
class StopImpersonationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
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
        return [];
    }
}
