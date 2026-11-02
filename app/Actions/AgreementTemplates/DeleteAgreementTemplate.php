<?php

namespace App\Actions\AgreementTemplates;

use App\Models\AgreementTemplate;
use App\Services\Service;

class DeleteAgreementTemplate extends Service
{
    public function handle(AgreementTemplate $template): void
    {
        $this->transaction(function () use ($template): void {
            $this->delete($template);
        });
    }
}
