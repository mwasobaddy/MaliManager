<?php

declare(strict_types=1);

namespace App\Actions\LandParcels;

use App\Data\LandParcels\LandParcelSectionLeaseData;
use App\Models\AgreementTemplate;
use App\Models\LandParcel;
use App\Models\LandParcelSection;
use App\Models\Lease;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Services\Service;
use App\Support\LeaseAgreementTemplate;

/**
 * Leases a land parcel section: resolves or creates the tenant person, creates the
 * lease, renders the agreement from the organization's template, and marks the
 * section leased.
 *
 * The three writes run in one transaction. Before this lived in a controller they
 * did not, so a template render failure left a lease behind with the section still
 * marked available.
 *
 * Authorization stays in the controller: it is an HTTP concern and it aborts.
 */
class LeaseLandParcelSection extends Service
{
    public function handle(
        LandParcelSection $section,
        LandParcel $parcel,
        Organization $organization,
        User $actor,
        LandParcelSectionLeaseData $data,
    ): Lease {
        return $this->transaction(function () use ($section, $parcel, $organization, $actor, $data) {
            $person = $this->resolvePerson($actor, $data);

            $lease = new Lease([
                'person_id' => $person->id,
                'organization_id' => $organization->id,
                'tenant_id' => $data->tenantId,
                'land_parcel_id' => $parcel->id,
                'land_parcel_section_id' => $section->id,
                'rent_amount' => $data->rentAmount,
                'rent_frequency' => $data->rentFrequency,
                'currency' => $data->currency,
                'deposit' => $data->deposit,
                'starts_at' => $data->startsAt,
                'ends_at' => $data->endsAt,
                'status' => 'active',
                'created_by' => $actor->id,
            ]);
            $this->save($lease);

            $agreement = $this->generateAgreement($organization, $lease, $data->templateId);

            if ($agreement !== null) {
                $lease->update(['agreement_text' => $agreement]);
            }

            $section->update(['status' => 'leased']);

            return $lease;
        });
    }

    private function resolvePerson(User $actor, LandParcelSectionLeaseData $data): Person
    {
        if ($data->personId !== null) {
            return Person::findOrFail($data->personId);
        }

        return Person::firstOrCreate(
            ['email' => $data->tenantEmail],
            [
                'first_name' => $data->tenantName,
                'last_name' => '',
                'created_by' => $actor->id,
            ],
        );
    }

    private function generateAgreement(Organization $organization, Lease $lease, ?int $templateId): ?string
    {
        $template = null;

        if ($templateId) {
            $template = AgreementTemplate::where('organization_id', $organization->id)
                ->where('id', $templateId)
                ->first();
        }

        if (! $template) {
            $template = AgreementTemplate::where('organization_id', $organization->id)
                ->whereNull('property_id')
                ->first();
        }

        if (! $template) {
            return null;
        }

        return LeaseAgreementTemplate::render($template->body_html, $lease);
    }
}
