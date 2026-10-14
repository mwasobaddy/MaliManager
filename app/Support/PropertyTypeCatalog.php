<?php

namespace App\Support;

use App\Models\PropertyType;
use Illuminate\Support\Str;

/**
 * The platform-wide property-type catalog.
 *
 * One catalogue for every tenant, curated by platform admins. It was
 * originally seeded per organization, which meant 52 rows written once per
 * tenant and the possibility of one owner's copy diverging from another's — and
 * it made "which types exist?" a question with 12 possible answers.
 *
 * `seed()` is idempotent and additive: it inserts what is missing and never
 * renames, edits or deletes an existing row. An admin who renamed "Villa" or
 * deleted a type keeps their version across re-seeds, exactly as
 * `DefaultSubRoles::syncBundlesFor()` behaves for sub-permissions.
 */
class PropertyTypeCatalog
{
    public const RESIDENTIAL = 'residential';

    public const COMMERCIAL = 'commercial';

    /**
     * The starting catalog, in display order.
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
     * Insert any catalog entries that do not exist yet.
     *
     * Additive by design: nothing already present is touched. That is what
     * makes this safe to call from a seeder that also runs against databases
     * where an admin has customised the catalog.
     *
     * @return int Number of types added.
     */
    public static function seed(): int
    {
        $existing = PropertyType::withTrashed()->pluck('slug')->all();

        // Start after whatever is already used, so a newly added default lands
        // at the end of the list instead of jumping to the top.
        $order = (int) PropertyType::withTrashed()->max('sort_order') + 1;
        $added = 0;

        foreach (self::catalog() as $group) {
            foreach ($group['names'] as $name) {
                $slug = self::slugFor($name);

                if (in_array($slug, $existing, true)) {
                    continue;
                }

                PropertyType::create([
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
     *
     * Generated rather than hand-written so an entry added here gets a usable
     * import key for free. `Str::slug` folds "4+ Bedroom Apartment" to
     * "4-bedroom-apartment" and "Bed & Breakfast (BnB)" to
     * "bed-breakfast-bnb".
     */
    public static function slugFor(string $name): string
    {
        return Str::limit(Str::slug($name), 120, '');
    }
}
