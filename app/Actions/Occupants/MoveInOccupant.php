<?php

namespace App\Actions\Occupants;

use App\Actions\Occupants\Concerns\OccupantHelpers;
use App\Models\Lease;
use App\Models\Occupant;
use App\Models\Property;
use App\Models\User;
use App\Services\Service;

/**
 * Move-in: activate a booked occupant. Every pending lease in the property
 * becomes active on the move-in date, the deposit plus first-period rent
 * is recorded on each, and the occupant flips to active. The booking is
 * what the lease terms were agreed on; the money is what starts them.
 */
class MoveInOccupant extends Service
{
    use OccupantHelpers;

    /**
     * @param  array{move_in_date?: string, payment_method?: string, payment_reference?: string|null}  $data
     */
    public function handle(Occupant $occupant, Property $property, User $actor, array $data): Occupant
    {
        return $this->transaction(function () use ($occupant, $property, $actor, $data) {
            abort_if($occupant->status === 'moved_out', 422, 'This occupant moved out — re-add them before moving in.');

            $pending = Lease::query()
                ->where('occupant_id', $occupant->id)
                ->where('property_id', $property->id)
                ->where('status', 'pending')
                ->whereNull('ends_at')
                ->get();

            abort_if($pending->isEmpty(), 422, 'There is nothing booked to move into — assign units first.');

            $moveInDate = $data['move_in_date'] ?? now()->toDateString();

            foreach ($pending as $lease) {
                $lease->update([
                    'status' => 'active',
                    'starts_at' => $moveInDate,
                ]);

                $this->refreshUnitStatus((int) $lease->unit_id);
            }

            $this->recordMoveInPayments($occupant, $property, $actor, $pending->all(), $data);

            $occupant->fill(['status' => 'active'])->save();

            return $occupant;
        });
    }
}
