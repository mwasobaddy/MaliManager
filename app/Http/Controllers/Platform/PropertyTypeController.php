<?php

namespace App\Http\Controllers\Platform;

use App\Enums\PlatformPermissionKey;
use App\Enums\PropertyCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\ImportPropertyTypeRequest;
use App\Http\Requests\Platform\StorePropertyTypeRequest;
use App\Http\Requests\Platform\UpdatePropertyTypeRequest;
use App\Models\PropertyType;
use App\Support\CsvReader;
use App\Support\PropertyTypeCatalog;
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
    private const SORTABLE = [
        'name' => 'name',
        'category' => 'category',
        'sort_order' => 'sort_order',
        'status' => 'status',
    ];

    public function index(Request $request): Response
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

    public function store(StorePropertyTypeRequest $request): RedirectResponse
    {
        // `validated()` only returns declared rules, and `slug` is derived in
        // the request's passedValidation() rather than being a rule — so it has
        // to be read from the merged input, not from validated().
        PropertyType::create([
            ...$request->validated(),
            'slug' => $request->string('slug')->toString(),
            'sort_order' => $request->integer('sort_order') ?: $this->nextSortOrder(),
            'status' => 'active',
            'created_by' => $request->user()?->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Property type created.']);

        return redirect()->route('platform.property-types.index');
    }

    public function update(UpdatePropertyTypeRequest $request, PropertyType $propertyType): RedirectResponse
    {
        $propertyType->update($request->validated());

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
    public function destroy(Request $request, PropertyType $propertyType): RedirectResponse
    {
        $inUse = $propertyType->usageCount();
        $name = $propertyType->name;

        $propertyType->delete();

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
    public function export(Request $request): StreamedResponse
    {
        $scope = $request->string('scope')->toString() === 'page' ? 'page' : 'all';

        $query = $this->filtered($request)
            ->withCount('properties')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($scope === 'page') {
            $query->forPage((int) $request->integer('page', 1), 20);
        }

        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, ['Name', 'Category', 'Properties in use', 'Description']);

        foreach ($query->get() as $type) {
            fputcsv($csv, [
                $type->name,
                $type->category->value,
                $type->properties_count,
                $type->description ?? '',
            ]);
        }

        rewind($csv);

        return response()->streamDownload(fn () => fpassthru($csv), 'property-types.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * @return Builder<PropertyType>
     */
    private function filtered(Request $request): Builder
    {
        // `Request::string()` returns a Stringable and an object is always truthy,
        // so `->when($request->string('search')->trim(), ...)` would run the
        // filter with no term — binding `like '%%'` and matching everything.
        return $this->query()
            ->when(
                $request->string('search')->trim()->toString(),
                fn (Builder $query, string $search) => $query->where('name', 'like', "%{$search}%"),
            )
            ->when(
                $request->string('category')->toString() !== '',
                fn (Builder $query) => $query->where('category', $request->string('category')->toString()),
            );
    }

    private function nextSortOrder(): int
    {
        return (int) $this->query()->max('sort_order') + 1;
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
     * Create property types from an uploaded CSV.
     *
     * Rows are independent: a bad row is recorded and skipped while the good
     * rows still land.
     *
     * Recognised columns (matched case-insensitively, spaces or underscores):
     * Name (required), Category, Status.
     *
     * Because the catalog is global, a duplicate name is the common failure —
     * the file was probably exported from here and re-imported — so that row
     * is reported rather than silently treated as an update. Nobody has asked
     * for an upsert here and quietly rewriting an admin's curated category
     * from a spreadsheet is worse than a clear error.
     */
    public function import(ImportPropertyTypeRequest $request, CsvReader $csv): RedirectResponse
    {
        $parsed = $csv->rows($request->file('file'));
        $errors = [];
        $imported = 0;

        foreach ($parsed['rows'] as $row) {
            $data = $row['data'];
            $name = trim($data['name'] ?? '');

            if ($name === '') {
                $errors[] = ['row' => $row['row'], 'message' => "Row {$row['row']}: Name is required."];

                continue;
            }

            if (PropertyType::withTrashed()->where('name', $name)->exists()) {
                $errors[] = ['row' => $row['row'], 'message' => "Row {$row['row']}: \"{$name}\" already exists."];

                continue;
            }

            $category = strtolower(trim($data['category'] ?? '')) ?: PropertyCategory::Residential->value;

            if (! PropertyCategory::tryFrom($category)) {
                $errors[] = ['row' => $row['row'], 'message' => "Row {$row['row']}: category must be residential or commercial."];

                continue;
            }

            PropertyType::create([
                'name' => $name,
                'slug' => PropertyTypeCatalog::slugFor($name),
                'category' => $category,
                'sort_order' => $this->nextSortOrder(),
                'status' => strtolower(trim($data['status'] ?? 'active')) === 'inactive' ? 'inactive' : 'active',
            ]);

            $imported++;
        }

        if (! $parsed['ok']) {
            $errors = $parsed['rows'];
        }

        $failed = count($errors);

        if ($failed === 0) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => $imported === 1 ? '1 property type imported.' : "{$imported} property types imported.",
            ]);

            return redirect()->route('platform.property-types.index');
        }

        Inertia::flash('toast', [
            'type' => $imported > 0 ? 'warning' : 'error',
            'message' => "{$imported} imported, {$failed} failed.",
        ]);

        Inertia::flash('importErrors', $errors);

        return back();
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
