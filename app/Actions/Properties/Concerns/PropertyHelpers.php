<?php

namespace App\Actions\Properties\Concerns;

use App\Models\Unit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait PropertyHelpers
{
    private function assertWithinLimit(?int $limit, int $current, string $resource): void
    {
        if ($limit !== null && $current >= $limit) {
            throw new \DomainException(
                "Your current plan allows up to {$limit} ".Str::plural($resource, (int) $limit).'. Please upgrade your plan to add more.',
            );
        }
    }

    /**
     * Attach photos uploaded alongside the unit.
     *
     * Inside the same transaction as the unit insert, so a failed upload cannot
     * leave a unit whose photo set half-wrote. Medialibrary records its own
     * rows; `$unit` is saved above so it has an id for them to point at.
     *
     * @param  iterable<UploadedFile>  $photos
     */
    private function attachUnitPhotos(Unit $unit, iterable $photos): void
    {
        foreach ($photos as $photo) {
            $unit->addMedia($photo)->toMediaCollection('photos');
        }
    }

    /**
     * Append uploads and reused library photos, capped at the unit ceiling.
     * Reuse copies the file into the unit's own folder — the source unit
     * keeps its photo, and deleting one side never orphans the other. Only
     * media already sitting on this organization's units is eligible; foreign
     * ids are ignored rather than rejected, because a stale picker should not
     * fail an otherwise good save.
     *
     * @param  array{photos?: iterable<UploadedFile>, reuse_photo_ids?: iterable<int>}  $data
     */
    private function attachAdditionalUnitPhotos(Unit $unit, array $data): void
    {
        $uploads = is_array($data['photos'] ?? null) ? array_values($data['photos']) : [];
        $reuseIds = array_values(array_unique(array_map(
            intval(...),
            is_array($data['reuse_photo_ids'] ?? null) ? $data['reuse_photo_ids'] : [],
        )));

        $attached = $unit->getMedia('photos')->count();

        if (count($uploads) + count($reuseIds) + $attached > Unit::MAX_PHOTOS) {
            throw new \DomainException(
                'A unit holds at most '.Unit::MAX_PHOTOS.' photos.',
            );
        }

        $this->attachUnitPhotos($unit, $uploads);
        $this->reuseUnitPhotos($unit, $reuseIds);
    }

    /**
     * @param  list<int>  $mediaIds
     */
    private function reuseUnitPhotos(Unit $unit, array $mediaIds): void
    {
        if ($mediaIds === []) {
            return;
        }

        $organization = $unit->property?->organization;

        if ($organization === null) {
            return;
        }

        $unitIds = Unit::query()
            ->whereIn('property_id', $organization->properties()->pluck('id'))
            ->pluck('id');

        $media = Media::query()
            ->whereIn('id', $mediaIds)
            ->where('model_type', $unit->getMorphClass())
            ->whereIn('model_id', $unitIds)
            ->get();

        foreach ($media as $item) {
            $unit->addMediaFromDisk($item->getPathRelativeToRoot(), $item->disk)
                ->toMediaCollection('photos');
        }
    }
}
