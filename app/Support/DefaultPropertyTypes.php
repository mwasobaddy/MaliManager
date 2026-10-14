<?php

namespace App\Support;

use App\Models\Organization;
use Illuminate\Support\Str;
use Spatie\Activitylog\Facades\Activity;

/**
 * The property-type catalog every organization starts with.
 *
 * Organization-scoped: each org gets its own copy and may then rename, add or
 * remove entries without touching anyone else's. The constants below are the
 * *starting* set, not a ceiling — `syncFor()` only ever adds what is missing,
 * so an owner who deletes a type does not get it resurrected by the next seed.
 *
 * Slugs are generated from the name rather than hand-written, which means a type
 * added here gets a usable path/import key for free and cannot collide with a
 * hand-typed one. `Str::slug` folds "4+ Bedroom Apartment" to
 * "4-bedroom-apartment" and "Bed & Breakfast (BnB)" to "bed-breakfast-bnb".
 */
class DefaultPropertyTypes
{
    public const RESIDENTIAL = 'residential';

    public const COMMERCIAL = 'commercial';

    /**
     * The seeded catalog, in display order.
     *
     * @return list<array{category: string, names: list<string>}>
     */
    public static function catalog(): array
    {
        return [
            [
                'category' => self::RESIDENTIAL,
                'names' => [
                    'Bedsitter',
                    'Studio Apartment',
                    '1-Bedroom Apartment',
                    '2-Bedroom Apartment',
                    '3-Bedroom Apartment',
                    '4+ Bedroom Apartment',
                    'Penthouse',
                    'Single-Family Detached House',
                    'Bungalow',
                    'Maisonette',
                    'Townhouse',
                    'Condominium Unit',
                    'Housing Co-op Unit',
                    'Duplex',
                    'Triplex',
                    'Quadplex / Fourplex',
                    'Villa',
                    'Mansion',
                    'Cottage',
                    'Cabin',
                    'Chalet',
                    'Mobile Home',
                    'Prefabricated / Modular Home',
                    'Serviced Apartment (Residential Lease)',
                    'Basement Apartment',
                    'Loft',
                ],
            ],
            [
                'category' => self::COMMERCIAL,
                'names' => [
                    'Corporate Office Building',
                    'Co-working Space',
                    'Medical Clinic',
                    'Dental Practice Office',
                    'High Street Retail Storefront',
                    'Strip Mall Unit',
                    'Regional Shopping Mall',
                    'Automobile Showroom',
                    'Furniture Showroom',
                    'Sit-down Restaurant',
                    'Fast Food Drive-Thru',
                    'Café',
                    'Bar / Pub',
                    'Nightclub',
                    'Bed & Breakfast (BnB)',
                    'Boutique Hotel',
                    'Commercial Resort',
                    'Highway Motel',
                    'Distribution Warehouse',
                    'Cold-Storage Warehouse',
                    'Heavy Manufacturing Factory',
                    'Light Industrial Workshop',
                    'Data Center Facility',
                    'Self-Storage Facility Unit',
                    'Commercial Parking Garage',
                    'Movie Theater / Cinema',
                ],
            ],
        ];
    }

    /**
     * Create the catalog for a newly created organization.
     */
    public static function createFor(Organization $organization, ?int $createdBy = null): void
    {
        // Seeded rows are not user actions. Logging them would write 52 entries
        // per new organization, which drowns the audit log — the thing anyone
        // actually reads when they need to know who changed what. An owner
        // adding a type by hand still logs normally.
        Activity::withoutLogging(function () use ($organization, $createdBy): void {
            $order = 0;

            foreach (self::catalog() as $group) {
                foreach ($group['names'] as $name) {
                    $organization->propertyTypes()->create([
                        'name' => $name,
                        'slug' => self::slugFor($name),
                        'category' => $group['category'],
                        'sort_order' => $order++,
                        'status' => 'active',
                        'created_by' => $createdBy,
                    ]);
                }
            }
        });
    }

    /**
     * Add catalog types an existing organization's set is missing.
     *
     * `createFor` only runs at organization creation, so a type added to the
     * catalog later would never reach organizations that already exist. Additive
     * by design: nothing is ever removed or renamed here, so an owner who
     * deleted a type keeps it deleted.
     *
     * @return int Number of types added.
     */
    public static function syncFor(Organization $organization): int
    {
        return (int) Activity::withoutLogging(
            fn (): int => self::syncTypes($organization)
        );
    }

    private static function syncTypes(Organization $organization): int
    {
        $existing = $organization->propertyTypes()
            ->withTrashed()
            ->pluck('slug')
            ->all();

        $added = 0;

        // Start after whatever the organization already uses, so a newly added
        // default lands at the end of their list instead of jumping to the top.
        $order = (int) $organization->propertyTypes()->withTrashed()->max('sort_order') + 1;

        foreach (self::catalog() as $group) {
            foreach ($group['names'] as $name) {
                $slug = self::slugFor($name);

                if (in_array($slug, $existing, true)) {
                    continue;
                }

                $organization->propertyTypes()->create([
                    'name' => $name,
                    'slug' => $slug,
                    'category' => $group['category'],
                    'sort_order' => $order++,
                    'status' => 'active',
                ]);

                $existing[] = $slug;
                $added++;
            }
        }

        return $added;
    }

    /**
     * Deterministic slug for a type name.
     */
    public static function slugFor(string $name): string
    {
        return str(Str::slug($name))
            ->limit(120, '')
            ->toString();
    }
}
