<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\Metering\RecordMeterReading;
use App\Enums\UtilityType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\MeterReadingIndexRequest;
use App\Http\Requests\Tenant\StoreMeterReadingRequest;
use App\Http\Resources\Metering\UnitMeterReadingResource;
use App\Models\Property;
use App\Models\Unit;
use App\Models\UnitMeterReading;
use App\Support\InertiaRedirect;
use App\Support\TenancyContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Utility meter readings for the organization's units.
 *
 * Every method is a route: read the request, call one action, return a
 * response. The consumption calculation lives in `RecordMeterReading`, and the
 * rules live in `StoreMeterReadingRequest`.
 */
class MeterReadingController extends Controller
{
    public function index(MeterReadingIndexRequest $request): Response
    {
        $organizationId = (int) TenancyContext::organization()?->id;
        $unitId = $request->integer('unit_id') ?: null;
        $utility = $request->string('utility')->toString();

        return Inertia::render('organisation/units/meter-readings', [
            'readings' => $this->readings($organizationId, $unitId, $utility),
            'units' => $this->units($organizationId),
            'utilities' => $this->utilities(),
            'filters' => ['unit_id' => $unitId, 'utility' => $utility],
        ]);
    }

    /**
     * The paginated list. Extracted so `index` reads as "gather filters, render
     * props" and so the query can be reused by an export later.
     */
    private function readings(int $organizationId, ?int $unitId, string $utility): LengthAwarePaginator
    {
        return UnitMeterReading::query()
            ->filtered([
                'organization_id' => $organizationId,
                'unit_id' => $unitId,
                'utility' => UtilityType::tryFrom($utility) !== null ? $utility : null,
            ])
            ->with(['unit:id,name', 'recorder:id,name'])
            ->orderByDesc('read_on')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (UnitMeterReading $reading): array => (new UnitMeterReadingResource($reading))->resolve());
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function utilities(): array
    {
        return array_map(
            fn (UtilityType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'unit_of_measure' => $type->unitOfMeasure(),
            ],
            UtilityType::cases(),
        );
    }

    public function store(StoreMeterReadingRequest $request, RecordMeterReading $record): RedirectResponse
    {
        $reading = $record->handle($request->user(), $request->toData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Reading recorded for {$reading->unit?->name}.",
        ]);

        return InertiaRedirect::to($request->header('Referer') ?? route('tenant.meter-readings.index'), $request);
    }

    /**
     * Units in the organization, option-shaped for the select.
     *
     * @return array<int, array{value: int, label: string}>
     */
    private function units(int $organizationId): array
    {
        return Unit::query()
            ->whereIn('property_id', Property::query()
                ->where('organization_id', $organizationId)
                ->select('id'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Unit $unit): array => [
                'value' => $unit->id,
                'label' => $unit->name,
            ])
            ->all();
    }
}
