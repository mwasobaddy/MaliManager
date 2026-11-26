<?php

declare(strict_types=1);

namespace App\Actions\LandParcels;

use App\Models\LandParcelSection;
use App\Models\User;
use App\Services\Service;

/**
 * Moves a section between its pre-lease states: vacant <-> reserved.
 * Leased and maintenance sections are not touched here — leased ends via
 * EndLandParcelSectionLease, maintenance via the section form.
 */
class UpdateLandParcelSectionStatus extends Service
{
    public function handle(LandParcelSection $section, User $actor, string $status): LandParcelSection
    {
        abort_if(! in_array($section->status, ['vacant', 'reserved'], true), 422, 'Only vacant or reserved sections can change status here. End the active lease first.');
        abort_if($section->status === $status, 422, "The section is already {$status}.");

        return $this->transaction(function () use ($section, $actor, $status): LandParcelSection {
            $section->update(['status' => $status]);

            activity()->inLog('land_parcels')
                ->causedBy($actor)
                ->performedOn($section)
                ->event("section.{$status}")
                ->log("Marked section {$section->name} as {$status}");

            return $section->refresh();
        });
    }
}
