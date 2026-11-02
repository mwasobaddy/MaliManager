<?php

namespace App\Actions\AgreementTemplates;

use App\Models\AgreementTemplate;
use App\Models\User;
use App\Services\Service;
use App\Support\LeaseAgreementTemplate;

class CreateAgreementTemplate extends Service
{
    public function handle(User $actor, int $organizationId, ?int $propertyId, string $name, string $bodyHtml): AgreementTemplate
    {
        return $this->transaction(function () use ($actor, $organizationId, $propertyId, $name, $bodyHtml) {
            $template = new AgreementTemplate([
                'organization_id' => $organizationId,
                'property_id' => $propertyId,
                'name' => $name,
                'body_html' => LeaseAgreementTemplate::sanitize($bodyHtml),
                'created_by' => $actor->id,
            ]);

            return $this->save($template);
        });
    }
}
