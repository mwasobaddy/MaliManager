<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class ImportUserRequest extends FormRequest
{
    /**
     * Authorization is handled by the `can:importUsers` route middleware.
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
            // `text/plain` covers .csv on most uploads. The cap is smaller than
            // the organizations import: each row creates or updates a person
            // *and* a user account, with role sync.
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimes' => 'Upload a CSV file (a .txt file containing CSV also works).',
            'file.max' => 'The import file may not be larger than 2 MB.',
        ];
    }
}
