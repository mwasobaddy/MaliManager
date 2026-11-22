<?php

namespace App\Http\Requests\Tenant;

use App\Data\Units\ImportUnitsData;
use Illuminate\Foundation\Http\FormRequest;

class ImportUnitsRequest extends FormRequest
{
    /**
     * Authorization rides on the create+edit route middleware; this form
     * only validates the upload itself.
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
            // `text/plain` covers .csv on most uploads.
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

    public function toData(): ImportUnitsData
    {
        return new ImportUnitsData(
            file: $this->file('file'),
            actor: $this->user(),
        );
    }
}
