<?php

declare(strict_types=1);

namespace App\Http\Resources\Maintenance;

use App\Models\MaintenanceRequestNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MaintenanceRequestNote
 */
class MaintenanceNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'visibility' => $this->visibility->value,
            'visibility_label' => $this->visibility->label(),
            'author_id' => $this->author_id,
            'author_name' => $this->whenLoaded('author', fn () => $this->author?->name),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
