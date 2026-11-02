<?php

namespace App\Services;

use App\Actions\Inspections\Concerns\InspectionHelpers;
use App\Actions\Inspections\CreateInspection;
use App\Actions\Inspections\DeleteInspection;
use App\Actions\Inspections\GenerateInspectionReport;
use App\Models\Inspection;
use App\Models\Property;
use App\Models\User;
use App\Support\Ai\AiGateway;
use Illuminate\Http\UploadedFile;

/**
 * Unit inspections: creation with photo attachments, and AI-generated
 * condition reports from those photos plus inspector notes. Report
 * generation resolves the caller's BYO credential through AiGateway and
 * logs usage; failures propagate to the controller for toast handling.
 */
class InspectionService extends Service
{
    use InspectionHelpers;

    public function __construct(private AiGateway $gateway) {}

    /**
     * @param  array<string, mixed>  $data  validated request payload
     * @param  list<UploadedFile>  $photos
     */
    public function create(Property $property, User $actor, array $data, array $photos = []): Inspection
    {
        return app(CreateInspection::class)->handle($property, $actor, $data, $photos);
    }

    public function softDelete(Inspection $inspection): void
    {
        app(DeleteInspection::class)->handle($inspection);
    }

    /**
     * Generate the structured condition report from photos + notes using
     * the caller's BYO credential (vision input). Usage is logged; the
     * report is stored on the inspection.
     *
     * @return array<string, mixed> the generated report
     */
    public function generateReport(Inspection $inspection, User $inspector): array
    {
        return app(GenerateInspectionReport::class)->handle($inspection, $inspector);
    }
}
