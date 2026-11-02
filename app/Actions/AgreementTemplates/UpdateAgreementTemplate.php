<?php

namespace App\Actions\AgreementTemplates;

use App\Models\AgreementTemplate;
use App\Models\User;
use App\Services\Service;
use App\Support\LeaseAgreementTemplate;

class UpdateAgreementTemplate extends Service
{
    public function handle(AgreementTemplate $template, User $actor, string $name, string $bodyHtml): AgreementTemplate
    {
        return $this->transaction(function () use ($template, $name, $bodyHtml) {
            $template->update([
                'name' => $name,
                'body_html' => LeaseAgreementTemplate::sanitize($bodyHtml),
            ]);

            return $template->refresh();
        });
    }
}
