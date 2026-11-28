<?php

namespace App\Actions\AgreementTemplates;

use App\Models\AgreementTemplate;
use App\Models\Organization;
use App\Models\User;
use App\Services\Service;

/**
 * The one template every organization starts with: a plain residential
 * agreement using only tokens that always resolve (names, dates, rent,
 * the billing clauses), so a quick-added tenant prints a finished
 * agreement with zero template decisions. Anything nullable on the
 * lease (end date, email, deposit) is prose, never a token — empty
 * tokens survive the merge verbatim.
 */
class CreateDefaultAgreementTemplate extends Service
{
    public const NAME = 'Standard Residential Agreement';

    public function handle(User $actor, Organization $organization): AgreementTemplate
    {
        return app(CreateAgreementTemplate::class)->handle(
            $actor,
            $organization->id,
            null,
            self::NAME,
            self::body(),
        );
    }

    /**
     * Idempotent: the org default is its oldest org-wide template, so an
     * org that already set up its library keeps its own default and only
     * orgs without one get the shipped copy.
     */
    public function ensureForOrg(Organization $organization, User $creator): AgreementTemplate
    {
        $existing = AgreementTemplate::query()
            ->where('organization_id', $organization->id)
            ->whereNull('property_id')
            ->orderBy('id')
            ->first();

        if ($existing instanceof AgreementTemplate) {
            return $existing;
        }

        return $this->handle($creator, $organization);
    }

    public static function body(): string
    {
        return <<<'HTML'
        <h2>Residential Tenancy Agreement</h2>
        <p>This agreement is made between <strong>{{organization_name}}</strong> (the Landlord) and <strong>{{occupant_name}}</strong> (the Tenant).</p>
        <h3>1. Premises</h3>
        <p>The Landlord lets to the Tenant <strong>{{unit_name}}</strong> at <strong>{{property_name}}</strong>, to be used as a residence only.</p>
        <h3>2. Term</h3>
        <p>The tenancy begins on <strong>{{start_date}}</strong> and continues {{rent_frequency}} until either party gives one month's written notice.</p>
        <h3>3. Rent</h3>
        <p>The Tenant pays <strong>{{rent_amount}} {{currency}}</strong> {{rent_frequency}}, in advance and without deduction. A refundable deposit was collected on move-in as receipted.</p>
        <h3>4. Utilities and extras</h3>
        <p>Utility billing for this unit: {{utility_charges}}. Additional agreed charges: {{additional_charges}}.</p>
        <h3>5. Tenant obligations</h3>
        <p>The Tenant keeps the premises clean and in good repair, allows reasonable access for inspection and maintenance on 24 hours' notice, and does not sublet without the Landlord's written consent.</p>
        <h3>6. Termination</h3>
        <p>Either party may end this tenancy with one month's written notice. Unpaid rent and damages beyond fair wear and tear may be deducted from the deposit, with the balance refunded within 30 days of handover.</p>
        <p>Signed: ______________________ (Landlord) &nbsp;&nbsp; ______________________ (Tenant) &nbsp;&nbsp; Date: __________</p>
        HTML;
    }
}
