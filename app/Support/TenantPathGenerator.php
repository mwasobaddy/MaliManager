<?php

namespace App\Support;

use App\Models\AgreementTemplate;
use App\Models\Expense;
use App\Models\Inspection;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Places tenant documents inside the StorageLayout folder structure while
 * keeping medialibrary's validation, single-file replacement, and disk
 * abstraction (local dev, S3 production). Unknown collections fall back to
 * medialibrary's default layout so existing media is safe.
 */
class TenantPathGenerator implements PathGenerator
{
    public function getRelativePathForMedia(Media $media): string
    {
        $base = $this->tenantBasePath($media);

        if ($base !== null) {
            return $base.'/';
        }

        $prefix = config('media-library.prefix', '');

        return ($prefix !== '' ? $prefix.'/' : '').$media->getKey().'/';
    }

    public function getPath(Media $media): string
    {
        // Directory only — medialibrary appends the file name itself.
        return $this->getRelativePathForMedia($media);
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getRelativePathForMedia($media).$media->getKey().'/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getRelativePathForMedia($media).$media->getKey().'/responsive-images/';
    }

    /**
     * The collection-specific folder prefix, or null to use the default.
     */
    private function tenantBasePath(Media $media): ?string
    {
        $model = $media->model;

        if ($model instanceof Expense && $media->collection_name === 'receipt') {
            return StorageLayout::expenseReceiptsPath($model);
        }

        if ($model instanceof Inspection && $media->collection_name === 'photos') {
            return StorageLayout::inspectionPhotosPath($model);
        }

        if ($model instanceof MaintenanceRequest && $media->collection_name === 'photos') {
            return StorageLayout::maintenancePhotosPath($model);
        }

        if ($model instanceof Lease && $media->collection_name === 'agreement') {
            $property = $model->property;

            if ($property) {
                return StorageLayout::propertyLeasePath($property);
            }
        }

        if ($model instanceof AgreementTemplate && $media->collection_name === 'document') {
            $organization = $model->organization;

            if ($organization) {
                return StorageLayout::templatesPath($organization);
            }
        }

        return null;
    }
}
