<?php

namespace App\Http\Controllers\Platform;

use App\Actions\PropertyTypes\CreatePropertyType;
use App\Actions\PropertyTypes\DeletePropertyType;
use App\Actions\PropertyTypes\ExportPropertyTypes;
use App\Actions\PropertyTypes\ImportPropertyTypes;
use App\Actions\PropertyTypes\UpdatePropertyType;
use App\Concerns\FlashesImportResult;
use App\Enums\PlatformPermissionKey;
use App\Enums\PropertyCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\DestroyPropertyTypeRequest;
use App\Http\Requests\Platform\ExportPropertyTypesRequest;
use App\Http\Requests\Platform\ImportPropertyTypeRequest;
use App\Http\Requests\Platform\PropertyTypeIndexRequest;
use App\Http\Requests\Platform\StorePropertyTypeRequest;
use App\Http\Requests\Platform\UpdatePropertyTypeRequest;
use App\Models\PropertyType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The platform-wide property-type catalog.
 *
 * A catalog of labels for properties, classified residential or commercial. It
 * is not a property itself — nothing about a property depends on one beyond an
 * optional label — which is why deleting a type in use simply unlabels the
 * properties that referenced it rather than cascading.
 *
 * Central rather than per-organization: one catalogue for every tenant,
 * curated here under `property-type.*` permissions. Tenant property forms read
 * it, so a rename reaches everyone at once. That is the intent — the catalog is
 * platform configuration, not something each landlord curates for themselves.
 */
class PropertyTypeController extends Controller
{
    use FlashesImportResult;

    private const SORTABLE = [
        'name' => 'name',
        'category' => 'category',
        'sort_order' => 'sort_order',
        'status' => 'status',
    ];

    public function index(PropertyTypeIndexRequest $request): Response
    {
        $sort = $request->string('sort')->toString();
        $sortColumn = self::SORTABLE[$sort] ?? null;
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        $types = $this->filtered($request)
            ->withCount('properties')
            ->when(
                $sortColumn !== null,
                fn (Builder $query) => $query->orderBy($sortColumn, $direction),
            )
            // Display order is the catalog's own order, ascending — so the
            // unsorted default must not inherit the `desc` default above, or
            // clicking away from a sort would flip the list.
            ->when($sortColumn === null, fn (Builder $query) => $query->orderBy('sort_order')->orderBy('name'))
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (PropertyType $type): array => $this->serialize($type));

        return Inertia::render('platform/property-types/index', [
            'propertyTypes' => $types,
            'counts' => [
                'residential' => $this->query()->where('category', PropertyCategory::Residential)->count(),
                'commercial' => $this->query()->where('category', PropertyCategory::Commercial)->count(),
            ],
            'canCreate' => $this->allowed(PlatformPermissionKey::PropertyTypeCreate),
            'canEdit' => $this->allowed(PlatformPermissionKey::PropertyTypeEdit),
            'canDelete' => $this->allowed(PlatformPermissionKey::PropertyTypeDelete),
            'canImport' => $this->allowed(PlatformPermissionKey::PropertyTypeImport),
            'canExport' => $this->allowed(PlatformPermissionKey::PropertyTypeExport),
        ]);
    }

    public function store(StorePropertyTypeRequest $request, CreatePropertyType $action): RedirectResponse
    {
        // `validated()` only returns declared rules, and `slug` is derived in
        // the request's passedValidation() rather than being a rule — so it has
        // to be read from the merged input, not from validated().
        $action->handle(
            [...$request->validated(), 'slug' => $request->string('slug')->toString()],
            $request->integer('sort_order'),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Property type created.']);

        return redirect()->route('platform.property-types.index');
    }

    public function update(UpdatePropertyTypeRequest $request, PropertyType $propertyType, UpdatePropertyType $action): RedirectResponse
    {
        $action->handle($propertyType, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Property type updated.']);

        return redirect()->route('platform.property-types.index');
    }

    /**
     * Delete a type.
     *
     * Properties that referenced it keep existing and end up with no type, so a
     * mis-click cannot take a landlord's buildings with it. The confirmation
     * states the count for that reason.
     */
    public function destroy(DestroyPropertyTypeRequest $request, PropertyType $propertyType, DeletePropertyType $action): RedirectResponse
    {
        $inUse = $propertyType->usageCount();
        $name = $propertyType->name;

        $action->handle($propertyType);

        $suffix = $inUse === 1 ? '1 property is now untyped.' : "{$inUse} properties are now untyped.";

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $inUse > 0
                ? "The type \"{$name}\" was deleted. {$suffix}"
                : "The type \"{$name}\" was deleted.",
        ]);

        return redirect()->route('platform.property-types.index');
    }

    /**
     * CSV export of the catalog, honouring the table's own filters.
     *
     * Export rather than import: the catalog is seeded per organization and an
     * owner adds a type by hand in seconds, so a bulk-load path would be a way
     * to get the catalog *wrong* in bulk. Export is the useful direction — the
     * 52 seeded rows are worth having in a spreadsheet.
     */
    public function export(ExportPropertyTypesRequest $request, ExportPropertyTypes $export): StreamedResponse
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
     * @return Builder<PropertyType>
     */
    private function filtered(Request $request): Builder
    {
        return PropertyType::query()->filtered([
            'search' => $request->string('search')->trim()->toString(),
            'category' => $request->string('category')->toString(),
        ]);
    }

    /**
     * The catalogue query. Global by definition — there is one set of types.
     *
     * @return Builder<PropertyType>
     */
    private function query(): Builder
    {
        return PropertyType::query();
    }

    /**
     * Create property types from an uploaded CSV. A bad row is recorded and
     * skipped while the good rows still land.
     */
    public function import(ImportPropertyTypeRequest $request, ImportPropertyTypes $import): RedirectResponse
    {
        $result = $import->handle($request->toData());

        return $this->respondToImport($result, 'property type', 'property types', 'platform.property-types.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(PropertyType $type): array
    {
        return [
            'id' => $type->id,
            'name' => $type->name,
            'slug' => $type->slug,
            'category' => $type->category->value,
            'category_label' => $type->category->label(),
            'sort_order' => $type->sort_order,
            'status' => $type->status,
            'description' => $type->description,
            'properties_count' => $type->properties_count ?? 0,
        ];
    }

    /**
     * Whether the current user holds a platform permission.
     *
     * The route middleware is the real gate; these flags only decide which
     * buttons render.
     */
    private function allowed(PlatformPermissionKey $key): bool
    {
        return request()->user()?->hasPermissionTo($key->value) ?? false;
    }
}
