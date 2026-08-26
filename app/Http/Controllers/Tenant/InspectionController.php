<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreInspectionRequest;
use App\Models\Inspection;
use App\Models\Property;
use App\Services\InspectionService;
use App\Services\StaffService;
use App\Support\TenancyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

/**
 * Unit inspections: photos + notes in, AI-generated condition report out.
 * Photos live under {org}/{property}/inspections/ via TenantPathGenerator.
 */
class InspectionController extends Controller
{
    public function index(Request $request, Property $property): Response
    {
        $this->authorize($request, $property);

        $inspections = Inspection::query()
            ->where('property_id', $property->id)
            ->with(['unit:id,name'])
            ->withCount('media')
            ->orderByDesc('inspection_date')
            ->get()
            ->map(fn (Inspection $inspection): array => [
                'id' => $inspection->id,
                'title' => $inspection->title,
                'unit_name' => $inspection->unit?->name,
                'inspection_date' => $inspection->inspection_date?->toDateString(),
                'photo_count' => $inspection->media_count,
                'has_report' => $inspection->ai_report !== null,
            ]);

        return Inertia::render('tenant/inspections/index', [
            'propertySlug' => $property->slug,
            'inspections' => $inspections,
        ]);
    }

    public function create(Request $request, Property $property): Response
    {
        $this->authorize($request, $property);

        return Inertia::render('tenant/inspections/create', [
            'units' => $property->units()->get(['id', 'name']),
        ]);
    }

    public function store(StoreInspectionRequest $request, Property $property, InspectionService $service): RedirectResponse
    {
        $this->authorize($request, $property);

        $inspection = $service->create(
            $property,
            $request->user(),
            $request->validated(),
            (array) ($request->validated()['photos'] ?? []),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Inspection created.']);

        return redirect()->route('tenant.inspections.show', [$property, $inspection]);
    }

    public function show(Request $request, Property $property, Inspection $inspection): Response
    {
        $this->authorize($request, $property);
        abort_if($inspection->property_id !== $property->id, 403);

        return Inertia::render('tenant/inspections/show', [
            'inspection' => [
                'id' => $inspection->id,
                'title' => $inspection->title,
                'unit_name' => $inspection->unit?->name,
                'inspection_date' => $inspection->inspection_date?->toDateString(),
                'notes' => $inspection->notes,
                'ai_report' => $inspection->ai_report,
                'report_generated_at' => $inspection->report_generated_at?->toDateTimeString(),
                'photos' => $inspection->getMedia('photos')->map(fn ($media) => [
                    'url' => $media->getUrl(),
                    'name' => $media->file_name,
                ])->all(),
            ],
        ]);
    }

    /**
     * Generate the AI condition report. Expected failures (no credential,
     * no photos, provider errors) degrade to a toast; unexpected ones bubble.
     */
    public function generateReport(Request $request, Property $property, Inspection $inspection, InspectionService $service): RedirectResponse
    {
        $this->authorize($request, $property);
        abort_if($inspection->property_id !== $property->id, 403);

        try {
            $service->generateReport($inspection, $request->user());

            Inertia::flash('toast', ['type' => 'success', 'message' => 'AI report generated.']);
        } catch (\RuntimeException $e) {
            // Domain-level failures (not configured, no photos).
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }

    private static function reportSchema(): ObjectSchema
    {
        return new ObjectSchema(
            name: 'inspection_report',
            description: 'Structured property inspection report',
            properties: [
                new StringSchema('overall_condition', 'Overall condition: excellent, good, fair or poor'),
                new ArraySchema(
                    'areas',
                    'One entry per inspected area/photo',
                    new ObjectSchema(
                        'area',
                        'A single inspected area',
                        [
                            new StringSchema('area', 'Area name (e.g. Kitchen, Bathroom)'),
                            new StringSchema('condition', 'Condition: excellent, good, fair or poor'),
                            new StringSchema('issues', 'Issues observed, or none'),
                        ],
                        ['area', 'condition', 'issues'],
                    ),
                ),
                new ArraySchema(
                    'recommendations',
                    'Actionable follow-up recommendations',
                    new StringSchema('recommendation', 'A recommended action'),
                ),
            ],
            requiredFields: ['overall_condition', 'areas', 'recommendations'],
        );
    }

    private function authorize(Request $request, Property $property): void
    {
        $user = $request->user();
        $organization = TenancyContext::organization();

        abort_unless($user && $organization, 403);

        if ($user->isOwnerOf($organization)) {
            return;
        }

        $membership = $user->membershipFor($organization);

        if ($membership && in_array($property->id, app(StaffService::class)->delegatedPropertyIds($membership))) {
            return;
        }

        abort(403);
    }
}
