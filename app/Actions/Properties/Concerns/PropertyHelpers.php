<?php

namespace App\Actions\Properties\Concerns;

use App\Models\Unit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

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
}
