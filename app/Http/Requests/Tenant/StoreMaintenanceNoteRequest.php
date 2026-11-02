<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Data\Maintenance\AddMaintenanceNoteData;
use App\Enums\NoteVisibility;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestNote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaintenanceNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var MaintenanceRequest|null $record */
        $record = $this->route('record');

        return $record !== null
            && $this->user()->can('create', [MaintenanceRequestNote::class, $record]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:2', 'max:5000'],
            'visibility' => ['required', Rule::enum(NoteVisibility::class)],
        ];
    }

    public function toData(): AddMaintenanceNoteData
    {
        /** @var MaintenanceRequest $record */
        $record = $this->route('record');

        return new AddMaintenanceNoteData(
            maintenanceRequestId: $record->id,
            body: trim((string) $this->input('body')),
            visibility: NoteVisibility::from((string) $this->input('visibility')),
        );
    }
}
