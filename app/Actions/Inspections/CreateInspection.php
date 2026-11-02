<?php

namespace App\Actions\Inspections;

use App\Actions\Inspections\Concerns\InspectionHelpers;
use App\Models\Inspection;
use App\Models\Property;
use App\Models\User;
use App\Services\Service;
use App\Support\Ai\AiGateway;
use App\Support\TenancyContext;
use Illuminate\Http\UploadedFile;

class CreateInspection extends Service
{
    use InspectionHelpers;

    public function __construct(private AiGateway $gateway) {}

    public function handle(Property $property, User $actor, array $data, array $photos = []): Inspection
    {
        return $this->transaction(function () use ($property, $actor, $data, $photos) {
            $inspection = new Inspection([
                'tenant_id' => TenancyContext::tenantId(),
                'organization_id' => $property->organization_id,
                'title' => $data['title'],
                'inspection_date' => $data['inspection_date'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);
            $inspection->property_id = $property->id;
            $inspection->unit_id = (int) $data['unit_id'];

            $this->save($inspection);

            foreach (array_slice($photos, 0, Inspection::MAX_PHOTOS) as $photo) {
                /** @var UploadedFile $photo */
                $inspection->addMedia($photo)->toMediaCollection('photos');
            }

            return $inspection;
        });
    }
}
