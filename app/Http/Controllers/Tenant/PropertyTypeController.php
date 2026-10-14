<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\PropertyCategory;
use App\Enums\SubPermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StorePropertyTypeRequest;
use App\Http\Requests\Tenant\UpdatePropertyTypeRequest;
use App\Models\PropertyType;
use App\Support\TenancyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The organization's property-type catalog.
 *
 * A catalog of labels for properties, classified residential or commercial.
 * It is not a property itself — nothing about a property depends on one beyond
 * an optional label — which is why deleting a type in use simply unlabels the
 * properties that referenced it rather than cascading.
 *
 * Every query is scoped to the current organization. The catalog is per-org on
 * purpose, so one owner renaming "Villa" cannot reach another's properties.
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

        return Inertia::render('organisation/property-types/index', [
            'propertyTypes' => $types,
            'counts' => [
                'residential' => $this->scoped()->where('category', PropertyCategory::Residential)->count(),
                'commercial' => $this->scoped()->where('category', PropertyCategory::Commercial)->count(),
            ],
            'canCreate' => $this->allowed(SubPermissionKey::PropertyTypeCreate),
            'canEdit' => $this->allowed(SubPermissionKey::PropertyTypeEdit),
            'canDelete' => $this->allowed(SubPermissionKey::PropertyTypeDelete),
        ]);
    }

    public function store(StorePropertyTypeRequest $request): RedirectResponse
    {
        $organization = TenancyContext::organization();

        // `validated()` only returns declared rules, and `slug` is derived in
        // the request's passedValidation() rather than being a rule — so it has
        // to be read from the merged input, not from validated().
        $organization->propertyTypes()->create([
            ...$request->validated(),
            'slug' => $request->string('slug')->toString(),
            'sort_order' => $request->integer('sort_order') ?: $this->nextSortOrder(),
            'status' => 'active',
            'created_by' => $request->user()?->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Property type created.']);

        return back();
    }

    public function update(UpdatePropertyTypeRequest $request, PropertyType $propertyType): RedirectResponse
    {
        $propertyType->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Property type updated.']);

        return back();
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

        return back();
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
    private function scoped(): Builder
    {
        return PropertyType::query()
            ->where('organization_id', TenancyContext::organization()?->id);
    }

    /**
     * @return Builder<PropertyType>
     */
    private function filtered(Request $request): Builder
    {
        // `Request::string()` returns a Stringable and an object is always truthy,
        // so `->when($request->string('search')->trim(), ...)` would run the
        // filter with no term — binding `like '%%'` and matching everything.
        return $this->scoped()
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
        return (int) $this->scoped()->max('sort_order') + 1;
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
     * Whether the current user holds a sub-permission.
     *
     * The route middleware is the real gate; these flags only decide which
     * buttons render.
     */
    private function allowed(SubPermissionKey $key): bool
    {
        $user = request()->user();
        $organization = TenancyContext::organization();

        if ($user === null || $organization === null) {
            return false;
        }

        return $user->hasSubPermission($organization, $key);
    }
}
