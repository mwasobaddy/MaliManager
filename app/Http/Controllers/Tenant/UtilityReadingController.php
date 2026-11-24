<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\Metering\ExportUtilityReadings;
use App\Actions\Metering\RecordUtilityReading;
use App\Enums\UtilityType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ExportUtilityReadingsRequest;
use App\Http\Requests\Tenant\StoreUtilityReadingRequest;
use App\Http\Requests\Tenant\UtilityReadingIndexRequest;
use App\Http\Resources\Metering\UtilityReadingResource;
use App\Models\Property;
use App\Models\Unit;
use App\Models\UtilityCharge;
use App\Models\UtilityReading;
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
 * response. The consumption calculation lives in `RecordUtilityReading`, and the
 * rules live in `StoreUtilityReadingRequest`.
 */
class UtilityReadingController extends Controller
{
    public function index(UtilityReadingIndexRequest $request): Response
    {
        $organizationId = (int) TenancyContext::organization()?->id;
        $unitId = $request->integer('unit_id') ?: null;
        $utility = $request->string('utility')->toString();

        return Inertia::render('organisation/utilities/index', [
            'readings' => $this->readings(
                $organizationId,
                $unitId,
                $utility ?: null,
                $request->string('search')->trim()->toString(),
                $request->string('sort')->toString(),
                $request->string('direction')->toString(),
            ),
            'focusedReading' => $request->string('mode')->toString() === 'view'
                ? $this->focusedReading($organizationId, $request->integer('reading'))
                : null,
            'units' => $this->units($organizationId),
            'properties' => $this->properties($organizationId),
            'charges' => $this->charges($organizationId),
            'utilities' => $this->utilities(),
            'filters' => [
                'unit_id' => $unitId,
                'utility' => $utility,
                'search' => $request->string('search')->trim()->toString(),
            ],
        ]);
    }

    /**
     * The paginated list. Extracted so `index` reads as "gather filters, render
     * props" and so the query can be reused by the export.
     */
    private function readings(
        int $organizationId,
        ?int $unitId,
        ?string $utility,
        string $search = '',
        string $sort = '',
        string $direction = 'desc',
    ): LengthAwarePaginator {
        return UtilityReading::query()
            ->filtered([
                'organization_id' => $organizationId,
                'unit_id' => $unitId,
                'utility' => UtilityType::tryFrom((string) $utility) !== null ? $utility : null,
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
            ])
            ->with(['unit:id,name', 'recorder:id,name'])
            ->paginate(18)
            ->withQueryString()
            ->through(fn (UtilityReading $reading): array => (new UtilityReadingResource($reading))->resolve());
    }

    /**
     * Deep links (?mode=view&reading=) resolve server-side so a reading off
     * the current page still opens. Org-scoped like the index query, so a
     * foreign id resolves to null and the sheet stays shut.
     *
     * @return array<string, mixed>|null
     */
    private function focusedReading(int $organizationId, int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $reading = UtilityReading::query()
            ->forOrganization($organizationId)
            ->whereKey($id)
            ->with(['unit:id,name', 'recorder:id,name'])
            ->first();

        if (! $reading) {
            return null;
        }

        return (new UtilityReadingResource($reading))->resolve();
    }

    /**
     * Streams the CSV. `scope=page` honours the pagination the user is
     * looking at; anything else exports the whole filtered set.
     */
    public function export(ExportUtilityReadingsRequest $request, ExportUtilityReadings $export): \Symfony\Component\HttpFoundation\Response
    {
        $csv = $export->handle($request->toData());

        return response()->streamDownload(
            function () use ($csv): void {
                echo $csv->content;
            },
            $csv->filename,
            ['Content-Type' => 'text/csv'],
        );
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

    public function store(StoreUtilityReadingRequest $request, RecordUtilityReading $record): RedirectResponse
    {
        $reading = $record->handle($request->user(), $request->toData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Reading recorded for {$reading->unit?->name}.",
        ]);

        return InertiaRedirect::to($request->header('Referer') ?? route('tenant.utilities.index'), $request);
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

    /**
     * Properties with their units, for the charge setup sheet's pickers.
     *
     * @return list<array{id: int, name: string, slug: string, units: list<array{id: int, name: string}>}>
     */
    private function properties(int $organizationId): array
    {
        return Property::query()
            ->where('organization_id', $organizationId)
            ->orderBy('name')
            ->with(['units' => fn ($query) => $query->select('id', 'property_id', 'name')->orderBy('name')])
            ->get(['id', 'name', 'slug'])
            ->map(fn (Property $property): array => [
                'id' => $property->id,
                'name' => $property->name,
                'slug' => $property->slug,
                'units' => $property->units
                    ->map(fn (Unit $unit): array => ['id' => $unit->id, 'name' => $unit->name])
                    ->all(),
            ])
            ->all();
    }

    /**
     * Active and inactive charges with their assigned units, for the charges
     * table. Amounts and watermarks included: the setup sheet edits per-unit
     * amounts in place.
     *
     * @return list<array<string, mixed>>
     */
    private function charges(int $organizationId): array
    {
        return UtilityCharge::query()
            ->forOrganization($organizationId)
            ->with(['property:id,name', 'units:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (UtilityCharge $charge): array => [
                'id' => $charge->id,
                'property_id' => $charge->property_id,
                'property_name' => $charge->property?->name,
                'name' => $charge->name,
                'kind' => $charge->kind->value,
                'kind_label' => $charge->kind->label(),
                'utility' => $charge->utility?->value,
                'utility_label' => $charge->utility?->label(),
                'unit_of_measure' => $charge->utility?->unitOfMeasure(),
                'rate' => $charge->rate,
                'currency' => $charge->currency,
                'is_active' => $charge->is_active,
                'units' => $charge->units
                    ->map(fn (Unit $unit): array => [
                        'id' => $unit->id,
                        'name' => $unit->name,
                        'amount' => $unit->pivot->amount,
                    ])
                    ->all(),
            ])
            ->all();
    }
}
