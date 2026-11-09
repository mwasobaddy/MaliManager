<?php

namespace App\Support;

use App\Models\Organization;

/**
 * Completion state for the landlord setup funnel's stepper.
 *
 * Every setup page reports this so the step titles can link between funnel
 * steps without letting anyone leapfrog an unskippable step: step 1
 * (organization) gates everything downstream, and step 2 (first asset)
 * gates the team and review steps. The team step itself is skippable, so
 * it never gates anything.
 */
class SetupProgress
{
    /**
     * @return array{hasOrganization: bool, hasAsset: bool, hasConfirmedPlan: bool}
     */
    public static function forOrganization(?Organization $organization): array
    {
        if ($organization === null) {
            return ['hasOrganization' => false, 'hasAsset' => false, 'hasConfirmedPlan' => false];
        }

        return [
            'hasOrganization' => true,
            // Either asset kind counts: the funnel offers buildings and land
            // parcels as alternatives on the same step.
            'hasAsset' => $organization->properties()->exists()
                || $organization->landParcels()->exists(),
            'hasConfirmedPlan' => $organization->plan_confirmed_at !== null,
        ];
    }
}
