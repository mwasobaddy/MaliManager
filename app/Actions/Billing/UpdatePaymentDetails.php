<?php

namespace App\Actions\Billing;

use App\Models\Organization;
use App\Models\User;
use App\Services\Service;

/**
 * Stores how tenants pay this organization (M-Pesa paybill/till and
 * notes), shown on the tenant rentals page and appended to rent
 * reminders. Merged into settings so currency and its siblings are
 * never disturbed.
 */
class UpdatePaymentDetails extends Service
{
    /**
     * @param  array{paybill_number?: ?string, account_reference?: ?string, till_number?: ?string, notes?: ?string}  $data
     */
    public function handle(Organization $organization, User $actor, array $data): Organization
    {
        $settings = $organization->settings ?? [];

        $settings['payment_details'] = [
            'paybill_number' => $data['paybill_number'] ?? null,
            'account_reference' => $data['account_reference'] ?? null,
            'till_number' => $data['till_number'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        return $this->save($organization->fill(['settings' => $settings]));
    }
}
