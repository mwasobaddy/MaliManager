<?php

namespace App\Http\Requests\Platform;

use App\Data\Plans\ImportPlansData;
use Illuminate\Foundation\Http\FormRequest;

class ImportPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('importPlans') ?? false;
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

    public function toData(): ImportPlansData
    {
        return new ImportPlansData(
            file: $this->file('file'),
            actor: $this->user(),
        );
    }
}
