<?php

namespace App\Support;

use App\Models\Expense;
use App\Models\Inspection;
use App\Models\LandParcel;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Organization;
use App\Models\Property;
use Illuminate\Support\Facades\Storage;

/**
 * The canonical on-disk layout for tenant documents. Every consumer
 * (medialibrary path generator, marker files) derives paths from here so
 * local and S3 disks stay identical in structure:
 *
 *   {organization-slug}/templates/lease          org-wide agreement templates
 *   {organization-slug}/{property-slug}/lease    uploaded lease agreements
 *
 * Folders are keyed by slug at creation time and are never moved on rename.
 */
class StorageLayout
{
    /**
     * The organization root folder, e.g. "acme-estates".
     */
    public static function organizationPrefix(Organization $organization): string
    {
        return $organization->slug;
    }

    /**
     * Folder for org-wide agreement templates, e.g. "acme-estates/templates/lease".
     */
    public static function templatesPath(Organization $organization): string
    {
        return self::organizationPrefix($organization).'/templates/lease';
    }

    /**
     * Folder for images pasted/inserted into agreement rich-text,
     * e.g. "acme-estates/agreement-images".
     */
    public static function agreementImagesPath(Organization $organization): string
    {
        return self::organizationPrefix($organization).'/agreement-images';
    }

    /**
     * Folder for a property's lease documents,
     * e.g. "acme-estates/sunset-heights/lease".
     */
    public static function propertyLeasePath(Property $property): string
    {
        $organizationSlug = $property->organization?->slug ?? $property->organization()->value('slug');

        return $organizationSlug.'/'.$property->slug.'/lease';
    }

    /**
     * Folder for receipts attached to an expense. Expenses target either a
     * property or a land parcel, so both layouts exist:
     *   {org}/{property}/expenses   |   {org}/land-parcels/{parcel}/expenses
     */
    public static function expenseReceiptsPath(Expense $expense): ?string
    {
        $asset = $expense->expenseable;

        if ($asset instanceof Property) {
            $organizationSlug = $asset->organization?->slug ?? $asset->organization()->value('slug');

            return $organizationSlug.'/'.$asset->slug.'/expenses';
        }

        if ($asset instanceof LandParcel) {
            $organizationSlug = $asset->organization?->slug ?? $asset->organization()->value('slug');

            return $organizationSlug.'/land-parcels/'.$asset->slug.'/expenses';
        }

        return null;
    }

    /**
     * Folder for unit inspection photos:
     *   {org}/{property}/inspections
     */
    public static function inspectionPhotosPath(Inspection $inspection): ?string
    {
        $property = $inspection->property;

        if ($property === null) {
            return null;
        }

        $organizationSlug = $property->organization?->slug ?? $property->organization()->value('slug');

        return $organizationSlug.'/'.$property->slug.'/inspections';
    }

    /**
     * Folder for photos attached to a maintenance request:
     *   {org}/{property}/maintenance
     */
    public static function maintenancePhotosPath(MaintenanceRequest $request): ?string
    {
        $property = $request->property;

        if ($property === null) {
            return null;
        }

        $organizationSlug = $property->organization?->slug ?? $property->organization()->value('slug');

        return $organizationSlug.'/'.$property->slug.'/maintenance';
    }

    /**
     * Create the folder skeleton for a new organization. Object storage has
     * no real directories, so a marker file makes them visible in consoles.
     */
    public static function bootstrapOrganization(Organization $organization): void
    {
        Storage::disk(self::disk())->put(self::templatesPath($organization).'/.keep', '');

        foreach ($organization->properties as $property) {
            self::bootstrapProperty($property);
        }
    }

    /**
     * Create the folder skeleton for a new property.
     */
    public static function bootstrapProperty(Property $property): void
    {
        $organizationSlug = $property->organization?->slug ?? $property->organization()->value('slug');

        Storage::disk(self::disk())->put(self::propertyLeasePath($property).'/.keep', '');
        Storage::disk(self::disk())->put($organizationSlug.'/'.$property->slug.'/inspections/.keep', '');
    }

    /**
     * Everything flows through the default disk: "local" in development,
     * "s3" in production (FILESYSTEM_DISK).
     */
    public static function disk(): string
    {
        return (string) config('filesystems.default');
    }
}
